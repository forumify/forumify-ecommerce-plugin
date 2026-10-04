<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use DateTimeZone;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Revenue\ProductSales;
use Forumify\Ecommerce\Revenue\ProductSalesService;
use Forumify\Ecommerce\Revenue\SalesPeriod;
use Forumify\Ecommerce\Service\StoreSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProductSalesServiceTest extends TestCase
{
    public function testSalesAreMergedRankedAndComparedToThePeriodBefore(): void
    {
        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('sumSalesByProduct')->willReturnOnConsecutiveCalls(
            [
                self::row('vip', 'VIP', 'monthly', 'USD', false, 2, 2000, '2026-02-10 10:00:00'),
                self::row('vip', 'VIP', 'monthly', 'USD', true, 3, 3000, '2026-02-12 10:00:00'),
                self::row('vip', 'VIP rank', 'yearly', 'EUR', false, 1, 8726, '2026-02-20 10:00:00'),
                self::row('hoodie', 'Hoodie', 'default', 'USD', false, 4, 10000, '2026-02-05 10:00:00'),
                self::row('sticker', 'Sticker', 'default', 'USD', false, 5, 0, '2026-02-06 10:00:00'),
            ],
            [
                self::row('vip', 'VIP', 'monthly', 'USD', false, 1, 10000, '2026-01-10 10:00:00'),
            ],
        );

        $report = $this->createService($orderRepository)->getReport(SalesPeriod::LastMonth);

        self::assertSame('USD 250.00', (string)$report->revenue);
        self::assertSame('USD 100.00', (string)$report->previousRevenue);
        self::assertEqualsWithDelta(1.5, $report->getChange(), 0.0001);

        self::assertSame(['vip', 'hoodie', 'sticker'], array_map(
            static fn(ProductSales $sales) => $sales->key,
            $report->products,
        ));
        [$vip, $hoodie, $sticker] = $report->products;

        self::assertSame('VIP rank', $vip->title);
        self::assertSame('USD 150.00', (string)$vip->revenue);
        self::assertSame([3, 3], [$vip->newOrders, $vip->renewals]);
        self::assertEqualsWithDelta(0.5, $vip->getChange(), 0.0001);
        self::assertSame(['yearly', 'monthly'], array_map(static fn(ProductSales $tier) => $tier->key, $vip->tiers));
        self::assertSame([2, 3], [$vip->tiers[1]->newOrders, $vip->tiers[1]->renewals]);

        self::assertTrue($hoodie->isNew());
        self::assertNull($hoodie->getChange());

        self::assertSame(5, $sticker->getOrders());
        self::assertTrue($sticker->revenue->isZero());
        self::assertEqualsWithDelta(1.0, $vip->share + $hoodie->share + $sticker->share, 0.0001);
        self::assertEqualsWithDelta(0.6, $vip->share, 0.0001);
    }

    public function testAllTimeHasNothingToCompareAgainst(): void
    {
        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository
            ->expects(self::once())
            ->method('sumSalesByProduct')
            ->with(null, null)
            ->willReturn([self::row('hoodie', 'Hoodie', 'default', 'USD', false, 1, 2500, '2026-02-05 10:00:00')]);

        $report = $this->createService($orderRepository)->getReport(SalesPeriod::AllTime);

        self::assertNull($report->previousRevenue);
        self::assertNull($report->products[0]->previousRevenue);
        self::assertFalse($report->products[0]->isNew());
    }

    private function createService(OrderRepository $orderRepository): ProductSalesService
    {
        $storeSettings = $this->createMock(StoreSettings::class);
        $storeSettings->method('getCurrency')->willReturn('USD');

        $exchangeRates = $this->createMock(ExchangeRateRepository::class);
        $exchangeRates->method('findAllRates')->willReturn(['EUR' => '0.872600000000']);

        $timezoneResolver = $this->createMock(TimezoneResolver::class);
        $timezoneResolver->method('getTimezone')->willReturn(new DateTimeZone('UTC'));

        return new ProductSalesService(
            $orderRepository,
            new CurrencyConverter($exchangeRates, new NullLogger()),
            $storeSettings,
            $timezoneResolver,
        );
    }

    /**
     * @return array{
     *     productKey: string,
     *     productTitle: string,
     *     tierKey: string,
     *     tierLabel: string,
     *     currency: string,
     *     renewal: bool,
     *     count: int,
     *     total: int,
     *     lastPaidAt: string,
     * }
     */
    private static function row(
        string $product,
        string $title,
        string $tier,
        string $currency,
        bool $renewal,
        int $count,
        int $total,
        string $lastPaidAt,
    ): array {
        return [
            'productKey' => $product,
            'productTitle' => $title,
            'tierKey' => $tier,
            'tierLabel' => ucfirst($tier),
            'currency' => $currency,
            'renewal' => $renewal,
            'count' => $count,
            'total' => $total,
            'lastPaidAt' => $lastPaidAt,
        ];
    }
}

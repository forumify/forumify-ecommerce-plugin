<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use Brick\Money\Money;
use DateTimeImmutable;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Revenue\RevenueCapService;
use Forumify\Ecommerce\Revenue\RevenueTier;
use Forumify\Ecommerce\Revenue\RevenueTierResolver;
use Forumify\Ecommerce\Service\StoreSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RevenueCapServiceTest extends TestCase
{
    private OrderRepository&MockObject $orderRepository;
    private RevenueCapService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);

        $tierResolver = $this->createMock(RevenueTierResolver::class);
        $tierResolver->method('getTier')->willReturn(RevenueTier::Merchant);

        $storeSettings = $this->createMock(StoreSettings::class);
        $storeSettings->method('getCurrency')->willReturn('EUR');

        $exchangeRates = $this->createMock(ExchangeRateRepository::class);
        $exchangeRates->method('findAllRates')->willReturn(['EUR' => '0.872600000000', 'USD' => '1.000000000000']);

        $this->service = new RevenueCapService(
            $this->orderRepository,
            $tierResolver,
            $storeSettings,
            new CurrencyConverter($exchangeRates, new NullLogger()),
        );
    }

    public function testRevenueAndCapAreConvertedToTheStoreCurrency(): void
    {
        $this->orderRepository
            ->method('sumRevenueSince')
            ->willReturn(['EUR' => 300000, 'USD' => 100000]);

        $usage = $this->service->getUsage();

        self::assertSame('EUR 4363.00', (string)$usage->cap);
        self::assertSame('EUR 1290.87', (string)$usage->getAverage());
        self::assertFalse($this->service->isBlocked(Money::of(1000, 'USD')));
        self::assertTrue($this->service->isBlocked(Money::of(20000, 'USD')));
    }

    public function testMonthlyRevenueFillsTheMonthsWithoutOrders(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $this->orderRepository
            ->method('sumRevenueByMonth')
            ->willReturn([$month->format('Y-m') => ['USD' => 100000]]);

        $series = $this->service->getMonthlyRevenue(3);

        self::assertCount(3, $series);
        self::assertSame(
            ['EUR 0.00', 'EUR 0.00', 'EUR 872.60'],
            array_map(static fn(Money $money) => (string)$money, array_values($series)),
        );
    }
}

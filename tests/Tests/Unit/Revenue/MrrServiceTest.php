<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Revenue\MrrService;
use Forumify\Ecommerce\Service\StoreSettings;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MrrServiceTest extends TestCase
{
    public function testSubscriptionsAreNormalisedToAMonthAndPastDueIsAtRisk(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('sumOpenByInterval')->willReturn([
            self::row(SubscriptionStatus::Active, 'USD', BillingInterval::Month, 1, 2, 2000),
            self::row(SubscriptionStatus::Active, 'USD', BillingInterval::Year, 1, 1, 12000),
            self::row(SubscriptionStatus::Active, 'USD', BillingInterval::Week, 1, 1, 1200),
            self::row(SubscriptionStatus::Active, 'EUR', BillingInterval::Month, 3, 1, 3000),
            self::row(SubscriptionStatus::PastDue, 'USD', BillingInterval::Month, 1, 1, 500),
        ]);

        $storeSettings = $this->createMock(StoreSettings::class);
        $storeSettings->method('getCurrency')->willReturn('USD');

        $exchangeRates = $this->createMock(ExchangeRateRepository::class);
        $exchangeRates->method('findAllRates')->willReturn(['EUR' => '0.872600000000']);

        $mrr = new MrrService(
            $repository,
            new CurrencyConverter($exchangeRates, new NullLogger()),
            $storeSettings,
        )->getMrr();

        self::assertSame('USD 98.46', (string)$mrr->total);
        self::assertSame('USD 5.00', (string)$mrr->atRisk);
        self::assertSame(6, $mrr->subscriptions);
        self::assertSame(1, $mrr->pastDue);
    }

    /**
     * @return array{
     *     status: SubscriptionStatus,
     *     currency: string,
     *     billingInterval: BillingInterval,
     *     intervalCount: int,
     *     count: int,
     *     total: int,
     * }
     */
    private static function row(
        SubscriptionStatus $status,
        string $currency,
        BillingInterval $interval,
        int $intervalCount,
        int $count,
        int $total,
    ): array {
        return [
            'status' => $status,
            'currency' => $currency,
            'billingInterval' => $interval,
            'intervalCount' => $intervalCount,
            'count' => $count,
            'total' => $total,
        ];
    }
}

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use DateTimeImmutable;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SubscriptionTest extends TestCase
{
    public function testRenewalsMoveThePeriodForwardAndRecoverFromPastDue(): void
    {
        $subscription = self::createSubscription(new DateTimeImmutable('2026-10-01'));

        self::assertFalse($subscription->renew(new DateTimeImmutable('2026-10-01')));
        self::assertTrue($subscription->renew(new DateTimeImmutable('2026-11-01')));
        self::assertEquals(new DateTimeImmutable('2026-11-01'), $subscription->currentPeriodEnd);

        self::assertTrue($subscription->markPastDue());
        self::assertFalse($subscription->markPastDue());
        self::assertTrue($subscription->renew(new DateTimeImmutable('2026-11-01')));
        self::assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function testCancellationCanBeUndoneUntilTheSubscriptionEnds(): void
    {
        $subscription = self::createSubscription(new DateTimeImmutable('2026-10-01'));

        self::assertTrue($subscription->cancel());
        self::assertFalse($subscription->cancel());
        self::assertFalse($subscription->markPastDue());
        self::assertTrue($subscription->resume());
        self::assertSame(SubscriptionStatus::Active, $subscription->status);

        self::assertTrue($subscription->end());
        self::assertFalse($subscription->end());
        self::assertNotNull($subscription->endedAt);
        self::assertFalse($subscription->renew(new DateTimeImmutable('2027-01-01')));
        self::assertFalse($subscription->resume());
        self::assertFalse($subscription->cancel());
        self::assertSame(SubscriptionStatus::Ended, $subscription->status);
        self::assertEquals(new DateTimeImmutable('2026-10-01'), $subscription->currentPeriodEnd);
    }

    public function testGraceEndsAtTheEndOfTheUnpaidPeriodOrAfterThirtyDays(): void
    {
        $monthly = self::createSubscription(new DateTimeImmutable('2026-10-01'));
        self::assertNull($monthly->graceEndsAt);
        self::assertTrue($monthly->markPastDue());
        self::setPastDueSince($monthly, new DateTimeImmutable('2026-10-15'));
        self::assertEquals(new DateTimeImmutable('2026-11-01'), $monthly->graceEndsAt);

        $yearly = self::createSubscription(new DateTimeImmutable('2026-10-01'), BillingInterval::Year);
        $yearly->markPastDue();
        self::setPastDueSince($yearly, new DateTimeImmutable('2026-10-01'));
        self::assertEquals(new DateTimeImmutable('2026-10-31'), $yearly->graceEndsAt);

        self::assertTrue($yearly->cancel());
        self::assertTrue($yearly->resume());
        self::assertSame(SubscriptionStatus::PastDue, $yearly->status);
        self::assertTrue($yearly->renew(new DateTimeImmutable('2027-10-01')));
        self::assertNull($yearly->pastDueSince);
        self::assertNull($yearly->graceEndsAt);
    }

    public function testOnlyRecurringProviderOrdersStartSubscriptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Subscription(new Order(new User(), self::createPrice(PriceType::OneTime), 'fake'), 'sub_1', new DateTimeImmutable());
    }

    private static function createSubscription(DateTimeImmutable $periodEnd, BillingInterval $interval = BillingInterval::Month): Subscription
    {
        return new Subscription(new Order(new User(), self::createPrice(PriceType::Recurring, $interval), 'fake'), 'sub_1', $periodEnd);
    }

    private static function setPastDueSince(Subscription $subscription, DateTimeImmutable $since): void
    {
        new ReflectionProperty(Subscription::class, 'pastDueSince')->setValue($subscription, $since);
    }

    private static function createPrice(PriceType $type, BillingInterval $interval = BillingInterval::Month): ProductPrice
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->amountMinor = 999;
        $price->type = $type;
        if ($type === PriceType::Recurring) {
            $price->billingInterval = $interval;
            $price->intervalCount = 1;
        }

        return $price;
    }
}

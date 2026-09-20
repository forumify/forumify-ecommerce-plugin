<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use DateTimeImmutable;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testOrderSnapshotsWhatWasBought(): void
    {
        $order = $this->createOrder();
        $order->providerReference = 'cs_123';

        self::assertSame(OrderStatus::Pending, $order->status);
        self::assertMatchesRegularExpression('/^[0-9A-F]{12}$/', $order->reference);
        self::assertSame(['Milsim', 'forumify/milsim', 'Premium', 'premium'], [$order->productTitle, $order->productKey, $order->tierLabel, $order->tierKey]);
        self::assertSame('EUR 9.99', (string)$order->getAmount());
        self::assertSame([PriceType::Recurring, BillingInterval::Month, 1], [$order->priceType, $order->billingInterval, $order->intervalCount]);
        self::assertFalse($order->isFree());
        self::assertNotSame($order->reference, $this->createOrder()->reference);
        self::assertEquals(new DateTimeImmutable('2026-02-28 12:00'), $order->periodEndFrom(new DateTimeImmutable('2026-01-31 12:00')));
        self::assertEquals(new DateTimeImmutable('2028-02-29'), BillingInterval::Year->addTo(new DateTimeImmutable('2024-02-29'), 4));
        self::assertEquals(new DateTimeImmutable('2026-01-15'), BillingInterval::Week->addTo(new DateTimeImmutable('2026-01-01'), 2));
    }

    public function testOnlyPendingOrdersFailOrCancelAndPaymentAlwaysWins(): void
    {
        $order = $this->createOrder();
        self::assertTrue($order->cancel());
        self::assertFalse($order->fail());
        self::assertSame(OrderStatus::Cancelled, $order->status);

        self::assertTrue($order->pay());
        self::assertNotNull($order->paidAt);
        self::assertFalse($order->pay());
        self::assertFalse($order->fail());
        self::assertFalse($order->cancel());
        self::assertSame(OrderStatus::Paid, $order->status);
    }

    public function testOnlyPaidOrdersComplete(): void
    {
        $order = $this->createOrder();
        $admin = new User();
        self::assertFalse($order->complete());
        self::assertSame(OrderStatus::Pending, $order->status);

        $order->pay();
        self::assertTrue($order->complete($admin, "  Tracking: https://track.test/1 \n"));
        self::assertFalse($order->complete());
        self::assertSame(OrderStatus::Completed, $order->status);
        self::assertNotNull($order->completedAt);
        self::assertSame($admin, $order->completedBy);
        self::assertSame('Tracking: https://track.test/1', $order->fulfillmentNote);
        self::assertFalse($order->pay());
    }

    public function testOnlyPaidOrCompletedOrdersRefund(): void
    {
        $order = $this->createOrder();
        self::assertFalse($order->refund());

        $order->pay();
        self::assertTrue($order->refund());
        self::assertFalse($order->refund());
        self::assertSame(OrderStatus::Refunded, $order->status);
        self::assertNotNull($order->refundedAt);
        self::assertFalse($order->complete());
        self::assertFalse($order->pay());
    }

    public function testTheRefundWindowStartsWhenTheBuyerHasTheirOrder(): void
    {
        self::assertFalse($this->createOrder()->isRefundable(), 'a pending order has nothing to refund');
        self::assertNull($this->createOrder()->getRefundDeadline());

        $order = $this->createOrder(refundPeriodDays: 14);
        $order->pay();
        self::assertTrue($order->isRefundable(), 'paid but never delivered stays refundable');
        self::assertNull($order->getRefundDeadline(), 'the window has not started yet');

        $order->complete();
        self::assertTrue($order->isRefundable());
        self::assertEquals(
            $order->completedAt?->modify('+14 days'),
            $order->getRefundDeadline(),
        );

        $withoutWindow = $this->createOrder();
        $withoutWindow->pay();
        $withoutWindow->complete();
        self::assertFalse($withoutWindow->isRefundable(), 'no refund period means no refunds');

        $free = $this->createOrder(amountMinor: 0, refundPeriodDays: 14);
        $free->pay();
        self::assertFalse($free->isRefundable(), 'there is no money to send back');
    }

    private function createOrder(int $amountMinor = 999, ?int $refundPeriodDays = null): Order
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'forumify/milsim';
        $product->refundPeriodDays = $refundPeriodDays;
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'EUR');
        $price->type = PriceType::Recurring;
        $price->billingInterval = BillingInterval::Month;
        $price->intervalCount = 1;
        $price->amountMinor = $amountMinor;

        return new Order(new User(), $price, 'fake');
    }
}

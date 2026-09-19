<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use DateTimeImmutable;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Event\OrderFailedEvent;
use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Event\SubscriptionCancelledEvent;
use Forumify\Ecommerce\Event\SubscriptionEndedEvent;
use Forumify\Ecommerce\Event\SubscriptionPastDueEvent;
use Forumify\Ecommerce\Event\SubscriptionRenewedEvent;
use Forumify\Ecommerce\Event\SubscriptionStartedEvent;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Fulfillment\Type\InstantFulfillmentType;
use Forumify\Ecommerce\Fulfillment\Type\ManualFulfillmentType;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionPaymentFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalStarted;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionUpdated;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentEventHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    /** @var list<Subscription> */
    private array $saved = [];

    /** @var list<Order> */
    private array $renewals = [];

    public function testPaymentIsIdempotent(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCompleted($order->reference), new CheckoutCompleted($order->reference)], 'fake');

        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertNotNull($order->paidAt);
        self::assertNull($order->completedAt);
        self::assertCount(1, $this->dispatched);
        self::assertInstanceOf(OrderPaidEvent::class, $this->dispatched[0]);
        self::assertSame($order, $this->dispatched[0]->order);
    }

    public function testFailedCheckoutFailsPendingOrderOnce(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutFailed($order->reference), new CheckoutFailed($order->reference)], 'fake');

        self::assertSame(OrderStatus::Failed, $order->status);
        self::assertCount(1, $this->dispatched);
        self::assertInstanceOf(OrderFailedEvent::class, $this->dispatched[0]);
    }

    public function testFailureNeverOverwritesPaidOrder(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCompleted($order->reference), new CheckoutFailed($order->reference), new CheckoutCancelled($order->reference)], 'fake');

        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertCount(1, $this->dispatched);
    }

    public function testCancelledOrderIsStillPaidWhenPaymentArrives(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCancelled($order->reference), new CheckoutCompleted($order->reference)], 'fake');

        self::assertSame(OrderStatus::Paid, $order->status);
        self::assertCount(1, $this->dispatched);
    }

    public function testUnknownOrderIsIgnored(): void
    {
        $handler = $this->createHandler($this->createOrder());

        $handler->handle(new CheckoutCompleted('UNKNOWN'), 'fake');
        self::assertSame([], $this->dispatched);
    }

    public function testEventFromAnotherProviderIsIgnored(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCompleted($order->reference), new CheckoutFailed($order->reference)], 'stripe');
        $handler->handle(new CheckoutCompleted($order->reference), null);

        self::assertSame(OrderStatus::Pending, $order->status);

        self::assertSame([], $this->dispatched);
    }

    public function testRecurringOrderStartsSubscriptionWhenPaid(): void
    {
        $order = $this->createOrder(recurring: true);
        $handler = $this->createHandler($order);

        $handler->handle(new CheckoutCompleted($order->reference, externalSubscriptionId: 'sub_1'), 'fake');
        $handler->handle(new CheckoutCompleted($order->reference, externalSubscriptionId: 'sub_1'), 'fake');

        self::assertSame([OrderPaidEvent::class, SubscriptionStartedEvent::class], array_map(get_class(...), $this->dispatched));
        self::assertCount(1, $this->saved);
        $subscription = $this->saved[0];
        self::assertSame($subscription, $this->dispatched[0]->subscription);
        self::assertSame([$order, 'fake', 'sub_1'], [$subscription->initialOrder, $subscription->providerKey, $subscription->providerReference]);
        self::assertEqualsWithDelta(new DateTimeImmutable('+1 month'), $subscription->currentPeriodEnd, 5);

        $withoutProviderSubscription = $this->createOrder(recurring: true);
        $this->createHandler($withoutProviderSubscription)->handle(new CheckoutCompleted($withoutProviderSubscription->reference), 'fake');
        self::assertSame(OrderStatus::Paid, $withoutProviderSubscription->status);
        self::assertCount(1, $this->saved);
    }

    public function testSubscriptionLifecycleDispatchesEachTransitionOnce(): void
    {
        $subscription = new Subscription($this->createOrder(recurring: true), 'sub_1', new DateTimeImmutable('2026-10-01'));
        $handler = $this->createHandler($subscription->initialOrder, $subscription);

        $handler->handleAll([
            new SubscriptionRenewalStarted('sub_1', 'in_1'),
            new SubscriptionRenewalStarted('sub_1', 'in_1'),
            new SubscriptionRenewed('sub_1', 'in_1', new DateTimeImmutable('2026-11-01')),
            new SubscriptionRenewed('sub_1', 'in_1', new DateTimeImmutable('2026-11-01')),
            new SubscriptionPaymentFailed('sub_1', 'in_2'),
            new SubscriptionPaymentFailed('sub_1', 'in_2'),
            new SubscriptionRenewed('sub_1', 'in_2', new DateTimeImmutable('2026-12-01')),
            new SubscriptionUpdated('sub_1', true),
            new SubscriptionUpdated('sub_1', true),
            new SubscriptionRenewalStarted('sub_1', 'in_3'),
            new SubscriptionEnded('sub_1'),
            new SubscriptionRenewalStarted('sub_1', 'in_4'),
            new SubscriptionEnded('sub_1'),
        ], 'fake');
        $handler->handle(new SubscriptionEnded('sub_1'), 'stripe');

        self::assertSame([
            SubscriptionRenewedEvent::class,
            SubscriptionPastDueEvent::class,
            SubscriptionRenewedEvent::class,
            SubscriptionCancelledEvent::class,
            SubscriptionEndedEvent::class,
        ], array_map(get_class(...), $this->dispatched));
        self::assertSame(SubscriptionStatus::Ended, $subscription->status);
        self::assertEquals(new DateTimeImmutable('2026-12-01'), $subscription->currentPeriodEnd);

        self::assertSame(['in_1', 'in_2', 'in_3'], array_map(static fn (Order $order) => $order->providerReference, $this->renewals));
        self::assertSame(
            [OrderStatus::Completed, OrderStatus::Completed, OrderStatus::Failed],
            array_map(static fn (Order $order) => $order->status, $this->renewals),
        );
        foreach ($this->renewals as $renewal) {
            self::assertSame($subscription, $renewal->subscription);
            self::assertSame([999, 'fake'], [$renewal->amountMinor, $renewal->providerKey]);
        }
        self::assertSame([$this->renewals[0], $this->renewals[1]], [$this->dispatched[0]->order, $this->dispatched[2]->order]);
    }

    public function testRenewalWithoutStartCreatesCompletedOrder(): void
    {
        $subscription = new Subscription($this->createOrder(recurring: true), 'sub_1', new DateTimeImmutable('2026-10-01'));
        $handler = $this->createHandler($subscription->initialOrder, $subscription);

        $handler->handle(new SubscriptionRenewed('sub_1', 'in_1', new DateTimeImmutable('2026-11-01')), 'fake');
        $handler->handle(new CheckoutFailed($this->renewals[0]->reference), 'fake');

        self::assertCount(1, $this->renewals);
        self::assertSame(OrderStatus::Completed, $this->renewals[0]->status);
        self::assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function testManualRenewalStaysPaidForAnAdminToFulfill(): void
    {
        $initial = $this->createOrder(recurring: true, fulfillmentType: ManualFulfillmentType::KEY, fulfillmentData: ['label' => 'Username', 'value' => 'jannes']);
        $initial->pay();
        $subscription = new Subscription($initial, 'sub_1', new DateTimeImmutable('2026-10-01'));
        $initial->product->fulfillmentType = InstantFulfillmentType::KEY;
        $handler = $this->createHandler($initial, $subscription);

        $handler->handle(new SubscriptionRenewed('sub_1', 'in_1', new DateTimeImmutable('2026-11-01')), 'fake');

        self::assertSame(OrderStatus::Paid, $this->renewals[0]->status);
        self::assertSame(ManualFulfillmentType::KEY, $this->renewals[0]->fulfillmentType);
        self::assertSame(['label' => 'Username', 'value' => 'jannes'], $this->renewals[0]->fulfillmentData);
        self::assertSame([SubscriptionRenewedEvent::class], array_map(get_class(...), $this->dispatched));
    }

    public function testResumedSubscriptionIsActiveAgain(): void
    {
        $subscription = new Subscription($this->createOrder(recurring: true), 'sub_1', new DateTimeImmutable('2026-10-01'));
        $handler = $this->createHandler($subscription->initialOrder, $subscription);

        $handler->handleAll([new SubscriptionUpdated('sub_1', true), new SubscriptionUpdated('sub_1', false)], 'fake');

        self::assertSame(SubscriptionStatus::Active, $subscription->status);
        self::assertSame([SubscriptionCancelledEvent::class], array_map(get_class(...), $this->dispatched));
    }

    private function createHandler(Order $order, ?Subscription $subscription = null): PaymentEventHandler
    {
        $repository = $this->createMock(OrderRepository::class);
        $repository->method('withLockedOrder')->willReturnCallback(function (string $reference, callable $callback) use ($order) {
            foreach ([$order, ...$this->renewals] as $candidate) {
                if ($candidate->reference === $reference) {
                    return $callback($candidate);
                }
            }

            return null;
        });
        $repository->method('findOneByProviderReference')->willReturnCallback(
            fn (string $providerKey, string $reference) => array_find(
                $this->renewals,
                static fn (Order $renewal) => $renewal->providerKey === $providerKey && $renewal->providerReference === $reference,
            ),
        );
        $repository->method('findPendingRenewals')->willReturnCallback(fn (Subscription $subscription) => array_values(array_filter(
            $this->renewals,
            static fn (Order $renewal) => $renewal->subscription === $subscription && $renewal->status === OrderStatus::Pending,
        )));
        $repository->method('save')->willReturnCallback(function (Order $renewal): void {
            $this->renewals[] = $renewal;
        });

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) {
            $this->dispatched[] = $event;

            return $event;
        });

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->method('withLockedSubscription')->willReturnCallback(
            static fn (string $providerKey, string $reference, callable $callback) => $subscription !== null
                && $providerKey === $subscription->providerKey
                && $reference === $subscription->providerReference ? $callback($subscription) : null,
        );
        $subscriptionRepository->method('save')->willReturnCallback(function (Subscription $saved): void {
            $this->saved[] = $saved;
        });

        $fulfillmentTypes = new FulfillmentTypeRegistry([new InstantFulfillmentType(), new ManualFulfillmentType()]);

        return new PaymentEventHandler($repository, $subscriptionRepository, $dispatcher, $fulfillmentTypes, new NullLogger());
    }

    /**
     * @param array<string, mixed> $fulfillmentData
     */
    private function createOrder(bool $recurring = false, string $fulfillmentType = InstantFulfillmentType::KEY, array $fulfillmentData = []): Order
    {
        $product = new Product();
        $product->fulfillmentType = $fulfillmentType;
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->amountMinor = 999;
        if ($recurring) {
            $price->type = PriceType::Recurring;
            $price->billingInterval = BillingInterval::Month;
            $price->intervalCount = 1;
        }

        return new Order(new User(), $price, 'fake', fulfillmentData: $fulfillmentData);
    }
}

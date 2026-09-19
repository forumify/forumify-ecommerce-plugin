<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Event\OrderCompletedEvent;
use Forumify\Ecommerce\Event\OrderFailedEvent;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentEventHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    public function testCompletionIsIdempotent(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCompleted($order->reference), new CheckoutCompleted($order->reference)], 'fake');

        self::assertSame(OrderStatus::Completed, $order->status);
        self::assertCount(1, $this->dispatched);
        self::assertInstanceOf(OrderCompletedEvent::class, $this->dispatched[0]);
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

    public function testFailureNeverOverwritesCompletedOrder(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCompleted($order->reference), new CheckoutFailed($order->reference), new CheckoutCancelled($order->reference)], 'fake');

        self::assertSame(OrderStatus::Completed, $order->status);
        self::assertCount(1, $this->dispatched);
    }

    public function testCancelledOrderStillCompletesWhenPaymentArrives(): void
    {
        $order = $this->createOrder();
        $handler = $this->createHandler($order);

        $handler->handleAll([new CheckoutCancelled($order->reference), new CheckoutCompleted($order->reference)], 'fake');

        self::assertSame(OrderStatus::Completed, $order->status);
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

    private function createHandler(Order $order): PaymentEventHandler
    {
        $repository = $this->createMock(OrderRepository::class);
        $repository->method('withLockedOrder')->willReturnCallback(
            static fn (string $reference, callable $callback) => $reference === $order->reference ? $callback($order) : null,
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) {
            $this->dispatched[] = $event;

            return $event;
        });

        return new PaymentEventHandler($repository, $dispatcher, new NullLogger());
    }

    private function createOrder(): Order
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->amountMinor = 999;

        return new Order(new User(), $price, 'fake');
    }
}

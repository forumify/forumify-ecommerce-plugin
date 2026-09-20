<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Event\OrderPlacedEvent;
use Forumify\Ecommerce\Exception\CheckoutException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\CheckoutService;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckoutServiceTest extends TestCase
{
    private PaymentProviderRegistry&MockObject $registry;
    private OrderRepository&MockObject $orderRepository;
    private PaymentEventHandler&MockObject $eventHandler;
    private EventDispatcher $dispatcher;
    private CheckoutService $service;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(PaymentProviderRegistry::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->eventHandler = $this->createMock(PaymentEventHandler::class);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn(string $route, array $parameters) => "https://shop.test/$route/{$parameters['reference']}",
        );

        $this->dispatcher = new EventDispatcher();
        $this->service = new CheckoutService($this->registry, $this->orderRepository, $this->eventHandler, $urlGenerator, $this->dispatcher, new NullLogger());
    }

    public function testFreePriceCompletesWithoutProvider(): void
    {
        $price = $this->createPrice(0);
        $this->registry->expects(self::never())->method('findAvailableFor');
        $this->orderRepository->expects(self::once())->method('save')->with(self::callback(
            static fn(Order $order) => $order->fulfillmentType === 'instant' && $order->fulfillmentData === ['note' => 'hi'],
        ));
        $this->eventHandler->expects(self::once())->method('handle')->with(self::callback(
            static fn(CheckoutCompleted $event) => strlen($event->orderReference) === 12,
        ), null);

        $redirect = $this->service->checkout(new User(), $price, fulfillmentData: ['note' => 'hi']);

        self::assertStringStartsWith('https://shop.test/ecommerce_order/', $redirect->url);
        self::assertTrue($price->locked);
    }

    public function testPaidPriceStartsCheckoutWithTheOnlyAvailableProvider(): void
    {
        $price = $this->createPrice(999);
        $provider = $this->createProvider('stripe');
        $provider->expects(self::once())->method('startCheckout')->willReturnCallback(
            function (Order $order, $urls) {
                self::assertSame("https://shop.test/ecommerce_order/{$order->reference}", $urls->successUrl);
                self::assertSame("https://shop.test/ecommerce_order_cancel/{$order->reference}", $urls->cancelUrl);

                return new CheckoutRedirect('https://pay.test/session', 'cs_1');
            },
        );
        $this->registry->method('findAvailableFor')->willReturn([$provider]);
        $savedOrders = [];
        $this->orderRepository->method('save')->willReturnCallback(function (Order $order) use (&$savedOrders) {
            $savedOrders[] = $order;
        });

        $redirect = $this->service->checkout(new User(), $price);

        self::assertSame('https://pay.test/session', $redirect->url);
        self::assertSame('cs_1', $savedOrders[0]->providerReference);
        self::assertSame('stripe', $savedOrders[0]->providerKey);
        self::assertTrue($price->locked);
    }

    public function testSeveralProvidersRequireAChoice(): void
    {
        $price = $this->createPrice(999);
        $stripe = $this->createProvider('stripe');
        $paypal = $this->createProvider('paypal');
        $paypal->method('startCheckout')->willReturn(new CheckoutRedirect('https://paypal.test'));
        $this->registry->method('findAvailableFor')->willReturn([$stripe, $paypal]);
        self::assertSame([$stripe, $paypal], $this->service->getProviderOptions($price));

        try {
            $this->service->checkout(new User(), $price);
            self::fail('A choice between providers should be required.');
        } catch (CheckoutException $e) {
            self::assertSame('ecommerce.checkout.error.invalid_provider', $e->getMessage());
        }

        self::assertFalse($price->locked);
        self::assertSame('https://paypal.test', $this->service->checkout(new User(), $price, 'paypal')->url);
    }

    public function testPaidPriceWithoutProviderCreatesNoOrder(): void
    {
        $this->registry->method('findAvailableFor')->willReturn([]);
        $this->orderRepository->expects(self::never())->method('save');

        $this->expectExceptionObject(CheckoutException::noProvider());
        $this->service->checkout(new User(), $this->createPrice(999));
    }

    public function testInactivePriceOrUnpublishedProductIsNotPurchasable(): void
    {
        $inactive = $this->createPrice(999);
        $inactive->active = false;
        $draft = $this->createPrice(999, ProductStatus::Draft);

        self::assertFalse($this->service->isPurchasable($inactive));
        self::assertFalse($this->service->isPurchasable($draft));
        $this->expectExceptionObject(CheckoutException::notPurchasable());
        $this->service->checkout(new User(), $draft);
    }

    public function testFailingProviderFailsTheOrder(): void
    {
        $provider = $this->createProvider('stripe');
        $provider->method('startCheckout')->willThrowException(new RuntimeException('API down'));
        $this->registry->method('findAvailableFor')->willReturn([$provider]);
        $this->eventHandler->expects(self::once())->method('handle')->with(self::isInstanceOf(CheckoutFailed::class), 'stripe');

        $this->expectExceptionObject(CheckoutException::providerFailed());
        $this->service->checkout(new User(), $this->createPrice(999));
    }

    public function testSyncOnlyAsksProviderAboutPendingOrders(): void
    {
        $provider = $this->createProvider('stripe');
        $events = [new CheckoutCompleted('REF')];
        $provider->expects(self::once())->method('syncOrder')->willReturn($events);
        $this->registry->method('findConfigured')->with('stripe')->willReturn($provider);
        $this->eventHandler->expects(self::once())->method('handleAll')->with($events, 'stripe');

        $pending = new Order(new User(), $this->createPrice(999), 'stripe');
        $paid = new Order(new User(), $this->createPrice(999), 'stripe');
        $paid->pay();

        $this->service->sync($paid);
        $this->service->sync($pending);
    }

    public function testOrderPlacedListenerCanAbortTheCheckout(): void
    {
        $provider = $this->createProvider('stripe');
        $provider->expects(self::never())->method('startCheckout');
        $this->registry->method('findAvailableFor')->willReturn([$provider]);
        $this->dispatcher->addListener(OrderPlacedEvent::class, static fn() => throw CheckoutException::notPurchasable());
        $savedOrders = [];
        $this->orderRepository->method('save')->willReturnCallback(function (Order $order) use (&$savedOrders) {
            $savedOrders[] = $order;
        });

        try {
            $this->service->checkout(new User(), $this->createPrice(999));
            self::fail('The checkout should have been aborted.');
        } catch (CheckoutException) {
        }

        self::assertSame(OrderStatus::Cancelled, $savedOrders[0]->status);
    }

    private function createProvider(string $key): PaymentProviderInterface&MockObject
    {
        $provider = $this->createMock(PaymentProviderInterface::class);
        $provider->method('getKey')->willReturn($key);

        return $provider;
    }

    private function createPrice(int $amountMinor, ProductStatus $status = ProductStatus::Published): ProductPrice
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $product->status = $status;
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->amountMinor = $amountMinor;

        return $price;
    }
}

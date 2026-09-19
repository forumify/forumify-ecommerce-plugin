<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

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
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use Forumify\Ecommerce\Service\SubscriptionCancellationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class SubscriptionCancellationServiceTest extends TestCase
{
    public function testProviderEventsAreAppliedUnderItsKey(): void
    {
        $subscription = $this->createSubscription(SubscriptionStatus::Active);
        $events = [new SubscriptionEnded('sub_1')];
        $provider = $this->createProvider();
        $provider->expects(self::once())->method('cancelSubscription')->with($subscription, false)->willReturn($events);
        $handler = $this->createMock(PaymentEventHandler::class);
        $handler->expects(self::once())->method('handleAll')->with($events, 'stripe');

        $this->createService($provider, $handler)->cancel($subscription, false);
    }

    public function testFailuresBecomeSubscriptionExceptions(): void
    {
        $handler = $this->createMock(PaymentEventHandler::class);
        $handler->expects(self::never())->method('handleAll');

        $failing = $this->createProvider();
        $failing->method('cancelSubscription')->willThrowException(new RuntimeException('API down'));

        $cases = [
            [$this->createService($failing, $handler), SubscriptionStatus::Active, SubscriptionException::providerFailed()],
            [$this->createService(null, $handler), SubscriptionStatus::Active, SubscriptionException::providerUnavailable()],
            [$this->createService($failing, $handler), SubscriptionStatus::Ended, SubscriptionException::alreadyEnded()],
        ];
        foreach ($cases as [$service, $status, $expected]) {
            try {
                $service->cancel($this->createSubscription($status), true);
                self::fail('Expected a ' . SubscriptionException::class);
            } catch (SubscriptionException $e) {
                self::assertSame($expected->getMessage(), $e->getMessage());
            }
        }
    }

    private function createService(?PaymentProviderInterface $provider, PaymentEventHandler $handler): SubscriptionCancellationService
    {
        $registry = $this->createMock(PaymentProviderRegistry::class);
        $registry->method('findConfigured')->with('stripe')->willReturn($provider);

        return new SubscriptionCancellationService($registry, $handler, new NullLogger());
    }

    private function createProvider(): PaymentProviderInterface
    {
        $provider = $this->createMock(PaymentProviderInterface::class);
        $provider->method('getKey')->willReturn('stripe');

        return $provider;
    }

    private function createSubscription(SubscriptionStatus $status): Subscription
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->amountMinor = 999;
        $price->type = PriceType::Recurring;
        $price->billingInterval = BillingInterval::Month;
        $price->intervalCount = 1;

        $subscription = new Subscription(new Order(new User(), $price, 'stripe'), 'sub_1', new DateTimeImmutable('+1 month'));
        if ($status === SubscriptionStatus::Ended) {
            $subscription->end();
        }

        return $subscription;
    }
}

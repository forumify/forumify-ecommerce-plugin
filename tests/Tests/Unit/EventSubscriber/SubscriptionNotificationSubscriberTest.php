<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\EventSubscriber;

use DateTimeImmutable;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\EventSubscriber\SubscriptionNotificationSubscriber;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Notification\SubscriptionCancelledNotificationType;
use Forumify\Ecommerce\Notification\SubscriptionEndedNotificationType;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SubscriptionNotificationSubscriberTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('cancellations')]
    public function testCancellationNotifiesOnce(bool $atPeriodEnd, array $expected): void
    {
        $subscription = $this->createSubscription();
        $sent = [];
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->method('sendNotification')->willReturnCallback(static function (Notification $notification) use (&$sent): void {
            $sent[] = $notification->getType();
        });

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->method('withLockedSubscription')->willReturnCallback(
            static fn (string $_, string $__, callable $callback) => $callback($subscription),
        );
        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('findPendingRenewals')->willReturn([]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SubscriptionNotificationSubscriber($notificationService));
        $handler = new PaymentEventHandler($orderRepository, $subscriptionRepository, $dispatcher, new FulfillmentTypeRegistry([]), new NullLogger());
        $provider = new FakePaymentProvider($this->createMock(UrlGeneratorInterface::class), $subscriptionRepository);

        $handler->handleAll($provider->cancelSubscription($subscription, $atPeriodEnd), FakePaymentProvider::KEY);

        self::assertSame($expected, $sent);
    }

    /**
     * @return iterable<string, array{bool, list<string>}>
     */
    public static function cancellations(): iterable
    {
        yield 'at period end' => [true, [SubscriptionCancelledNotificationType::TYPE]];
        yield 'immediately' => [false, [SubscriptionEndedNotificationType::TYPE]];
    }

    private function createSubscription(): Subscription
    {
        $product = new Product();
        $product->title = 'Milsim';
        $product->key = 'milsim';
        $tier = new ProductTier($product);
        $tier->key = 'premium';
        $tier->label = 'Premium';
        $price = new ProductPrice($tier, 'USD');
        $price->type = PriceType::Recurring;
        $price->billingInterval = BillingInterval::Month;
        $price->intervalCount = 1;

        return new Subscription(new Order(new User(), $price, FakePaymentProvider::KEY), 'sub_1', new DateTimeImmutable('+1 month'));
    }
}

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\EventSubscriber;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\EventSubscriber\InstantFulfillmentSubscriber;
use Forumify\Ecommerce\EventSubscriber\OrderNotificationSubscriber;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Fulfillment\Type\InstantFulfillmentType;
use Forumify\Ecommerce\Fulfillment\Type\PhysicalFulfillmentType;
use Forumify\Ecommerce\Notification\OrderPaidNotificationType;
use Forumify\Ecommerce\Notification\OrderToFulfillNotificationType;
use Forumify\Ecommerce\Service\FulfillmentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrderNotificationSubscriberTest extends TestCase
{
    /** @var list<string> */
    private array $sent = [];

    public function testInstantOrdersCompleteRightAwayWithoutPaymentReceivedNotification(): void
    {
        $order = $this->createPaidOrder(InstantFulfillmentType::KEY);
        $fulfillmentService = $this->createMock(FulfillmentService::class);
        $fulfillmentService->expects(self::once())->method('complete')->with($order)->willReturnCallback(
            static fn (Order $order) => $order->complete(),
        );

        $this->createDispatcher($fulfillmentService)->dispatch(new OrderPaidEvent($order));

        self::assertSame([], $this->sent);
    }

    public function testManualOrdersNotifyTheBuyerAndTheFulfillmentRole(): void
    {
        $fulfiller = new User();
        $role = new Role();
        $role->getUsers()->add($fulfiller);
        $order = $this->createPaidOrder(PhysicalFulfillmentType::KEY, $role);
        $fulfillmentService = $this->createMock(FulfillmentService::class);
        $fulfillmentService->expects(self::never())->method('complete');

        $this->createDispatcher($fulfillmentService)->dispatch(new OrderPaidEvent($order));

        self::assertSame([OrderPaidNotificationType::TYPE, OrderToFulfillNotificationType::TYPE], $this->sent);
    }

    private function createDispatcher(FulfillmentService $fulfillmentService): EventDispatcher
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->method('sendNotification')->willReturnCallback(function (Notification|array $notifications): void {
            foreach (is_array($notifications) ? $notifications : [$notifications] as $notification) {
                $this->sent[] = $notification->getType();
            }
        });

        $fulfillmentTypes = new FulfillmentTypeRegistry([new InstantFulfillmentType(), new PhysicalFulfillmentType()]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new InstantFulfillmentSubscriber($fulfillmentService));
        $dispatcher->addSubscriber(new OrderNotificationSubscriber($notificationService, $fulfillmentTypes, new NullLogger()));

        return $dispatcher;
    }

    private function createPaidOrder(string $fulfillmentType, ?Role $fulfillmentRole = null): Order
    {
        $product = new Product();
        $product->title = 'Hoodie';
        $product->key = 'hoodie';
        $product->fulfillmentType = $fulfillmentType;
        $product->fulfillmentRole = $fulfillmentRole;
        $tier = new ProductTier($product);
        $tier->key = 'default';
        $tier->label = 'Default';
        $order = new Order(new User(), new ProductPrice($tier, 'USD'), 'fake');
        $order->pay();

        return $order;
    }
}

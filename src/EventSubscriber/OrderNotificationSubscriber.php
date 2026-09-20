<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Event\OrderCompletedEvent;
use Forumify\Ecommerce\Event\OrderFailedEvent;
use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Event\OrderRefundedEvent;
use Forumify\Ecommerce\Event\SubscriptionRenewedEvent;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Notification\OrderCompletedNotificationType;
use Forumify\Ecommerce\Notification\OrderFailedNotificationType;
use Forumify\Ecommerce\Notification\OrderPaidNotificationType;
use Forumify\Ecommerce\Notification\OrderRefundedNotificationType;
use Forumify\Ecommerce\Notification\OrderToFulfillNotificationType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPaidEvent::class => ['onOrderPaid', -100],
            SubscriptionRenewedEvent::class => ['onSubscriptionRenewed', -100],
            OrderCompletedEvent::class => 'onOrderCompleted',
            OrderFailedEvent::class => 'onOrderFailed',
            OrderRefundedEvent::class => 'onOrderRefunded',
        ];
    }

    public function onOrderPaid(OrderPaidEvent $event): void
    {
        $order = $event->order;
        if ($order->status !== OrderStatus::Paid) {
            return;
        }

        $this->notifyBuyer($order, OrderPaidNotificationType::TYPE);
        $this->notifyFulfillers($order);
    }

    public function onSubscriptionRenewed(SubscriptionRenewedEvent $event): void
    {
        if ($event->order->status === OrderStatus::Paid) {
            $this->notifyFulfillers($event->order);
        }
    }

    public function onOrderCompleted(OrderCompletedEvent $event): void
    {
        $this->notifyBuyer($event->order, OrderCompletedNotificationType::TYPE);
    }

    public function onOrderFailed(OrderFailedEvent $event): void
    {
        $this->notifyBuyer($event->order, OrderFailedNotificationType::TYPE);
    }

    public function onOrderRefunded(OrderRefundedEvent $event): void
    {
        $this->notifyBuyer($event->order, OrderRefundedNotificationType::TYPE);
    }

    private function notifyBuyer(Order $order, string $type): void
    {
        if ($order->user !== null) {
            $this->notificationService->sendNotification(new Notification($type, $order->user, ['order' => $order]));
        }
    }

    private function notifyFulfillers(Order $order): void
    {
        if (!$this->fulfillmentTypes->requiresManualFulfillment($order->fulfillmentType)) {
            return;
        }

        $role = $order->product?->fulfillmentRole;
        if ($role === null) {
            $this->logger->warning('An order has to be fulfilled, but its product has no fulfillment role to notify.', [
                'order' => $order->reference,
            ]);
            return;
        }

        $notifications = [];
        foreach ($role->getUsers() as $user) {
            $notifications[] = new Notification(OrderToFulfillNotificationType::TYPE, $user, ['order' => $order]);
        }

        if ($notifications !== []) {
            $this->notificationService->sendNotification($notifications);
        }
    }
}

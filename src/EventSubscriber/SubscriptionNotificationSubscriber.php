<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Event\SubscriptionCancelledEvent;
use Forumify\Ecommerce\Event\SubscriptionEndedEvent;
use Forumify\Ecommerce\Event\SubscriptionEvent;
use Forumify\Ecommerce\Event\SubscriptionPastDueEvent;
use Forumify\Ecommerce\Notification\SubscriptionCancelledNotificationType;
use Forumify\Ecommerce\Notification\SubscriptionEndedNotificationType;
use Forumify\Ecommerce\Notification\SubscriptionPastDueNotificationType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SubscriptionNotificationSubscriber implements EventSubscriberInterface
{
    private const TYPES = [
        SubscriptionPastDueEvent::class => SubscriptionPastDueNotificationType::TYPE,
        SubscriptionCancelledEvent::class => SubscriptionCancelledNotificationType::TYPE,
        SubscriptionEndedEvent::class => SubscriptionEndedNotificationType::TYPE,
    ];

    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return array_fill_keys(array_keys(self::TYPES), 'notify');
    }

    public function notify(SubscriptionEvent $event): void
    {
        $subscription = $event->subscription;
        $type = self::TYPES[$event::class] ?? null;
        if ($type === null || $subscription->user === null) {
            return;
        }

        $this->notificationService->sendNotification(new Notification($type, $subscription->user, ['subscription' => $subscription]));
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Ecommerce\Event\SubscriptionCancelledEvent;
use Forumify\Ecommerce\Event\SubscriptionEndedEvent;
use Forumify\Ecommerce\Event\SubscriptionEvent;
use Forumify\Ecommerce\Service\CancellationRequestService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CancellationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CancellationRequestService $cancellationRequests)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SubscriptionCancelledEvent::class => 'onCancelled',
            SubscriptionEndedEvent::class => 'onCancelled',
        ];
    }

    public function onCancelled(SubscriptionEvent $event): void
    {
        $this->cancellationRequests->open($event->subscription);
    }
}

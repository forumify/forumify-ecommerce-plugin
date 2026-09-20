<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Event\OrderRefundedEvent;
use Forumify\Ecommerce\Event\SubscriptionEndedEvent;
use Forumify\Ecommerce\Service\GrantService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GrantSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GrantService $grantService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPaidEvent::class => 'onOrderPaid',
            OrderRefundedEvent::class => 'onOrderRefunded',
            SubscriptionEndedEvent::class => 'onSubscriptionEnded',
        ];
    }

    public function onOrderPaid(OrderPaidEvent $event): void
    {
        $this->grantService->grant($event->order, $event->subscription);
    }

    public function onOrderRefunded(OrderRefundedEvent $event): void
    {
        $this->grantService->revokeForOrder($event->order);
    }

    public function onSubscriptionEnded(SubscriptionEndedEvent $event): void
    {
        $this->grantService->revoke($event->subscription);
    }
}

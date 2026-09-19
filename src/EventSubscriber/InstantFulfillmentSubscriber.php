<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Fulfillment\Type\InstantFulfillmentType;
use Forumify\Ecommerce\Service\FulfillmentService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InstantFulfillmentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly FulfillmentService $fulfillmentService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [OrderPaidEvent::class => 'onOrderPaid'];
    }

    public function onOrderPaid(OrderPaidEvent $event): void
    {
        if ($event->order->fulfillmentType === InstantFulfillmentType::KEY) {
            $this->fulfillmentService->complete($event->order);
        }
    }
}

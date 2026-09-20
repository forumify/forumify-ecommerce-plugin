<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Event\OrderCompletedEvent;
use Forumify\Ecommerce\Repository\OrderRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FulfillmentService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function complete(Order $order, ?User $by = null, ?string $note = null): bool
    {
        $completed = $this->orderRepository->withLockedOrder(
            $order->reference,
            static fn(Order $locked) => $locked->complete($by, $note),
        ) ?? false;

        if ($completed) {
            $this->eventDispatcher->dispatch(new OrderCompletedEvent($order));
        }

        return $completed;
    }
}

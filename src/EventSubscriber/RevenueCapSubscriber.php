<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\EventSubscriber;

use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Event\OrderPlacedEvent;
use Forumify\Ecommerce\Revenue\RevenueCapNotifier;
use Forumify\Ecommerce\Revenue\RevenueCapService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class RevenueCapSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RevenueCapService $revenueCapService,
        private readonly RevenueCapNotifier $revenueCapNotifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlacedEvent::class => ['onOrder', -100],
            OrderPaidEvent::class => ['onOrder', -100],
        ];
    }

    /**
     * A listener on OrderPlacedEvent runs inside the checkout, where anything thrown cancels the order.
     * Warning the store about its revenue is never worth losing a sale over.
     */
    public function onOrder(): void
    {
        try {
            $this->revenueCapNotifier->notifyIfNearCap($this->revenueCapService->getUsage());
        } catch (Throwable $e) {
            $this->logger->error('Checking the revenue cap after an order failed.', ['exception' => $e]);
        }
    }
}

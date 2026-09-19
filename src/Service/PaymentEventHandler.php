<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Event\OrderCompletedEvent;
use Forumify\Ecommerce\Event\OrderFailedEvent;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\Repository\OrderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentEventHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param iterable<PaymentEvent> $events
     */
    public function handleAll(iterable $events, ?string $providerKey): void
    {
        foreach ($events as $event) {
            $this->handle($event, $providerKey);
        }
    }

    public function handle(PaymentEvent $event, ?string $providerKey): void
    {
        $orderEvent = match (true) {
            $event instanceof CheckoutCompleted => $this->apply(
                $event->orderReference,
                $providerKey,
                static fn (Order $order) => $order->complete() ? new OrderCompletedEvent($order) : null,
            ),
            $event instanceof CheckoutFailed => $this->apply(
                $event->orderReference,
                $providerKey,
                static fn (Order $order) => $order->fail() ? new OrderFailedEvent($order) : null,
            ),
            $event instanceof CheckoutCancelled => $this->apply(
                $event->orderReference,
                $providerKey,
                static function (Order $order): null {
                    $order->cancel();

                    return null;
                },
            ),
            default => null,
        };

        if ($orderEvent !== null) {
            $this->eventDispatcher->dispatch($orderEvent);
        }
    }

    /**
     * @param callable(Order): ?Event $transition
     */
    private function apply(string $reference, ?string $providerKey, callable $transition): ?Event
    {
        return $this->orderRepository->withLockedOrder($reference, function (Order $order) use ($providerKey, $transition): ?Event {
            if ($order->providerKey !== $providerKey) {
                $this->logger->warning('Ignored a payment event for an order that belongs to another payment provider.', [
                    'order' => $order->reference,
                    'orderProvider' => $order->providerKey,
                    'eventProvider' => $providerKey,
                ]);
                return null;
            }

            return $transition($order);
        });
    }
}

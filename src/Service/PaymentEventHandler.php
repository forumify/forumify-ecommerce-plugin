<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use DateTimeImmutable;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Event\OrderFailedEvent;
use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\Event\SubscriptionCancelledEvent;
use Forumify\Ecommerce\Event\SubscriptionEndedEvent;
use Forumify\Ecommerce\Event\SubscriptionPastDueEvent;
use Forumify\Ecommerce\Event\SubscriptionRenewedEvent;
use Forumify\Ecommerce\Event\SubscriptionStartedEvent;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionPaymentFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalStarted;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionUpdated;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentEventHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
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
        $domainEvents = match (true) {
            $event instanceof CheckoutCompleted => $this->applyToOrder(
                $event->orderReference,
                $providerKey,
                fn(Order $order) => $this->payOrder($order, $event),
            ),
            $event instanceof CheckoutFailed => $this->applyToOrder(
                $event->orderReference,
                $providerKey,
                static fn(Order $order) => $order->fail() ? [new OrderFailedEvent($order)] : [],
            ),
            $event instanceof CheckoutCancelled => $this->applyToOrder(
                $event->orderReference,
                $providerKey,
                static function (Order $order): array {
                    $order->cancel();

                    return [];
                },
            ),
            $event instanceof SubscriptionRenewalStarted => $this->applyToSubscription(
                $event->externalSubscriptionId,
                $providerKey,
                fn(Subscription $subscription) => $this->startRenewal($subscription, $event),
            ),
            $event instanceof SubscriptionRenewed => $this->applyToSubscription(
                $event->externalSubscriptionId,
                $providerKey,
                fn(Subscription $subscription) => $this->renewSubscription($subscription, $event),
            ),
            $event instanceof SubscriptionPaymentFailed => $this->applyToSubscription(
                $event->externalSubscriptionId,
                $providerKey,
                fn(Subscription $subscription) => $this->failRenewal($subscription, $event),
            ),
            $event instanceof SubscriptionUpdated => $this->applyToSubscription(
                $event->externalSubscriptionId,
                $providerKey,
                static fn(Subscription $subscription) => self::updateSubscription($subscription, $event),
            ),
            $event instanceof SubscriptionEnded => $this->applyToSubscription(
                $event->externalSubscriptionId,
                $providerKey,
                fn(Subscription $subscription) => $this->endSubscription($subscription),
            ),
            default => [],
        };

        foreach ($domainEvents as $domainEvent) {
            $this->eventDispatcher->dispatch($domainEvent);
        }
    }

    /**
     * @param callable(Order): list<Event> $transition
     * @return list<Event>
     */
    private function applyToOrder(string $reference, ?string $providerKey, callable $transition): array
    {
        $apply = function (Order $order) use ($providerKey, $transition): array {
            if ($order->isRenewal()) {
                return [];
            }

            if ($order->providerKey !== $providerKey) {
                $this->logger->warning('Ignored a payment event for an order of another payment provider.', [
                    'order' => $order->reference,
                    'orderProvider' => $order->providerKey,
                    'eventProvider' => $providerKey,
                ]);
                return [];
            }

            return $transition($order);
        };

        return $this->orderRepository->withLockedOrder($reference, $apply) ?? [];
    }

    /**
     * @param callable(Subscription): list<Event> $transition
     * @return list<Event>
     */
    private function applyToSubscription(string $externalId, ?string $providerKey, callable $transition): array
    {
        if ($providerKey === null) {
            return [];
        }

        return $this->subscriptionRepository->withLockedSubscription($providerKey, $externalId, $transition) ?? [];
    }

    /**
     * @return list<Event>
     */
    private function payOrder(Order $order, CheckoutCompleted $event): array
    {
        if (!$order->pay()) {
            return [];
        }

        $subscription = $this->startSubscription($order, $event);
        $events = [new OrderPaidEvent($order, $subscription)];
        if ($subscription !== null) {
            $events[] = new SubscriptionStartedEvent($subscription);
        }

        return $events;
    }

    private function startSubscription(Order $order, CheckoutCompleted $event): ?Subscription
    {
        if ($order->priceType !== PriceType::Recurring || $order->providerKey === null) {
            return null;
        }

        if ($event->externalSubscriptionId === null) {
            $this->logger->error('A recurring order was completed without a subscription from its payment provider.', [
                'order' => $order->reference,
                'provider' => $order->providerKey,
            ]);
            return null;
        }

        $periodEnd = $event->periodEnd ?? $order->periodEndFrom(new DateTimeImmutable());
        $subscription = new Subscription($order, $event->externalSubscriptionId, $periodEnd);
        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    /**
     * @return list<Event>
     */
    private function startRenewal(Subscription $subscription, SubscriptionRenewalStarted $event): array
    {
        if ($subscription->status !== SubscriptionStatus::Ended) {
            $this->findOrCreateRenewal($subscription, $event->externalPaymentId);
        }

        return [];
    }

    /**
     * @return list<Event>
     */
    private function renewSubscription(Subscription $subscription, SubscriptionRenewed $event): array
    {
        $order = $this->findOrCreateRenewal($subscription, $event->externalPaymentId);
        if ($order->pay() && !$this->fulfillmentTypes->requiresManualFulfillment($order->fulfillmentType)) {
            $order->complete();
        }

        return $subscription->renew($event->periodEnd) ? [new SubscriptionRenewedEvent($subscription, $order)] : [];
    }

    /**
     * @return list<Event>
     */
    private function failRenewal(Subscription $subscription, SubscriptionPaymentFailed $event): array
    {
        if ($event->externalPaymentId !== null && $subscription->status !== SubscriptionStatus::Ended) {
            $this->findOrCreateRenewal($subscription, $event->externalPaymentId);
        }

        return $subscription->markPastDue() ? [new SubscriptionPastDueEvent($subscription)] : [];
    }

    /**
     * @return list<Event>
     */
    private function endSubscription(Subscription $subscription): array
    {
        if (!$subscription->end()) {
            return [];
        }

        foreach ($this->orderRepository->findPendingRenewals($subscription) as $order) {
            $order->fail();
        }

        return [new SubscriptionEndedEvent($subscription)];
    }

    private function findOrCreateRenewal(Subscription $subscription, string $externalPaymentId): Order
    {
        $order = $this->orderRepository->findOneByProviderReference($subscription->providerKey, $externalPaymentId);
        if ($order !== null) {
            return $order;
        }

        $price = $subscription->price ?? throw new LogicException('A subscription without a price can not be renewed.');
        $order = new Order($subscription->user, $price, $subscription->providerKey, $subscription);
        $order->providerReference = $externalPaymentId;
        $this->orderRepository->save($order);

        return $order;
    }

    /**
     * @return list<Event>
     */
    private static function updateSubscription(Subscription $subscription, SubscriptionUpdated $event): array
    {
        if (!$event->cancelAtPeriodEnd) {
            $subscription->resume();
            return [];
        }

        return $subscription->cancel() ? [new SubscriptionCancelledEvent($subscription)] : [];
    }
}

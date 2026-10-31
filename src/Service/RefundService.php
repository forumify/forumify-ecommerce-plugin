<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\RefundRequestStatus;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Exception\RefundException;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\Notification\RefundRejectedNotificationType;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderCapability;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\RefundRequestRepository;
use Psr\Log\LoggerInterface;
use Throwable;

class RefundService
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly PaymentEventHandler $eventHandler,
        private readonly RefundRequestRepository $refundRequestRepository,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function canRefund(Order $order): bool
    {
        if ($order->providerKey === null || $order->isFree()) {
            return false;
        }

        if ($order->status !== OrderStatus::Paid && $order->status !== OrderStatus::Completed) {
            return false;
        }

        return $this->registry->findCapableFor($order, PaymentProviderCapability::Refund) !== null;
    }

    /**
     * The subscription that refunding this order ends: the one it started or renewed, while it still runs.
     */
    public function findSubscriptionToEnd(Order $order): ?Subscription
    {
        $subscription = $order->subscription;

        return $subscription !== null && $subscription->status !== SubscriptionStatus::Ended ? $subscription : null;
    }

    /**
     * Refunding any payment of a subscription ends that subscription right away: it is what takes its grants back,
     * and a buyer who got their money back must not be charged again. It ends before the money goes back, so a
     * failure never leaves a refunded buyer on a subscription that keeps billing.
     *
     * @throws RefundException
     */
    public function refund(Order $order): void
    {
        if (!$this->canRefund($order)) {
            throw RefundException::notRefundable();
        }

        $provider = $this->registry->findCapableFor($order, PaymentProviderCapability::Refund)
            ?? throw RefundException::providerUnavailable();

        $subscription = $this->findSubscriptionToEnd($order);
        if ($subscription !== null) {
            try {
                $this->cancellationService->cancel($subscription, atPeriodEnd: false);
            } catch (SubscriptionException) {
                throw RefundException::subscriptionNotEnded();
            }
        }

        try {
            $events = $provider->refund($order);
        } catch (Throwable $e) {
            $this->logger->error('Refunding an order with its payment provider failed.', [
                'order' => $order->reference,
                'provider' => $provider->getKey(),
                'exception' => $e,
            ]);
            throw RefundException::providerFailed();
        }

        $this->eventHandler->handleAll($events, $provider->getKey());
    }

    /**
     * @throws RefundException
     */
    public function request(Order $order, ?User $user, string $reason): RefundRequest
    {
        if (!$order->isRefundable() || !$this->canRefund($order)) {
            throw RefundException::notRefundable();
        }

        if ($order->refundRequest !== null) {
            throw RefundException::alreadyRequested();
        }

        $request = new RefundRequest($order, $user, $reason);
        $this->refundRequestRepository->save($request);

        return $request;
    }

    /**
     * @throws RefundException
     */
    public function approve(RefundRequest $request, ?User $admin, ?string $note): void
    {
        $this->refund($request->order);

        if ($request->handle(RefundRequestStatus::Approved, $admin, $note)) {
            $this->refundRequestRepository->save($request);
        }
    }

    public function reject(RefundRequest $request, ?User $admin, ?string $note): bool
    {
        if (!$request->handle(RefundRequestStatus::Rejected, $admin, $note)) {
            return false;
        }

        $this->refundRequestRepository->save($request);

        $user = $request->user;
        if ($user !== null) {
            $this->notificationService->sendNotification(new Notification(
                RefundRejectedNotificationType::TYPE,
                $user,
                ['order' => $request->order],
            ));
        }

        return true;
    }
}

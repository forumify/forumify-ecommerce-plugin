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
use Forumify\Ecommerce\Exception\RefundException;
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
     * @throws RefundException
     */
    public function refund(Order $order): void
    {
        if (!$this->canRefund($order)) {
            throw RefundException::notRefundable();
        }

        $provider = $this->registry->findCapableFor($order, PaymentProviderCapability::Refund)
            ?? throw RefundException::providerUnavailable();

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

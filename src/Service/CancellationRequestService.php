<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use DateTimeImmutable;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\Ecommerce\Entity\CancellationRequest;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Notification\CancellationToHandleNotificationType;
use Forumify\Ecommerce\Repository\CancellationRequestRepository;
use Psr\Log\LoggerInterface;

/**
 * A subscription of a manually fulfilled product needs an admin to close it out, the same way it needed one
 * to deliver it. Cancelling tells the payment provider right away; this is the work that is left.
 */
class CancellationRequestService
{
    public function __construct(
        private readonly CancellationRequestRepository $repository,
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function open(Subscription $subscription): ?CancellationRequest
    {
        if (!$this->fulfillmentTypes->requiresManualFulfillment($subscription->initialOrder->fulfillmentType)) {
            return null;
        }

        $request = $subscription->requestCancellation();
        if ($request === null) {
            return null;
        }

        $this->repository->save($request);
        $this->notify($subscription);

        return $request;
    }

    public function complete(CancellationRequest $request, ?User $by): bool
    {
        if (!$request->complete($by)) {
            return false;
        }

        $this->repository->save($request);

        return true;
    }

    /**
     * Reminds the fulfillers of cancellations they still have not handled when the paid period runs out.
     */
    public function remindPending(): int
    {
        $requests = $this->repository->findDueReminders();
        foreach ($requests as $request) {
            $this->notify($request->subscription);
            $request->remindedAt = new DateTimeImmutable();
        }

        $this->repository->saveAll($requests);

        return count($requests);
    }

    private function notify(Subscription $subscription): void
    {
        $role = $subscription->initialOrder->product?->fulfillmentRole;
        if ($role === null) {
            $this->logger->warning('A subscription cancellation needs handling, but its product has no role.', [
                'subscription' => $subscription->getId(),
            ]);

            return;
        }

        $notifications = [];
        foreach ($role->getUsers() as $user) {
            $notifications[] = new Notification(
                CancellationToHandleNotificationType::TYPE,
                $user,
                ['subscription' => $subscription],
            );
        }

        if ($notifications !== []) {
            $this->notificationService->sendNotification($notifications);
        }
    }
}

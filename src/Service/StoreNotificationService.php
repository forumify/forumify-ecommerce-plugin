<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\NotificationService;
use Psr\Log\LoggerInterface;

/**
 * Sends a notification to whoever runs the store, as configured in the store settings.
 */
class StoreNotificationService
{
    public function __construct(
        private readonly StoreSettings $storeSettings,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return bool whether anyone was notified
     */
    public function notifyStoreManagers(string $type, array $context = []): bool
    {
        $role = $this->storeSettings->getStoreRole();
        if ($role === null) {
            $this->logger->warning('The store has something to report, but no store role is configured.', [
                'notification' => $type,
            ]);
            return false;
        }

        $notifications = [];
        foreach ($role->getUsers() as $user) {
            $notifications[] = new Notification($type, $user, $context);
        }

        if ($notifications === []) {
            return false;
        }

        $this->notificationService->sendNotification($notifications);

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Notification\RevenueCapReachedNotificationType;
use Forumify\Ecommerce\Notification\RevenueCapWarningNotificationType;
use Forumify\Ecommerce\Service\StoreNotificationService;

/**
 * Tells the store role that the revenue cap is in the way, at most once a day per kind: a blocked
 * checkout repeats as long as the store is over its cap, and so does a store sitting near it.
 */
class RevenueCapNotifier
{
    public const THROTTLE_HOURS = 24;
    private const NOTIFIED_AT = 'ecommerce.revenue_cap.notified.';

    public function __construct(
        private readonly StoreNotificationService $storeNotifications,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function notifyBlocked(RevenueUsage $usage): void
    {
        $this->notify(RevenueCapReachedNotificationType::TYPE, $usage);
    }

    public function notifyIfNearCap(RevenueUsage $usage): void
    {
        if (!$usage->isNearCap()) {
            return;
        }

        $this->notify(RevenueCapWarningNotificationType::TYPE, $usage);
    }

    private function notify(string $type, RevenueUsage $usage): void
    {
        $key = self::NOTIFIED_AT . $type;
        $lastNotifiedAt = $this->settingRepository->get($key);
        $now = time();

        if (is_int($lastNotifiedAt) && $now - $lastNotifiedAt < self::THROTTLE_HOURS * 3600) {
            return;
        }

        if ($this->storeNotifications->notifyStoreManagers($type, $this->getContext($usage))) {
            $this->settingRepository->set($key, $now);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getContext(RevenueUsage $usage): array
    {
        $average = $usage->getAverage();

        return [
            'tier' => $usage->tier->value,
            'currency' => $average->getCurrency()->getCurrencyCode(),
            'averageMinor' => $average->getMinorAmount()->toInt(),
            'capMinor' => $usage->cap?->getMinorAmount()->toInt(),
            'percentage' => $usage->getPercentage(),
        ];
    }
}

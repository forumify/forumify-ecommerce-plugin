<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Notification\RevenueCapWarningNotificationType;
use Forumify\Ecommerce\Revenue\RevenueCapNotifier;
use Forumify\Ecommerce\Revenue\RevenueTier;
use Forumify\Ecommerce\Revenue\RevenueUsage;
use Forumify\Ecommerce\Service\StoreNotificationService;
use PHPUnit\Framework\TestCase;

class RevenueCapNotifierTest extends TestCase
{
    public function testAStoreBelowTheWarningThresholdIsLeftAlone(): void
    {
        $storeNotifications = $this->createMock(StoreNotificationService::class);
        $storeNotifications->expects(self::never())->method('notifyStoreManagers');

        $notifier = new RevenueCapNotifier($storeNotifications, $this->createMock(SettingRepository::class));
        $notifier->notifyIfNearCap($this->createUsage('600.00'));
    }

    public function testTheSameWarningIsNotRepeatedWithinADay(): void
    {
        $usage = $this->createUsage('700.00');

        $storeNotifications = $this->createMock(StoreNotificationService::class);
        $storeNotifications
            ->expects(self::once())
            ->method('notifyStoreManagers')
            ->with(RevenueCapWarningNotificationType::TYPE, [
                'tier' => 'hobbyist',
                'currency' => 'USD',
                'averageMinor' => 23333,
                'capMinor' => 25000,
                'percentage' => 93,
            ])
            ->willReturn(true);

        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('get')->willReturnOnConsecutiveCalls(null, time());
        $settingRepository->expects(self::once())->method('set');

        $notifier = new RevenueCapNotifier($storeNotifications, $settingRepository);
        $notifier->notifyIfNearCap($usage);
        $notifier->notifyIfNearCap($usage);
    }

    private function createUsage(string $revenue): RevenueUsage
    {
        return new RevenueUsage(
            RevenueTier::Hobbyist,
            RationalMoney::of($revenue, 'USD'),
            3,
            Money::of(250, 'USD'),
        );
    }
}

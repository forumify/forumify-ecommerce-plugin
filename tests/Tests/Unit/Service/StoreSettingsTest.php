<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Forumify\Core\Repository\RoleRepository;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Service\StoreSettings;
use PHPUnit\Framework\TestCase;

class StoreSettingsTest extends TestCase
{
    public function testCurrencyFallsBackToDefaultUntilConfigured(): void
    {
        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository
            ->method('get')
            ->with(StoreSettings::CURRENCY)
            ->willReturnOnConsecutiveCalls(null, '', 'EUR');

        $storeSettings = new StoreSettings($settingRepository, $this->createMock(RoleRepository::class));

        self::assertSame(StoreSettings::DEFAULT_CURRENCY, $storeSettings->getCurrency());
        self::assertSame(StoreSettings::DEFAULT_CURRENCY, $storeSettings->getCurrency());
        self::assertSame('EUR', $storeSettings->getCurrency());
    }
}

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Forumify\Ecommerce\Revenue\RevenueTier;
use Forumify\Ecommerce\Revenue\RevenueUsage;
use PHPUnit\Framework\TestCase;

class RevenueUsageTest extends TestCase
{
    public function testAnOrderIsAllowedUntilTheAverageGoesOverTheCap(): void
    {
        $usage = $this->createUsage('600.00');

        self::assertSame('USD 200.00', (string)$usage->getAverage());
        self::assertSame(80, $usage->getPercentage());
        self::assertFalse($usage->isNearCap());
        self::assertFalse($usage->isOverCap());
        self::assertTrue($usage->allows(Money::of('150.00', 'USD')));
        self::assertFalse($usage->allows(Money::of('150.03', 'USD')));
    }

    public function testAStoreOverItsCapIsNearItAndOverIt(): void
    {
        $usage = $this->createUsage('900.00');

        self::assertSame('USD 300.00', (string)$usage->getAverage());
        self::assertSame(120, $usage->getPercentage());
        self::assertTrue($usage->isNearCap());
        self::assertTrue($usage->isOverCap());
        self::assertFalse($usage->allows(Money::of('0.00', 'USD')));
    }

    public function testTheWarningStartsAt90Percent(): void
    {
        self::assertFalse($this->createUsage('674.00')->isNearCap());
        self::assertTrue($this->createUsage('675.00')->isNearCap());
    }

    public function testAnUncappedTierAllowsEverything(): void
    {
        $usage = new RevenueUsage(RevenueTier::Marketplace, RationalMoney::of('90000.00', 'USD'), 3, null);

        self::assertSame('USD 30000.00', (string)$usage->getAverage());
        self::assertSame(0, $usage->getPercentage());
        self::assertFalse($usage->isNearCap());
        self::assertFalse($usage->isOverCap());
        self::assertTrue($usage->allows(Money::of('1000000.00', 'USD')));
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

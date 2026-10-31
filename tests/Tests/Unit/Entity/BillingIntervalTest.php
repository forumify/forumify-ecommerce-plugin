<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use DateTimeImmutable;
use Forumify\Ecommerce\Entity\BillingInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BillingIntervalTest extends TestCase
{
    /**
     * @return iterable<array{BillingInterval, int, string, string}>
     */
    public static function intervals(): iterable
    {
        yield 'day' => [BillingInterval::Day, 3, '2026-10-31 14:30:00', '2026-11-03 14:30:00'];
        yield 'week' => [BillingInterval::Week, 2, '2026-10-31 14:30:00', '2026-11-14 14:30:00'];
        yield 'month' => [BillingInterval::Month, 1, '2026-10-15 14:30:00', '2026-11-15 14:30:00'];
        yield 'month clamps to november' => [BillingInterval::Month, 1, '2026-10-31 14:30:00', '2026-11-30 14:30:00'];
        yield 'month clamps to february' => [BillingInterval::Month, 1, '2027-01-31 14:30:00', '2027-02-28 14:30:00'];
        yield 'months keep the day' => [BillingInterval::Month, 2, '2027-01-31 14:30:00', '2027-03-31 14:30:00'];
        yield 'year clamps a leap day' => [BillingInterval::Year, 1, '2028-02-29 14:30:00', '2029-02-28 14:30:00'];
    }

    #[DataProvider('intervals')]
    public function testAddTo(BillingInterval $interval, int $count, string $from, string $expected): void
    {
        self::assertEquals(new DateTimeImmutable($expected), $interval->addTo(new DateTimeImmutable($from), $count));
    }
}

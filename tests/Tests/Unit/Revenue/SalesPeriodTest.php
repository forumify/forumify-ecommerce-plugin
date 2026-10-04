<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use DateTimeImmutable;
use Forumify\Ecommerce\Revenue\SalesPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalesPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{SalesPeriod, string, list<string>, list<string>}>
     */
    public static function periods(): iterable
    {
        yield 'this month, compared to the same days before' => [
            SalesPeriod::ThisMonth,
            '2026-03-10 15:00',
            ['2026-03-01 00:00', '2026-04-01 00:00'],
            ['2026-02-01 00:00', '2026-02-10 15:00'],
        ];
        yield 'this month, compared to at most last month' => [
            SalesPeriod::ThisMonth,
            '2026-03-31 15:00',
            ['2026-03-01 00:00', '2026-04-01 00:00'],
            ['2026-02-01 00:00', '2026-03-01 00:00'],
        ];
        yield 'last month is complete' => [
            SalesPeriod::LastMonth,
            '2026-03-31 15:00',
            ['2026-02-01 00:00', '2026-03-01 00:00'],
            ['2026-01-01 00:00', '2026-02-01 00:00'],
        ];
        yield 'last 3 months' => [
            SalesPeriod::Last3Months,
            '2026-03-31 15:00',
            ['2025-12-01 00:00', '2026-03-01 00:00'],
            ['2025-09-01 00:00', '2025-12-01 00:00'],
        ];
    }

    /**
     * @param list<string> $range
     * @param list<string> $previous
     */
    #[DataProvider('periods')]
    public function testRanges(SalesPeriod $period, string $now, array $range, array $previous): void
    {
        $now = new DateTimeImmutable($now);

        self::assertSame($range, self::format($period->getRange($now)));
        self::assertSame($previous, self::format($period->getPreviousRange($now)));
    }

    public function testAllTimeHasNoRange(): void
    {
        self::assertNull(SalesPeriod::AllTime->getRange(new DateTimeImmutable()));
        self::assertNull(SalesPeriod::AllTime->getPreviousRange(new DateTimeImmutable()));
    }

    /**
     * @param array{DateTimeImmutable, DateTimeImmutable}|null $range
     * @return list<string>
     */
    private static function format(?array $range): array
    {
        return array_map(static fn(DateTimeImmutable $date) => $date->format('Y-m-d H:i'), $range ?? []);
    }
}

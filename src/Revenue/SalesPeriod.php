<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use DateTimeImmutable;

/**
 * A stretch of calendar months to report sales over. Apart from the current month, every period is made of
 * complete months, so it compares fairly against the one before it.
 */
enum SalesPeriod: string
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Last3Months = 'last_3_months';
    case Last12Months = 'last_12_months';
    case AllTime = 'all_time';

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}|null [from, to), or null for all time
     */
    public function getRange(DateTimeImmutable $now): ?array
    {
        $month = $now->modify('first day of this month midnight');

        return match ($this) {
            self::ThisMonth => [$month, $month->modify('+1 month')],
            self::AllTime => null,
            default => [$month->modify("-{$this->getMonths()} months"), $month],
        };
    }

    /**
     * The period of the same length right before this one. For the current month, that is the same number of
     * days of the month before, at most all of it.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}|null
     */
    public function getPreviousRange(DateTimeImmutable $now): ?array
    {
        $range = $this->getRange($now);
        if ($range === null) {
            return null;
        }

        $from = $range[0];
        $previousFrom = $from->modify("-{$this->getMonths()} months");

        return [$previousFrom, $this === self::ThisMonth ? min($previousFrom->add($from->diff($now)), $from) : $from];
    }

    private function getMonths(): int
    {
        return match ($this) {
            self::ThisMonth, self::LastMonth, self::AllTime => 1,
            self::Last3Months => 3,
            self::Last12Months => 12,
        };
    }
}

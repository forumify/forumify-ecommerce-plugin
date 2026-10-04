<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Brick\Math\BigRational;
use DateTimeImmutable;

enum BillingInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function addTo(DateTimeImmutable $date, int $count): DateTimeImmutable
    {
        if ($this === self::Day || $this === self::Week) {
            return $date->modify("+{$count} {$this->value}");
        }

        $months = $this === self::Year ? 12 * $count : $count;
        $target = $date->modify('first day of this month')->modify("+{$months} months");
        $day = min((int)$date->format('j'), (int)$target->format('t'));

        return $target->setDate((int)$target->format('Y'), (int)$target->format('n'), $day);
    }

    /**
     * How often a charge every $count of this interval comes around in an average month.
     */
    public function getTimesPerMonth(int $count): BigRational
    {
        $perYear = match ($this) {
            self::Day => 365,
            self::Week => 52,
            self::Month => 12,
            self::Year => 1,
        };

        return BigRational::of($perYear)->dividedBy(12 * $count);
    }
}

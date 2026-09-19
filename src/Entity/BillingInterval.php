<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

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
}

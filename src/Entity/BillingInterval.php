<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum BillingInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}

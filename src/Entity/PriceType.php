<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum PriceType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}

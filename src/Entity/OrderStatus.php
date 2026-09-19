<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

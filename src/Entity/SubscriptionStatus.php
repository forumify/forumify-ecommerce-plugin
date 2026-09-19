<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}

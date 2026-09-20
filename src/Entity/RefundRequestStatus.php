<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum RefundRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

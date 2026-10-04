<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Money\Money;
use DateTimeImmutable;
use Forumify\Core\Entity\User;

class TopCustomer
{
    public function __construct(
        public readonly User $user,
        public readonly int $orders,
        public readonly Money $revenue,
        public readonly int $subscriptions,
        public readonly DateTimeImmutable $customerSince,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Event;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Symfony\Contracts\EventDispatcher\Event;

class OrderPaidEvent extends Event
{
    public function __construct(
        public readonly Order $order,
        public readonly ?Subscription $subscription = null,
    ) {
    }
}

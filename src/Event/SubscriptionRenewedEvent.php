<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Event;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;

class SubscriptionRenewedEvent extends SubscriptionEvent
{
    public function __construct(Subscription $subscription, public readonly Order $order)
    {
        parent::__construct($subscription);
    }
}

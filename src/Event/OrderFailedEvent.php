<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Event;

use Forumify\Ecommerce\Entity\Order;
use Symfony\Contracts\EventDispatcher\Event;

class OrderFailedEvent extends Event
{
    public function __construct(public readonly Order $order)
    {
    }
}

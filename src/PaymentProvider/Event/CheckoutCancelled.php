<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

class CheckoutCancelled implements PaymentEvent
{
    public function __construct(public readonly string $orderReference)
    {
    }
}

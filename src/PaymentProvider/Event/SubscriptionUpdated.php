<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

class SubscriptionUpdated implements PaymentEvent
{
    public function __construct(
        public readonly string $externalSubscriptionId,
        public readonly bool $cancelAtPeriodEnd,
    ) {
    }
}

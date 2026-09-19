<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

use DateTimeImmutable;

class CheckoutCompleted implements PaymentEvent
{
    public function __construct(
        public readonly string $orderReference,
        public readonly ?string $externalCustomerId = null,
        public readonly ?string $externalSubscriptionId = null,
        public readonly ?DateTimeImmutable $periodEnd = null,
    ) {
    }
}

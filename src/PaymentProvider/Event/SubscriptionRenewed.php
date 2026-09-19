<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

use DateTimeImmutable;

class SubscriptionRenewed implements PaymentEvent
{
    public function __construct(
        public readonly string $externalSubscriptionId,
        public readonly string $externalPaymentId,
        public readonly DateTimeImmutable $periodEnd,
    ) {
    }
}

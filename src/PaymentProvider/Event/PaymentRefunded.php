<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

/**
 * Money went back to the buyer, whatever the reason: a refund we asked for, one an admin made in the
 * provider's own dashboard, or a chargeback. Identify the order by its reference, or by the provider's
 * payment id for a renewal.
 */
class PaymentRefunded implements PaymentEvent
{
    public function __construct(
        public readonly ?string $orderReference = null,
        public readonly ?string $externalPaymentId = null,
        public readonly ?int $amountMinor = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Event;

/**
 * The provider gave up on collecting a renewal (voided it, or wrote it off as uncollectible). Its pending order
 * fails. If the money arrives after all, SubscriptionRenewed still pays it.
 */
class SubscriptionRenewalVoided implements PaymentEvent
{
    public function __construct(
        public readonly string $externalSubscriptionId,
        public readonly string $externalPaymentId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

class CheckoutUrls
{
    public function __construct(
        public readonly string $successUrl,
        public readonly string $cancelUrl,
    ) {
    }
}

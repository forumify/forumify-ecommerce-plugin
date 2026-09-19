<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

class CheckoutRedirect
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $providerReference = null,
    ) {
    }
}

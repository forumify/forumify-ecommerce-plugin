<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use Stripe\StripeClient;

class StripeClientFactory
{
    public function create(StripeSettings $settings): StripeClient
    {
        return new StripeClient([
            'api_key' => $settings->secretKey,
            'max_network_retries' => 2,
        ]);
    }
}

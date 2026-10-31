<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

use Brick\Money\Money;

class Settlement
{
    public function __construct(
        public readonly Money $gross,
        public readonly Money $net,
    ) {
    }

    public function getFee(): Money
    {
        return $this->gross->minus($this->net);
    }
}

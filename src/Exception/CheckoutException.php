<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Exception;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public static function notPurchasable(): self
    {
        return new self('ecommerce.checkout.error.not_purchasable');
    }

    public static function noProvider(): self
    {
        return new self('ecommerce.checkout.error.no_provider');
    }

    public static function invalidProvider(): self
    {
        return new self('ecommerce.checkout.error.invalid_provider');
    }

    public static function providerFailed(): self
    {
        return new self('ecommerce.checkout.error.provider_failed');
    }
}

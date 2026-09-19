<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Exception;

use RuntimeException;

class SubscriptionException extends RuntimeException
{
    public static function alreadyEnded(): self
    {
        return new self('ecommerce.admin.subscription.error.already_ended');
    }

    public static function providerUnavailable(): self
    {
        return new self('ecommerce.admin.subscription.error.provider_unavailable');
    }

    public static function providerFailed(): self
    {
        return new self('ecommerce.admin.subscription.error.provider_failed');
    }
}

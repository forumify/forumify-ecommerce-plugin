<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Exception;

use RuntimeException;

class RefundException extends RuntimeException
{
    public static function notRefundable(): self
    {
        return new self('ecommerce.refund.error.not_refundable');
    }

    public static function providerUnavailable(): self
    {
        return new self('ecommerce.refund.error.provider_unavailable');
    }

    public static function providerFailed(): self
    {
        return new self('ecommerce.refund.error.provider_failed');
    }

    public static function subscriptionNotEnded(): self
    {
        return new self('ecommerce.refund.error.subscription_not_ended');
    }

    public static function alreadyRequested(): self
    {
        return new self('ecommerce.refund.error.already_requested');
    }
}

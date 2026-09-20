<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

class RefundRejectedNotificationType extends AbstractOrderNotificationType
{
    public const TYPE = 'ecommerce_refund_rejected';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.refund_rejected';
    }
}

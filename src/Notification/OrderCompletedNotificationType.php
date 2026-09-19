<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

class OrderCompletedNotificationType extends AbstractOrderNotificationType
{
    public const TYPE = 'ecommerce_order_completed';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.order_completed';
    }
}

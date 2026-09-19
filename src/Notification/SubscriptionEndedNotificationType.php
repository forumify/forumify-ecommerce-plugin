<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

class SubscriptionEndedNotificationType extends AbstractSubscriptionNotificationType
{
    public const TYPE = 'ecommerce_subscription_ended';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.subscription_ended';
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use DateTimeImmutable;
use Forumify\Ecommerce\Entity\Subscription;

class SubscriptionPastDueNotificationType extends AbstractSubscriptionNotificationType
{
    public const TYPE = 'ecommerce_subscription_past_due';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.subscription_past_due';
    }

    protected function getDate(Subscription $subscription): ?DateTimeImmutable
    {
        return $subscription->getGraceEndsAt();
    }
}

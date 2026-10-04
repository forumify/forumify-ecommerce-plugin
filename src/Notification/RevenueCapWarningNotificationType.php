<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

class RevenueCapWarningNotificationType extends AbstractRevenueCapNotificationType
{
    public const TYPE = 'ecommerce_revenue_cap_warning';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.revenue_cap_warning';
    }
}

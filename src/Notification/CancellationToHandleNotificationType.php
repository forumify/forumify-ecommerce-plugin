<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Forumify\Core\Entity\Notification;

class CancellationToHandleNotificationType extends AbstractSubscriptionNotificationType
{
    public const TYPE = 'ecommerce_cancellation_to_handle';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.cancellation_to_handle';
    }

    public function getUrl(Notification $notification): string
    {
        $subscription = $this->getSubscription($notification);
        if ($subscription === null) {
            return '';
        }

        return $this->urlGenerator->generate('ecommerce_admin_subscriptions_view', ['id' => $subscription->getId()]);
    }

    public function getEmailActionLabel(): string
    {
        return 'ecommerce.notification.handle_cancellation';
    }
}

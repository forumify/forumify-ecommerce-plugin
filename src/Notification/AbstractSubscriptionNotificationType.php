<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use DateTimeImmutable;
use Forumify\Core\Entity\Notification;
use Forumify\Ecommerce\Entity\Subscription;

abstract class AbstractSubscriptionNotificationType extends AbstractEcommerceNotificationType
{
    public function isSubjectValid(Notification $notification): bool
    {
        return $this->getSubscription($notification) !== null;
    }

    public function getImage(Notification $notification): string
    {
        return $this->getProductImage($this->getSubscription($notification)?->initialOrder->product);
    }

    public function getUrl(Notification $notification): string
    {
        return $this->urlGenerator->generate('ecommerce_orders');
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyEcommercePlugin/emails/notifications/subscription.html.twig';
    }

    public function getEmailActionLabel(): string
    {
        return 'ecommerce.notification.view_orders';
    }

    protected function getTranslationParameters(Notification $notification): array
    {
        $subscription = $this->getSubscription($notification);

        return [
            'product' => $subscription->initialOrder->productTitle ?? '',
            'tier' => $subscription->initialOrder->tierLabel ?? '',
            'date' => ($subscription === null ? null : $this->getDate($subscription)) ?? new DateTimeImmutable(),
        ];
    }

    protected function getDate(Subscription $subscription): ?DateTimeImmutable
    {
        return $subscription->currentPeriodEnd;
    }

    protected function getSubscription(Notification $notification): ?Subscription
    {
        $subscription = $notification->getDeserializedContext()['subscription'] ?? null;

        return $subscription instanceof Subscription ? $subscription : null;
    }
}

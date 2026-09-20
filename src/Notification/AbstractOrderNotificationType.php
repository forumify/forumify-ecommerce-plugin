<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Forumify\Core\Entity\Notification;
use Forumify\Ecommerce\Entity\Order;

abstract class AbstractOrderNotificationType extends AbstractEcommerceNotificationType
{
    public function isSubjectValid(Notification $notification): bool
    {
        return $this->getOrder($notification) !== null;
    }

    public function getImage(Notification $notification): string
    {
        return $this->getProductImage($this->getOrder($notification)?->product);
    }

    public function getUrl(Notification $notification): string
    {
        $order = $this->getOrder($notification);
        if ($order === null) {
            return '';
        }

        return $this->urlGenerator->generate('ecommerce_order', ['reference' => $order->reference]);
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyEcommercePlugin/emails/notifications/order.html.twig';
    }

    public function getEmailActionLabel(): string
    {
        return 'ecommerce.notification.view_order';
    }

    protected function getTranslationParameters(Notification $notification): array
    {
        $order = $this->getOrder($notification);

        return [
            'reference' => $order->reference ?? '',
            'product' => $order->productTitle ?? '',
            'tier' => $order->tierLabel ?? '',
        ];
    }

    protected function getOrder(Notification $notification): ?Order
    {
        $order = $notification->getDeserializedContext()['order'] ?? null;

        return $order instanceof Order ? $order : null;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Forumify\Core\Entity\Notification;

class OrderToFulfillNotificationType extends AbstractOrderNotificationType
{
    public const TYPE = 'ecommerce_order_to_fulfill';

    public function getType(): string
    {
        return self::TYPE;
    }

    protected function getTranslationKey(): string
    {
        return 'ecommerce.notification.order_to_fulfill';
    }

    public function getUrl(Notification $notification): string
    {
        $order = $this->getOrder($notification);
        if ($order === null) {
            return '';
        }

        return $this->urlGenerator->generate('ecommerce_admin_orders_view', ['id' => $order->getId()]);
    }

    public function getEmailActionLabel(): string
    {
        return 'ecommerce.notification.fulfill_order';
    }
}

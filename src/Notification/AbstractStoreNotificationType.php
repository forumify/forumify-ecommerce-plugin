<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Forumify\Core\Entity\Notification;

/**
 * A notification about the store itself rather than about one order, sent to the store role.
 */
abstract class AbstractStoreNotificationType extends AbstractEcommerceNotificationType
{
    public function getImage(Notification $notification): string
    {
        return $this->getProductImage(null);
    }

    public function getEmailTemplate(Notification $notification): string
    {
        return '@ForumifyEcommercePlugin/emails/notifications/store.html.twig';
    }
}

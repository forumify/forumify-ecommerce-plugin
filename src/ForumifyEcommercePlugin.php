<?php

declare(strict_types=1);

namespace Forumify\Ecommerce;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

class ForumifyEcommercePlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Ecommerce',
            'forumify',
            'Bring e-commerce to your forumify powered community!',
            'https://forumify.net',
            'ecommerce_admin_configuration',
        );
    }

    public function getPermissions(): array
    {
        return [
            'admin' => [
                'view',
                'configuration' => ['manage'],
                'payment_providers' => ['manage'],
                'categories' => ['view', 'manage'],
                'products' => ['view', 'manage'],
                'orders' => ['view'],
                'subscriptions' => ['view', 'manage'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

/**
 * The entry point forumify uses to recognise this package as a plugin.
 *
 * Override getPermissions() to declare permissions your plugin checks, for example:
 *
 *     public function getPermissions(): array
 *     {
 *         return ['admin' => ['example' => ['view', 'manage']]];
 *     }
 *
 * They are then checked as "<slugged-plugin-name>.admin.example.view".
 */
class ForumifyEcommercePlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Forumify Ecommerce',
            'forumify',
            'Bring e-commerce to your forumify powered community!',
            'https://forumify.net',
        );
    }
}

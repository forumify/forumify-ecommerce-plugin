<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Forumify\Core\Repository\PluginRepository;
use Forumify\Ecommerce\ForumifyEcommercePlugin;

class RevenueTierResolver
{
    private ?RevenueTier $tier = null;

    public function __construct(private readonly PluginRepository $pluginRepository)
    {
    }

    public function getTier(): RevenueTier
    {
        if ($this->tier !== null) {
            return $this->tier;
        }

        $plugin = $this->pluginRepository->findOneBy([
            'package' => ForumifyEcommercePlugin::PACKAGE,
            'active' => true,
        ]);

        $this->tier = RevenueTier::tryFrom($plugin?->getSubscriptionVersion() ?? '') ?? RevenueTier::Hobbyist;

        return $this->tier;
    }
}

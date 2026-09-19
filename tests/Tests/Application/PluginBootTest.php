<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\ForumifyEcommercePlugin;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the plugin is installed, registered and boots inside a real forumify
 * application. If this ever fails, something is wrong with the plugin's wiring rather
 * than with your own code.
 *
 * Add your own tests alongside it: anything that needs forumify running belongs in
 * tests/Tests/Application, anything that doesn't belongs in tests/Tests/Unit.
 */
class PluginBootTest extends KernelTestCase
{
    public function testPluginIsRegistered(): void
    {
        self::bootKernel();

        $bundles = self::$kernel?->getBundles() ?? [];

        self::assertArrayHasKey('ForumifyEcommercePlugin', $bundles);
        self::assertInstanceOf(ForumifyEcommercePlugin::class, $bundles['ForumifyEcommercePlugin']);
    }
}

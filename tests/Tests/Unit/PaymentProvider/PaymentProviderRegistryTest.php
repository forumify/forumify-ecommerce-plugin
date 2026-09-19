<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\PaymentProvider;

use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use PHPUnit\Framework\TestCase;

class PaymentProviderRegistryTest extends TestCase
{
    public function testOnlyEnabledConfiguredAndSupportingProvidersAreOffered(): void
    {
        $enabled = $this->createProvider('enabled');
        $disabled = $this->createProvider('disabled');
        $unconfigured = $this->createProvider('unconfigured', configured: false);
        $unsupporting = $this->createProvider('unsupporting', supports: false);
        $unknown = $this->createProvider('without_config');

        $registry = $this->createRegistry(
            [$enabled, $disabled, $unconfigured, $unsupporting, $unknown],
            ['enabled' => true, 'disabled' => false, 'unconfigured' => true, 'unsupporting' => true],
        );

        $price = new ProductPrice(new ProductTier(new Product()), 'USD');
        self::assertSame([$enabled], $registry->findAvailableFor($price));
    }

    public function testDisabledProvidersStillHandleWebhooksAndSyncs(): void
    {
        $disabled = $this->createProvider('disabled');
        $unconfigured = $this->createProvider('unconfigured', configured: false);
        $registry = $this->createRegistry([$disabled, $unconfigured], ['disabled' => false, 'unconfigured' => true]);

        self::assertSame($disabled, $registry->findConfigured('disabled'));
        self::assertNull($registry->findConfigured('unconfigured'));
        self::assertNull($registry->findConfigured('missing'));
        self::assertFalse($registry->getConfig($disabled)->enabled);
        self::assertSame('missing', (new PaymentProviderConfig('missing'))->providerKey);
    }

    /**
     * @param list<PaymentProviderInterface> $providers
     * @param array<string, bool> $enabled
     */
    private function createRegistry(array $providers, array $enabled): PaymentProviderRegistry
    {
        $configs = [];
        foreach ($enabled as $key => $isEnabled) {
            $configs[$key] = new PaymentProviderConfig($key);
            $configs[$key]->enabled = $isEnabled;
        }

        $repository = $this->createMock(PaymentProviderConfigRepository::class);
        $repository->method('findAllByKey')->willReturn($configs);

        return new PaymentProviderRegistry($providers, $repository);
    }

    private function createProvider(string $key, bool $configured = true, bool $supports = true): PaymentProviderInterface
    {
        $provider = $this->createMock(PaymentProviderInterface::class);
        $provider->method('getKey')->willReturn($key);
        $provider->method('isConfigured')->willReturn($configured);
        $provider->method('supports')->willReturn($supports);

        return $provider;
    }
}

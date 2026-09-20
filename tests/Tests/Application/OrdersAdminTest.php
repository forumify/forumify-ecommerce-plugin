<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class OrdersAdminTest extends EcommerceWebTestCase
{
    use InteractsWithLiveComponents;

    public function testAdminEnablesPaymentProvider(): void
    {
        $crawler = $this->client->request('GET', '/admin/ecommerce/payment-providers');
        self::assertSelectorTextContains('tbody', 'Fake payments');
        self::assertSelectorTextContains('tbody', 'Disabled');
        self::assertSelectorTextContains('tbody', 'Configured');

        $this->client->click($crawler->filter('a[aria-label="Edit Fake payments (development only)"]')->link());
        self::assertSelectorTextContains('.alert-info code', '/store/webhook/fake');

        $this->client->submitForm('Save', ['payment_provider_config[enabled]' => '1']);

        self::assertSelectorTextContains('tbody', 'Enabled');
        $config = self::getContainer()->get(PaymentProviderConfigRepository::class)->findOneBy(['providerKey' => 'fake']);
        self::assertInstanceOf(PaymentProviderConfig::class, $config);
        self::assertTrue($config->enabled);
    }

    public function testAdminFiltersOrdersByStatusAndProduct(): void
    {
        $milsim = ProductFactory::createOne(['title' => 'Milsim']);
        $theme = ProductFactory::createOne(['title' => 'Dark Theme']);
        $milsimPrice = ProductPriceFactory::createOne(['tier' => ProductTierFactory::createOne(['product' => $milsim])]);
        $themePrice = ProductPriceFactory::createOne(['tier' => ProductTierFactory::createOne(['product' => $theme])]);
        OrderFactory::createOne(['price' => $milsimPrice]);
        OrderFactory::new()->completed()->create(['price' => $milsimPrice]);
        OrderFactory::new()->completed()->create(['price' => $themePrice]);

        $titles = static fn($component) => $component->render()->crawler()->filter('tbody tr td:nth-child(2)')->each(fn($td) => $td->text());

        $table = $this->createLiveComponent('Ecommerce\\OrderTable', [], $this->client);
        self::assertCount(3, $titles($table));

        $table->set('statusFilter', 'completed');
        self::assertCount(2, $titles($table));

        $table->set('productFilter', (string)$milsim->getId());
        self::assertSame(['Milsim - ' . $milsimPrice->tier->label], $titles($table));
    }

    public function testOrderedProductCannotBeDeleted(): void
    {
        $ordered = ProductFactory::createOne();
        ProductPriceFactory::new()->locked()->create(['tier' => ProductTierFactory::createOne(['product' => $ordered])]);
        $unordered = ProductFactory::createOne();

        $this->client->request('GET', "/admin/ecommerce/products/{$ordered->getId()}/delete?confirmed=1");
        self::assertSelectorTextContains('body', 'This product has been ordered');
        self::assertNotNull(self::getContainer()->get(ProductRepository::class)->find($ordered->getId()));

        $this->client->request('GET', "/admin/ecommerce/products/{$unordered->getId()}/delete?confirmed=1");
        self::assertNull(self::getContainer()->get(ProductRepository::class)->find($unordered->getId()));
    }
}

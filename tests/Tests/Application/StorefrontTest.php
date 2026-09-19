<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\ProductStatus;
use PluginTests\Tests\Factories\ProductCategoryFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductImageFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;

class StorefrontTest extends EcommerceWebTestCase
{
    public function testCustomerBrowsesStore(): void
    {
        $plugins = ProductCategoryFactory::createOne(['title' => 'Plugins']);
        $themes = ProductCategoryFactory::createOne(['title' => 'Themes', 'description' => 'Give your forum a new look.']);
        $milsim = ProductFactory::createOne([
            'title' => 'Milsim',
            'shortDescription' => 'Run your unit.',
            'image' => 'cover.png',
            'categories' => new ArrayCollection([$plugins]),
        ]);
        ProductImageFactory::createOne(['product' => $milsim, 'image' => 'screenshot.png']);
        ProductFactory::createOne(['title' => 'Dark Theme', 'categories' => new ArrayCollection([$themes])]);
        $draft = ProductFactory::createOne([
            'title' => 'Secret Plugin',
            'status' => ProductStatus::Draft,
            'categories' => new ArrayCollection([$plugins]),
        ]);

        $free = ProductTierFactory::createOne(['product' => $milsim, 'label' => 'Free']);
        ProductPriceFactory::createOne(['tier' => $free, 'amountMinor' => 0]);
        $premium = ProductTierFactory::createOne(['product' => $milsim, 'label' => 'Premium', 'highlighted' => true]);
        ProductPriceFactory::new()->recurring()->create(['tier' => $premium, 'amountMinor' => 999]);
        ProductPriceFactory::new()->recurring(BillingInterval::Year)->create(['tier' => $premium, 'amountMinor' => 9900]);
        ProductPriceFactory::createOne(['tier' => $premium, 'amountMinor' => 500, 'active' => false]);

        $crawler = $this->client->request('GET', '/store');
        self::assertResponseIsSuccessful();
        self::assertSame(['All', 'Plugins', 'Themes'], $crawler->filter('.ecommerce-category-filter button')->each(fn ($b) => $b->text()));
        self::assertSame(['Milsim', 'Dark Theme'], $crawler->filter('.ecommerce-product-tile-title')->each(fn ($t) => $t->text()));
        self::assertSelectorTextContains('.ecommerce-product-tile-price', 'Free');

        $crawler = $this->client->request('GET', '/store?category=themes');
        self::assertSame(['Dark Theme'], $crawler->filter('.ecommerce-product-tile-title')->each(fn ($t) => $t->text()));
        self::assertSelectorTextContains('.ecommerce-category-description', 'Give your forum a new look.');
        self::assertSame('Give your forum a new look.', $crawler->filter('meta[name="description"]')->attr('content'));

        $crawler = $this->client->request('GET', '/store?q=your+unit');
        self::assertSame(['Milsim'], $crawler->filter('.ecommerce-product-tile-title')->each(fn ($t) => $t->text()));

        $this->client->request('GET', '/store?q=secret');
        self::assertSelectorTextContains('.ecommerce-store-empty', 'No products match "secret".');

        $crawler = $this->client->request('GET', "/store/{$milsim->getSlug()}");
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Milsim');
        self::assertCount(2, $crawler->filter('.ecommerce-gallery-thumbs button'));
        self::assertSame('Run your unit.', $crawler->filter('meta[property="og:description"]')->attr('content'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        self::assertStringEndsWith("/store/{$milsim->getSlug()}", $crawler->filter('link[rel="canonical"]')->attr('href'));

        $structuredData = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true);
        self::assertSame('Product', $structuredData['@type']);
        self::assertSame(['0.00', '9.99', '99.00'], array_column($structuredData['offers'], 'price'));
        self::assertSame('ANN', $structuredData['offers'][2]['priceSpecification']['unitCode']);
        self::assertSame(['Free', 'Premium'], $crawler->filter('.ecommerce-tier h3')->each(fn ($h) => $h->text()));
        self::assertSelectorTextContains('.ecommerce-tier.is-highlighted', 'Recommended');
        self::assertSame(
            ['$9.99 per month', '$99.00 per year'],
            $crawler->filter('.ecommerce-price-option')->each(fn ($o) => $o->text()),
        );

        $this->client->request('GET', "/store/{$draft->getSlug()}");
        self::assertResponseStatusCodeSame(404);
    }
}

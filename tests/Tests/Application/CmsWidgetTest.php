<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Cms\Entity\Page;
use Forumify\Cms\Repository\PageRepository;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductStatus;
use PluginTests\Tests\Factories\ProductCategoryFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;

class CmsWidgetTest extends EcommerceWebTestCase
{
    public function testWidgetsRenderOnAPage(): void
    {
        $plugins = ProductCategoryFactory::createOne(['title' => 'Plugins']);
        $milsim = $this->createProduct('Milsim', 'Run your unit.', $plugins, 999);
        $this->createProduct('Calendar', 'Plan your events.', $plugins, 500);
        $this->createProduct('Shop', 'Sell things.', $plugins, 250);
        ProductFactory::createOne([
            'title' => 'Secret Plugin',
            'status' => ProductStatus::Draft,
            'categories' => new ArrayCollection([$plugins]),
        ]);

        $this->createPage([
            ['widget' => 'ecommerce.catalog_search', 'settings' => ['title' => 'Find an addon']],
            ['widget' => 'ecommerce.product', 'settings' => ['product' => $milsim->key, 'hideDescription' => true]],
            ['widget' => 'ecommerce.product_category', 'settings' => ['category' => $plugins->getSlug(), 'limit' => 2]],
        ]);

        $crawler = $this->client->request('GET', '/marketplace');
        self::assertResponseIsSuccessful();

        $search = $crawler->filter('.ecommerce-catalog-search');
        self::assertSame('/store', $search->filter('form')->attr('action'));
        self::assertSame('Search products', $search->filter('input[name="q"]')->attr('placeholder'));
        self::assertSelectorTextContains('.ecommerce-catalog-search h2', 'Find an addon');

        $product = $crawler->filter('.ecommerce-product-widget');
        self::assertSame(['Milsim'], $product->filter('.ecommerce-product-tile-title')->each(fn($t) => $t->text()));
        self::assertCount(0, $product->filter('.ecommerce-product-tile-summary'));
        self::assertSame('$9.99', $product->filter('.ecommerce-product-tile-price')->text());

        $category = $crawler->filter('.ecommerce-product-category-widget');
        self::assertSelectorTextContains('.ecommerce-product-category-widget h2', 'Plugins');
        self::assertSame(
            ['Milsim', 'Calendar'],
            $category->filter('.ecommerce-product-tile-title')->each(fn($t) => $t->text()),
        );
        self::assertSame('/store?category=plugins', $category->filter('a.btn-link')->attr('href'));

        $crawler = $this->client->submit($search->filter('form')->form(['q' => 'your unit']));
        self::assertSame(
            ['Milsim'],
            $crawler->filter('.ecommerce-product-tile-title')->each(fn($t) => $t->text()),
        );
    }

    public function testPricingTableAndBuyButtonSellAProduct(): void
    {
        $supporter = ProductFactory::createOne(['title' => 'Supporter', 'key' => 'supporter']);

        $free = ProductTierFactory::createOne(['product' => $supporter, 'key' => 'free', 'label' => 'Free']);
        ProductPriceFactory::createOne(['tier' => $free, 'amountMinor' => 0]);

        $premium = ProductTierFactory::createOne(['product' => $supporter, 'key' => 'premium', 'label' => 'Premium']);
        $monthly = ProductPriceFactory::new()->recurring()->create(['tier' => $premium, 'amountMinor' => 500]);
        ProductPriceFactory::new()->recurring(BillingInterval::Year)->create(['tier' => $premium, 'amountMinor' => 5000]);

        $this->createPage([
            ['widget' => 'ecommerce.pricing_table', 'settings' => ['product' => 'supporter', 'title' => 'Support us']],
            ['widget' => 'ecommerce.buy_button', 'settings' => [
                'tier' => 'supporter::premium',
                'type' => 'btn-cta',
                'showPrice' => true,
            ]],
        ]);

        $crawler = $this->client->request('GET', '/marketplace');
        self::assertResponseIsSuccessful();

        $table = $crawler->filter('.ecommerce-pricing-table-widget');
        self::assertSelectorTextContains('.ecommerce-pricing-table-widget h2', 'Support us');
        self::assertSame(['Free', 'Premium'], $table->filter('.ecommerce-tier-heading h3')->each(fn($h) => $h->text()));
        self::assertCount(2, $table->filter('form[data-tier]'));
        self::assertSame(
            ['$5.00 per month', '$50.00 per year'],
            $table->filter('#ecommerce-tier-' . $premium->getId() . ' .ecommerce-price-option')->each(
                fn($o) => $o->filter('span')->text(),
            ),
        );

        $button = $crawler->filter('.ecommerce-buy-button-widget a');
        self::assertSame('btn-cta', $button->attr('class'));
        self::assertSame('Get Premium', $button->text());
        self::assertSelectorTextContains('.ecommerce-buy-button-widget .ecommerce-amount', '$5.00');

        $this->client->click($button->link());
        self::assertResponseIsSuccessful();
        self::assertRouteSame('ecommerce_checkout', ['price' => $monthly->getId()]);
    }

    public function testBuyButtonIsDisabledWhenTheTierHasNoActivePrice(): void
    {
        $product = ProductFactory::createOne(['title' => 'Supporter', 'key' => 'supporter']);
        $tier = ProductTierFactory::createOne(['product' => $product, 'key' => 'premium', 'label' => 'Premium']);
        ProductPriceFactory::createOne(['tier' => $tier, 'amountMinor' => 500, 'active' => false]);

        $this->createPage([
            ['widget' => 'ecommerce.buy_button', 'settings' => ['tier' => 'supporter::premium', 'showPrice' => true]],
        ]);

        $crawler = $this->client->request('GET', '/marketplace');
        self::assertResponseIsSuccessful();

        $button = $crawler->filter('.ecommerce-buy-button-widget button');
        self::assertSame('Get Premium', $button->text());
        self::assertNotNull($button->attr('disabled'));
        self::assertSame('This product is not available right now.', $button->attr('title'));
        self::assertCount(0, $crawler->filter('.ecommerce-buy-button-widget a'));
        self::assertCount(0, $crawler->filter('.ecommerce-buy-button-widget .ecommerce-amount'));
    }

    public function testWidgetsRenderNothingWithoutAValidSelection(): void
    {
        $this->createPage([
            ['widget' => 'ecommerce.product', 'settings' => ['product' => 'removed-product']],
            ['widget' => 'ecommerce.product_category', 'settings' => ['category' => 'removed-category']],
            ['widget' => 'ecommerce.pricing_table', 'settings' => ['product' => 'removed-product']],
            ['widget' => 'ecommerce.buy_button', 'settings' => ['tier' => 'removed-product::gone']],
        ]);

        $crawler = $this->client->request('GET', '/marketplace');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.ecommerce-product-widget'));
        self::assertCount(0, $crawler->filter('.ecommerce-product-category-widget'));
        self::assertCount(0, $crawler->filter('.ecommerce-pricing-table-widget'));
        self::assertCount(0, $crawler->filter('.ecommerce-buy-button-widget'));
    }

    private function createProduct(string $title, string $description, ProductCategory $category, int $amount): object
    {
        $product = ProductFactory::createOne([
            'title' => $title,
            'shortDescription' => $description,
            'categories' => new ArrayCollection([$category]),
        ]);

        $tier = ProductTierFactory::createOne(['product' => $product]);
        ProductPriceFactory::createOne(['tier' => $tier, 'amountMinor' => $amount]);

        return $product;
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function createPage(array $tree): void
    {
        $page = new Page();
        $page->setTitle('Marketplace');
        $page->setUrlKey('marketplace');
        $page->setType(Page::TYPE_BUILDER);
        $page->setTwig(json_encode($tree, JSON_THROW_ON_ERROR));

        /** @var PageRepository $pageRepository */
        $pageRepository = static::getContainer()->get(PageRepository::class);
        $pageRepository->save($page);
    }
}

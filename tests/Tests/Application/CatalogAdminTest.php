<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\ProductState;
use Forumify\Ecommerce\Repository\ProductCategoryRepository;
use Forumify\Ecommerce\Repository\ProductImageRepository;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;

class CatalogAdminTest extends EcommerceWebTestCase
{
    public function testAdminCreatesProductWithFreeAndPremiumTier(): void
    {
        $this->client->request('GET', '/admin/ecommerce/categories/create');
        $this->client->submitForm('Save', ['product_category[title]' => 'Plugins']);
        self::assertResponseIsSuccessful();
        $category = self::getContainer()->get(ProductCategoryRepository::class)->findOneBy(['title' => 'Plugins']);
        self::assertNotNull($category);

        $this->client->request('GET', '/admin/ecommerce/products/create');
        $this->client->submitForm('Save', ['product[title]' => 'Milsim Plugin', 'product[key]' => 'Milsim Plugin']);
        self::assertSelectorTextContains('body', 'Use only lowercase letters');

        $this->client->submitForm('Save', [
            'product[title]' => 'Milsim Plugin',
            'product[key]' => 'forumify/forumify-milhq-plugin',
            'product[state]' => ProductState::Published->value,
            'product[categories]' => [(string)$category->getId()],
            'product[shortDescription]' => 'Run your unit with forumify.',
        ]);
        self::assertResponseIsSuccessful();
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy(['title' => 'Milsim Plugin']);
        self::assertNotNull($product);

        $this->addTier($product->getId(), 'Free', 'free', [
            'new_product_tier[price][amountMinor]' => '0',
            'new_product_tier[price][type]' => PriceType::OneTime->value,
        ]);
        $this->addTier($product->getId(), 'Premium', 'premium', [
            'new_product_tier[price][amountMinor]' => '9.99',
            'new_product_tier[price][type]' => PriceType::Recurring->value,
            'new_product_tier[price][intervalCount]' => '1',
            'new_product_tier[price][billingInterval]' => BillingInterval::Month->value,
        ]);

        $this->client->request('GET', "/admin/ecommerce/products/{$product->getId()}/edit");
        self::assertSelectorTextContains('#tiers', '$9.99 per month');

        $monthlyId = self::getContainer()->get(ProductRepository::class)
            ->find($product->getId())->tiers->last()->prices->first()->getId();
        $this->client->request('GET', "/admin/ecommerce/products/prices/$monthlyId/delete");
        self::assertSelectorTextContains('body', 'A tier needs at least one price');

        self::getContainer()->get(ProductRepository::class)->getEntityManager()->clear();
        $product = self::getContainer()->get(ProductRepository::class)->find($product->getId());
        self::assertSame(ProductState::Published, $product->state);
        self::assertSame('forumify/forumify-milhq-plugin', $product->key);
        self::assertSame(['Plugins'], $product->categories->map(fn ($c) => $c->title)->toArray());

        [$free, $premium] = $product->tiers->toArray();
        self::assertSame('free', $free->key);
        self::assertTrue($free->prices->first()->isFree());

        $monthly = $premium->prices->first();
        self::assertSame('premium', $premium->key);
        self::assertSame(999, $monthly->amountMinor);
        self::assertSame('USD', $monthly->currency);
        self::assertSame(BillingInterval::Month, $monthly->billingInterval);
    }

    public function testAdminChangesLockedPrice(): void
    {
        $price = ProductPriceFactory::new()->recurring()->locked()->create(['amountMinor' => 1000]);

        $this->client->request('GET', "/admin/ecommerce/products/prices/{$price->getId()}/edit");
        self::assertSelectorExists('#product_price_amountMinor[disabled]');

        $this->client->request('GET', "/admin/ecommerce/products/prices/{$price->getId()}/change");
        $this->client->submitForm('Save', ['product_price[amountMinor]' => '12.00']);
        self::assertSelectorTextContains('body', 'The price was changed.');

        $price = self::getContainer()->get(ProductPriceRepository::class)->find($price->getId());
        $replacement = $price->replacedBy;
        self::assertNotNull($replacement);
        self::assertFalse($price->active);
        self::assertTrue($replacement->active);
        self::assertSame(1200, $replacement->amountMinor);
        self::assertSame(BillingInterval::Month, $replacement->billingInterval);
        self::assertSame(1000, $price->amountMinor);
    }

    public function testAdminAddsGalleryImages(): void
    {
        $product = ProductFactory::createOne(['image' => 'cover.png']);

        foreach ([1, 2] as $_) {
            $crawler = $this->client->request('GET', "/admin/ecommerce/products/{$product->getId()}/images/create");
            $form = $crawler->selectButton('Save')->form();
            $form['product_images[images][file]'][0]->upload(TEST_DATA_DIR . '/product.png');
            $this->client->submit($form);
            self::assertSelectorTextContains('body', 'The images were added to the gallery.');
        }

        $images = self::getContainer()->get(ProductImageRepository::class)->findBy(['product' => $product->getId()], ['position' => 'ASC']);
        self::assertCount(2, $images);

        $this->client->request('GET', "/admin/ecommerce/products/images/{$images[0]->getId()}/delete");
        self::assertSelectorTextContains('body', 'The image was removed from the gallery.');

        $product = self::getContainer()->get(ProductRepository::class)->find($product->getId());
        self::assertSame(['cover.png', $images[1]->image], $product->gallery);
    }

    /**
     * @param array<string, string> $priceFields
     */
    private function addTier(int $productId, string $label, string $key, array $priceFields): void
    {
        $this->client->request('GET', "/admin/ecommerce/products/$productId/tiers/create");
        $this->client->submitForm('Save', [
            'new_product_tier[tier][label]' => $label,
            'new_product_tier[tier][key]' => $key,
            ...$priceFields,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'The tier was saved.');
    }
}

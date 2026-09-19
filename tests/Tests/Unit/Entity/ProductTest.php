<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductImage;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class ProductTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function keyProvider(): iterable
    {
        yield 'simple' => ['t-shirt', true];
        yield 'composer package' => ['forumify/forumify-milhq-plugin', true];
        yield 'dotted' => ['support_v2.0', true];
        yield 'uppercase' => ['T-Shirt', false];
        yield 'spaces' => ['t shirt', false];
        yield 'leading slash' => ['/t-shirt', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('keyProvider')]
    public function testKeyFormat(string $key, bool $valid): void
    {
        $product = new Product();
        $product->key = $key;

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validateProperty($product, 'key');

        self::assertSame($valid, $violations->count() === 0);
    }

    public function testProductIsLockedOnceAnyPriceIsLocked(): void
    {
        $product = new Product();
        $tier = new ProductTier($product);
        $product->tiers->add($tier);
        $price = new ProductPrice($tier, 'USD');
        $tier->prices->add($price);

        self::assertFalse($product->hasLockedPrices());

        $price->lock();
        self::assertTrue($product->hasLockedPrices());
    }

    public function testGalleryStartsWithTheCoverImage(): void
    {
        $product = new Product();
        $product->images->add(new ProductImage($product, 'screenshot.png'));
        self::assertSame(['screenshot.png'], $product->gallery);

        $product->image = 'cover.png';
        self::assertSame(['cover.png', 'screenshot.png'], $product->gallery);
    }
}

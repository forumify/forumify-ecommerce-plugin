<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\ProductImage;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductImage>
 */
class ProductImageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductImage::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'product' => ProductFactory::new(),
            'image' => self::faker()->uuid() . '.png',
        ];
    }
}

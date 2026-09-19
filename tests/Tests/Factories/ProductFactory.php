<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductState;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Product>
 */
class ProductFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->words(3, true),
            'key' => self::faker()->unique()->slug(3),
            'shortDescription' => self::faker()->sentence(),
            'state' => ProductState::Published,
        ];
    }
}

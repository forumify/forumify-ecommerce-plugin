<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\ProductTier;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductTier>
 */
class ProductTierFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductTier::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'product' => ProductFactory::new(),
            'key' => self::faker()->unique()->slug(2),
            'label' => self::faker()->word(),
        ];
    }
}

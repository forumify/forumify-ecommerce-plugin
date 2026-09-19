<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\ProductCategory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductCategory>
 */
class ProductCategoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductCategory::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->unique()->words(2, true),
        ];
    }
}

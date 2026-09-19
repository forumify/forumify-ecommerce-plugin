<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProductCategory;

/**
 * @extends AbstractRepository<ProductCategory>
 */
class ProductCategoryRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProductCategory::class;
    }
}

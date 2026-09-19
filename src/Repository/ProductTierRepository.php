<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProductTier;

/**
 * @extends AbstractRepository<ProductTier>
 */
class ProductTierRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProductTier::class;
    }
}

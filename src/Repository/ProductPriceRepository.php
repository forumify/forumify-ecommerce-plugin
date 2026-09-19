<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProductPrice;

/**
 * @extends AbstractRepository<ProductPrice>
 */
class ProductPriceRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProductPrice::class;
    }
}

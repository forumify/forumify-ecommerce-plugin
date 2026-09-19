<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProductImage;

/**
 * @extends AbstractRepository<ProductImage>
 */
class ProductImageRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProductImage::class;
    }
}

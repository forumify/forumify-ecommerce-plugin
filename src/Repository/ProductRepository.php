<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductState;

/**
 * @extends AbstractRepository<Product>
 */
class ProductRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Product::class;
    }

    public function findPublishedBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug, 'state' => ProductState::Published]);
    }
}

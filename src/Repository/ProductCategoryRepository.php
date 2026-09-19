<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductStatus;

/**
 * @extends AbstractRepository<ProductCategory>
 */
class ProductCategoryRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProductCategory::class;
    }

    /**
     * @return list<ProductCategory>
     */
    public function findWithPublishedProducts(): array
    {
        $publishedProducts = $this->getEntityManager()->createQueryBuilder()
            ->select('p.id')
            ->from(Product::class, 'p')
            ->innerJoin('p.categories', 'pc')
            ->where('pc = c')
            ->andWhere('p.status = :published');

        return $this->createQueryBuilder('c')
            ->where("EXISTS ({$publishedProducts->getDQL()})")
            ->setParameter('published', ProductStatus::Published)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

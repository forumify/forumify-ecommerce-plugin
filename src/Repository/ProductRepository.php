<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductStatus;

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
        return $this->findOneBy(['slug' => $slug, 'status' => ProductStatus::Published]);
    }

    /**
     * @return list<Product>
     */
    public function findPublishedInCategory(ProductCategory $category, int $limit): array
    {
        /** @var list<Product> $products */
        $products = $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->where('c = :category')
            ->andWhere('p.status = :published')
            ->setParameter('category', $category)
            ->setParameter('published', ProductStatus::Published)
            ->orderBy('p.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $this->hydratePrices($products);

        return $products;
    }

    /**
     * Loads the tiers and prices of already hydrated products in a single query, so rendering their
     * starting price does not trigger a lazy load per product.
     *
     * @param array<Product> $products
     */
    public function hydratePrices(array $products): void
    {
        if ($products === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->select('p', 't', 'pr')
            ->leftJoin('p.tiers', 't')
            ->leftJoin('t.prices', 'pr')
            ->where('p IN (:products)')
            ->setParameter('products', $products)
            ->getQuery()
            ->getResult();
    }
}

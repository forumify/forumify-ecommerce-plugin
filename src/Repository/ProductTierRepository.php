<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProductStatus;
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

    public function findPublishedByKeys(string $productKey, string $tierKey): ?ProductTier
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.product', 'p')
            ->andWhere('p.key = :productKey')
            ->andWhere('p.status = :published')
            ->andWhere('t.key = :tierKey')
            ->setParameter('productKey', $productKey)
            ->setParameter('tierKey', $tierKey)
            ->setParameter('published', ProductStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Every tier, drafts included, so a page can be built before the product goes live.
     *
     * @return list<ProductTier>
     */
    public function findAllForSelection(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'p')
            ->innerJoin('t.product', 'p')
            ->orderBy('p.title', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

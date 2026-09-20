<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use DateTimeImmutable;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\CancellationRequest;

/**
 * @extends AbstractRepository<CancellationRequest>
 */
class CancellationRequestRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return CancellationRequest::class;
    }

    public function countPending(): int
    {
        return $this->count(['completedAt' => null]);
    }

    /**
     * Pending requests of subscriptions whose paid period has run out, that were not reminded about yet.
     *
     * @return list<CancellationRequest>
     */
    public function findDueReminders(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.subscription', 's')
            ->where('c.completedAt IS NULL')
            ->andWhere('c.remindedAt IS NULL')
            ->andWhere('s.currentPeriodEnd <= :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}

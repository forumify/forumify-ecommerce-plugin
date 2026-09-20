<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;

/**
 * @extends AbstractRepository<Subscription>
 */
class SubscriptionRepository extends AbstractRepository
{
    /** @use LockingRepositoryTrait<Subscription> */
    use LockingRepositoryTrait;

    public static function getEntityClass(): string
    {
        return Subscription::class;
    }

    public function findOneByProviderReference(string $providerKey, string $providerReference): ?Subscription
    {
        return $this->findOneBy(['providerKey' => $providerKey, 'providerReference' => $providerReference]);
    }

    public function findOneByInitialOrder(Order $order): ?Subscription
    {
        return $this->findOneBy(['initialOrder' => $order]);
    }

    /**
     * @return list<Subscription>
     */
    public function findCurrentForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect(
                'CASE WHEN s.status = :pastDue THEN 0 WHEN s.status = :active THEN 1 ELSE 2 END AS HIDDEN urgency',
            )
            ->where('s.user = :user')
            ->andWhere('s.status != :ended')
            ->setParameter('user', $user)
            ->setParameter('ended', SubscriptionStatus::Ended)
            ->setParameter('pastDue', SubscriptionStatus::PastDue)
            ->setParameter('active', SubscriptionStatus::Active)
            ->orderBy('urgency', 'ASC')
            ->addOrderBy('s.currentPeriodEnd', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Subscription>
     */
    public function findPastDue(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.pastDueSince IS NOT NULL')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])
            ->getQuery()
            ->getResult();
    }

    /**
     * @template TResult
     * @param callable(Subscription): TResult $callback
     * @return TResult|null
     */
    public function withLockedSubscription(string $providerKey, string $providerReference, callable $callback): mixed
    {
        return $this->withLocked(['providerKey' => $providerKey, 'providerReference' => $providerReference], $callback);
    }
}

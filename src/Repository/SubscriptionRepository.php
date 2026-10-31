<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\BillingInterval;
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
     * The subscriptions that still renew (active or past due), summed per status, currency and billing interval,
     * at the amount their initial order charged.
     *
     * @return list<array{
     *     status: SubscriptionStatus,
     *     currency: string,
     *     billingInterval: BillingInterval,
     *     intervalCount: int,
     *     count: int,
     *     total: int,
     * }>
     */
    public function sumOpenByInterval(): array
    {
        /** @var list<array{
         *     status: SubscriptionStatus,
         *     currency: string,
         *     billingInterval: BillingInterval|null,
         *     intervalCount: int|null,
         *     count: int|string,
         *     total: int|string,
         * }> $rows
         */
        $rows = $this->createQueryBuilder('s')
            ->select(
                's.status AS status',
                'o.currency AS currency',
                'o.billingInterval AS billingInterval',
                'o.intervalCount AS intervalCount',
                'COUNT(s.id) AS count',
                'SUM(o.amountMinor) AS total',
            )
            ->join('s.initialOrder', 'o')
            ->where('s.status IN (:statuses)')
            ->andWhere('o.billingInterval IS NOT NULL')
            ->setParameter('statuses', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->groupBy('s.status', 'o.currency', 'o.billingInterval', 'o.intervalCount')
            ->getQuery()
            ->getArrayResult();

        $sums = [];
        foreach ($rows as $row) {
            if ($row['billingInterval'] === null) {
                continue;
            }

            $sums[] = [
                'status' => $row['status'],
                'currency' => $row['currency'],
                'billingInterval' => $row['billingInterval'],
                'intervalCount' => $row['intervalCount'] ?? 1,
                'count' => (int)$row['count'],
                'total' => (int)$row['total'],
            ];
        }

        return $sums;
    }

    /**
     * How many subscriptions that still renew each of these users has.
     *
     * @param list<int> $userIds
     * @return array<int, int> user id => count
     */
    public function countOpenByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var list<array{userId: int|string, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.user) AS userId', 'COUNT(s.id) AS count')
            ->where('s.user IN (:users)')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('users', $userIds)
            ->setParameter('statuses', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->groupBy('s.user')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['userId']] = (int)$row['count'];
        }

        return $counts;
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

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Subscription;

/**
 * @extends AbstractRepository<Order>
 */
class OrderRepository extends AbstractRepository
{
    /** @use LockingRepositoryTrait<Order> */
    use LockingRepositoryTrait;

    public static function getEntityClass(): string
    {
        return Order::class;
    }

    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function findOneByProviderReference(string $providerKey, string $providerReference): ?Order
    {
        return $this->findOneBy(['providerKey' => $providerKey, 'providerReference' => $providerReference]);
    }

    /**
     * @return list<Order>
     */
    public function findPendingRenewals(Subscription $subscription): array
    {
        return $this->findBy(['subscription' => $subscription, 'status' => OrderStatus::Pending]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLatestFulfillmentData(User $user, string $fulfillmentType): ?array
    {
        $order = $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.fulfillmentType = :type')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('type', $fulfillmentType)
            ->setParameter('statuses', [OrderStatus::Paid, OrderStatus::Completed])
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $order instanceof Order && $order->fulfillmentData !== [] ? $order->fulfillmentData : null;
    }

    /**
     * The most recent payment of a subscription: a renewal, or the initial order.
     */
    public function findLatestPaidForSubscription(Subscription $subscription): ?Order
    {
        $order = $this->createQueryBuilder('o')
            ->where('o.subscription = :subscription OR o = :initialOrder')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('subscription', $subscription)
            ->setParameter('initialOrder', $subscription->initialOrder)
            ->setParameter('statuses', [OrderStatus::Paid, OrderStatus::Completed])
            ->orderBy('o.paidAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $order instanceof Order ? $order : null;
    }

    /**
     * Everything a store actually earned since a date, per currency, in minor units.
     * Refunded orders are not in it: a refund gives the money back, so it never counts as revenue.
     *
     * @return array<string, int>
     */
    public function sumRevenueSince(DateTimeInterface $since): array
    {
        /** @var list<array{currency: string, total: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.currency AS currency', 'SUM(o.amountMinor) AS total')
            ->where('o.status IN (:statuses)')
            ->andWhere('o.paidAt >= :since')
            ->setParameter('statuses', [OrderStatus::Paid, OrderStatus::Completed])
            ->setParameter('since', $since)
            ->groupBy('o.currency')
            ->getQuery()
            ->getArrayResult();

        $revenue = [];
        foreach ($rows as $row) {
            $revenue[$row['currency']] = (int)$row['total'];
        }

        return $revenue;
    }

    /**
     * The same revenue, bucketed per calendar month ("2026-09") as it is stored.
     *
     * @return array<string, array<string, int>> month => currency => minor units
     */
    public function sumRevenueByMonth(DateTimeInterface $since): array
    {
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT DATE_FORMAT(paid_at, \'%Y-%m\') AS month, currency, SUM(amount) AS total'
                . ' FROM ecommerce_order'
                . ' WHERE status IN (:statuses) AND paid_at >= :since'
                . ' GROUP BY month, currency',
            [
                'statuses' => [OrderStatus::Paid->value, OrderStatus::Completed->value],
                'since' => $since,
            ],
            [
                'statuses' => ArrayParameterType::STRING,
                'since' => Types::DATETIME_IMMUTABLE,
            ],
        )->fetchAllAssociative();

        $revenue = [];
        foreach ($rows as $row) {
            /** @var array{month: string, currency: string, total: string} $row */
            $revenue[$row['month']][$row['currency']] = (int)$row['total'];
        }

        return $revenue;
    }

    public function countToFulfill(): int
    {
        return $this->count(['status' => OrderStatus::Paid]);
    }

    /**
     * @template TResult
     * @param callable(Order): TResult $callback
     * @return TResult|null
     */
    public function withLockedOrder(string $reference, callable $callback): mixed
    {
        return $this->withLocked(['reference' => $reference], $callback);
    }
}

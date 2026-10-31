<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
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
            ->where('o.subscription = :subscription')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('subscription', $subscription)
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

    /**
     * Revenue per product tier between two dates, split into new orders and renewals, per currency.
     *
     * @return list<array{
     *     productKey: string,
     *     productTitle: string,
     *     tierKey: string,
     *     tierLabel: string,
     *     currency: string,
     *     renewal: bool,
     *     count: int,
     *     total: int,
     *     lastPaidAt: string,
     * }>
     */
    public function sumSalesByProduct(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        [$period, $params, $types] = self::paidBetween($from, $to);

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT product_key, product_title, tier_key, tier_label, currency,'
                . ' subscription_id IS NOT NULL AND NOT EXISTS ('
                . '     SELECT 1 FROM ecommerce_subscription s WHERE s.initial_order_id = ecommerce_order.id'
                . ' ) AS renewal, COUNT(id) AS count, SUM(amount) AS total,'
                . ' MAX(paid_at) AS last_paid_at'
                . ' FROM ecommerce_order'
                . " WHERE $period"
                . ' GROUP BY product_key, product_title, tier_key, tier_label, currency, renewal',
            $params,
            $types,
        )->fetchAllAssociative();

        return array_map(static fn(array $row) => [
            'productKey' => (string)$row['product_key'],
            'productTitle' => (string)$row['product_title'],
            'tierKey' => (string)$row['tier_key'],
            'tierLabel' => (string)$row['tier_label'],
            'currency' => (string)$row['currency'],
            'renewal' => (bool)$row['renewal'],
            'count' => (int)$row['count'],
            'total' => (int)$row['total'],
            'lastPaidAt' => (string)$row['last_paid_at'],
        ], $rows);
    }

    /**
     * The buyers who spent the most between two dates, best first, with what they spent per currency.
     * They are ranked in US dollars through the stored exchange rates, so currencies compare without loading
     * every buyer. Deleted users are left out.
     *
     * @return list<array{userId: int, currency: string, count: int, total: int, customerSince: string}>
     */
    public function findTopCustomers(?DateTimeImmutable $from, ?DateTimeImmutable $to, int $limit): array
    {
        [$period, $params, $types] = self::paidBetween($from, $to, 'o.');
        [$rankedPeriod] = self::paidBetween($from, $to, 'r.');

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT o.user_id, o.currency, COUNT(o.id) AS count, SUM(o.amount) AS total, top.customer_since'
                . ' FROM ecommerce_order o'
                . ' JOIN ('
                . '     SELECT r.user_id, COUNT(r.id) AS count, SUM(r.amount / COALESCE(x.rate, 1)) AS ranking,'
                . '         (SELECT MIN(f.paid_at) FROM ecommerce_order f'
                . '             WHERE f.user_id = r.user_id AND f.status IN (:statuses)) AS customer_since'
                . '     FROM ecommerce_order r'
                . '     LEFT JOIN ecommerce_exchange_rate x ON x.currency = r.currency'
                . "     WHERE $rankedPeriod AND r.user_id IS NOT NULL"
                . '     GROUP BY r.user_id'
                . '     ORDER BY ranking DESC, count DESC, r.user_id ASC'
                . "     LIMIT $limit"
                . ' ) top ON top.user_id = o.user_id'
                . " WHERE $period"
                . ' GROUP BY o.user_id, o.currency, top.ranking, top.count, top.customer_since'
                . ' ORDER BY top.ranking DESC, top.count DESC, o.user_id ASC',
            $params,
            $types,
        )->fetchAllAssociative();

        return array_map(static fn(array $row) => [
            'userId' => (int)$row['user_id'],
            'currency' => (string)$row['currency'],
            'count' => (int)$row['count'],
            'total' => (int)$row['total'],
            'customerSince' => (string)$row['customer_since'],
        ], $rows);
    }

    /**
     * @return array{string, array<string, mixed>, array<string, ArrayParameterType|string>}
     */
    private static function paidBetween(?DateTimeImmutable $from, ?DateTimeImmutable $to, string $alias = ''): array
    {
        $conditions = ["{$alias}status IN (:statuses)"];
        $params = ['statuses' => [OrderStatus::Paid->value, OrderStatus::Completed->value]];
        $types = ['statuses' => ArrayParameterType::STRING];

        foreach (['from' => [$from, '>='], 'to' => [$to, '<']] as $name => [$date, $operator]) {
            if ($date === null) {
                continue;
            }

            $conditions[] = "{$alias}paid_at $operator :$name";
            $params[$name] = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $types[$name] = Types::DATETIME_IMMUTABLE;
        }

        return [implode(' AND ', $conditions), $params, $types];
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

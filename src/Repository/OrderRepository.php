<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

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

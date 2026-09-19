<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Doctrine\DBAL\LockMode;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Order;

/**
 * @extends AbstractRepository<Order>
 */
class OrderRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Order::class;
    }

    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /**
     * @template TResult
     * @param callable(Order): TResult $callback
     * @return TResult|null
     */
    public function withLockedOrder(string $reference, callable $callback): mixed
    {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($em, $reference, $callback) {
            $order = $this->findOneByReference($reference);
            if ($order === null) {
                return null;
            }

            $em->refresh($order, LockMode::PESSIMISTIC_WRITE);

            return $callback($order);
        });
    }
}

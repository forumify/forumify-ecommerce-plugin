<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Doctrine\DBAL\LockMode;

/**
 * @template TEntity of object
 */
trait LockingRepositoryTrait
{
    /**
     * @template TResult
     * @param array<string, mixed> $criteria
     * @param callable(TEntity): TResult $callback
     * @return TResult|null
     */
    private function withLocked(array $criteria, callable $callback): mixed
    {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($em, $criteria, $callback) {
            $entity = $this->findOneBy($criteria);
            if ($entity === null) {
                return null;
            }

            $em->refresh($entity, LockMode::PESSIMISTIC_WRITE);

            return $callback($entity);
        });
    }
}

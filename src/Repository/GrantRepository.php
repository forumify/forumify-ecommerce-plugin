<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\Grant;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Forum\Entity\Badge;

/**
 * @extends AbstractRepository<Grant>
 */
class GrantRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return Grant::class;
    }

    public function findFirst(User $user, Role|Badge $granted): ?Grant
    {
        return $this->findOneBy(
            ['user' => $user, $granted instanceof Role ? 'role' : 'badge' => $granted],
            ['id' => 'ASC'],
        );
    }

    /**
     * @return list<Grant>
     */
    public function findBySubscription(Subscription $subscription): array
    {
        return $this->findBy(['subscription' => $subscription]);
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\ProviderCustomer;

/**
 * @extends AbstractRepository<ProviderCustomer>
 */
class ProviderCustomerRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return ProviderCustomer::class;
    }

    public function findOneFor(User $user, string $providerKey): ?ProviderCustomer
    {
        return $this->findOneBy(['user' => $user, 'providerKey' => $providerKey]);
    }
}

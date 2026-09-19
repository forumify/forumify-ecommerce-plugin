<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\ProviderCustomerRepository;

#[ORM\Entity(repositoryClass: ProviderCustomerRepository::class)]
#[ORM\Table('ecommerce_provider_customer')]
#[ORM\UniqueConstraint(fields: ['user', 'providerKey'])]
class ProviderCustomer
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) User $user;

    #[ORM\Column(length: 32)]
    public private(set) string $providerKey;

    #[ORM\Column(length: 255)]
    public private(set) string $externalId;

    public function __construct(User $user, string $providerKey, string $externalId)
    {
        $this->user = $user;
        $this->providerKey = $providerKey;
        $this->externalId = $externalId;
    }
}

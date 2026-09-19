<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SensitiveField;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;

#[ORM\Entity(repositoryClass: PaymentProviderConfigRepository::class)]
#[ORM\Table('ecommerce_payment_provider_config')]
class PaymentProviderConfig implements AuditableEntityInterface
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 32, unique: true)]
    public private(set) string $providerKey;

    #[ORM\Column]
    public bool $enabled = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    #[SensitiveField]
    public array $settings = [];

    public function __construct(string $providerKey)
    {
        $this->providerKey = $providerKey;
    }

    public function getIdentifierForAudit(): string
    {
        return (string)$this->getId();
    }

    public function getNameForAudit(): string
    {
        return $this->providerKey;
    }
}

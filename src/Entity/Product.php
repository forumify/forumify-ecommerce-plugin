<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SluggableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table('ecommerce_product')]
#[UniqueEntity(fields: ['key'], message: 'ecommerce.product.key_taken', errorPath: 'key')]
class Product implements SortableEntityInterface, AuditableEntityInterface
{
    use IdentifiableEntityTrait;
    use SluggableEntityTrait;
    use SortableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Length(max: 255)]
    public string $title;

    #[ORM\Column(name: 'product_key', length: 128, unique: true)]
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Length(max: 128)]
    #[Assert\Regex('/^[a-z0-9][a-z0-9_.\/-]*$/', message: 'ecommerce.product.key_format')]
    public string $key;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    public string $shortDescription = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $description = '';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $image = null;

    #[ORM\Column(length: 16, enumType: ProductState::class)]
    public ProductState $state = ProductState::Draft;

    /** @var Collection<int, ProductCategory> */
    #[ORM\ManyToMany(targetEntity: ProductCategory::class)]
    #[ORM\JoinTable('ecommerce_product_categories')]
    public Collection $categories;

    /** @var Collection<int, ProductTier> */
    #[ORM\OneToMany(targetEntity: ProductTier::class, mappedBy: 'product', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public private(set) Collection $tiers;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->tiers = new ArrayCollection();
    }

    public function hasLockedPrices(): bool
    {
        return $this->tiers->exists(static fn (int $_, ProductTier $tier) => $tier->hasLockedPrices());
    }

    public function getIdentifierForAudit(): string
    {
        return (string)$this->getId();
    }

    public function getNameForAudit(): string
    {
        return $this->title;
    }
}

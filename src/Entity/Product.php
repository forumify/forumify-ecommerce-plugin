<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\SluggableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Fulfillment\Type\InstantFulfillmentType;
use Forumify\Ecommerce\Repository\ProductRepository;
use Forumify\Forum\Entity\Badge;
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

    #[ORM\Column(length: 16, enumType: ProductStatus::class)]
    public ProductStatus $status = ProductStatus::Draft;

    /** @var Collection<int, ProductCategory> */
    #[ORM\ManyToMany(targetEntity: ProductCategory::class)]
    #[ORM\JoinTable('ecommerce_product_categories')]
    public Collection $categories;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    public ?int $refundPeriodDays = null;

    #[ORM\Column(length: 64)]
    public string $fulfillmentType = InstantFulfillmentType::KEY;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public array $fulfillmentSettings = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Role $fulfillmentRole = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable('ecommerce_product_granted_roles')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    public Collection $grantedRoles;

    /** @var Collection<int, Badge> */
    #[ORM\ManyToMany(targetEntity: Badge::class)]
    #[ORM\JoinTable('ecommerce_product_granted_badges')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    public Collection $grantedBadges;

    /** @var Collection<int, ProductTier> */
    #[ORM\OneToMany(targetEntity: ProductTier::class, mappedBy: 'product', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public private(set) Collection $tiers;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public private(set) Collection $images;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->grantedRoles = new ArrayCollection();
        $this->grantedBadges = new ArrayCollection();
        $this->tiers = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    /**
     * @return list<string>
     */
    public function getGallery(): array
    {
        $images = $this->images->map(static fn(ProductImage $image) => $image->image)->getValues();

        return $this->image === null ? $images : [$this->image, ...$images];
    }

    /**
     * @return list<ProductTier>
     */
    public function getPurchasableTiers(): array
    {
        return $this->tiers
            ->filter(static fn(ProductTier $tier) => $tier->getActivePrices() !== [])
            ->getValues();
    }

    /**
     * @return list<ProductPrice>
     */
    public function getActivePrices(): array
    {
        return array_merge(...array_map(
            static fn(ProductTier $tier) => $tier->getActivePrices(),
            $this->tiers->toArray(),
        ));
    }

    public function getCheapestPrice(): ?ProductPrice
    {
        return ProductPrice::cheapest($this->getActivePrices());
    }

    public function hasLockedPrices(): bool
    {
        return $this->tiers->exists(static fn(int $_, ProductTier $tier) => $tier->hasLockedPrices());
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

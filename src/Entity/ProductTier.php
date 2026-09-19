<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductTierRepository::class)]
#[ORM\Table('ecommerce_product_tier')]
#[ORM\UniqueConstraint(fields: ['product', 'key'])]
#[UniqueEntity(fields: ['product', 'key'], message: 'ecommerce.tier.key_taken', errorPath: 'key')]
class ProductTier implements SortableEntityInterface, AuditableEntityInterface
{
    use IdentifiableEntityTrait;
    use SortableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'tiers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Product $product;

    #[ORM\Column(name: 'tier_key', length: 64)]
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9][a-z0-9_-]*$/', message: 'ecommerce.tier.key_format')]
    public string $key;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Length(max: 255)]
    public string $label;

    #[ORM\Column(type: Types::TEXT)]
    public string $description = '';

    #[ORM\Column]
    public bool $highlighted = false;

    /** @var Collection<int, ProductPrice> */
    #[ORM\OneToMany(targetEntity: ProductPrice::class, mappedBy: 'tier', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public private(set) Collection $prices;

    /** @var list<ProductPrice> */
    public array $activePrices {
        get => array_values($this->prices->filter(static fn (ProductPrice $price) => $price->active)->toArray());
    }

    public ?ProductPrice $cheapestPrice {
        get => ProductPrice::cheapest($this->activePrices);
    }

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->prices = new ArrayCollection();
    }

    public function hasLockedPrices(): bool
    {
        return $this->prices->exists(static fn (int $_, ProductPrice $price) => $price->locked);
    }

    public function getIdentifierForAudit(): string
    {
        return (string)$this->getId();
    }

    public function getNameForAudit(): string
    {
        return "{$this->product->title} - {$this->label}";
    }
}

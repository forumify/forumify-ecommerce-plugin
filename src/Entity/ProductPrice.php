<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Brick\Money\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\AuditableEntityInterface;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Core\Entity\SortableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Exception\PriceLockedException;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ProductPriceRepository::class)]
#[ORM\Table('ecommerce_product_price')]
class ProductPrice implements SortableEntityInterface, AuditableEntityInterface
{
    use IdentifiableEntityTrait;
    use SortableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) ProductTier $tier;

    #[ORM\Column(length: 3)]
    #[Assert\Currency]
    public private(set) string $currency;

    #[ORM\Column(name: 'amount')]
    #[Assert\PositiveOrZero]
    public int $amountMinor = 0 {
        set {
            $this->assertUnlocked();
            $this->amountMinor = $value;
        }
    }

    public Money $amount {
        get => Money::ofMinor($this->amountMinor, $this->currency);
    }

    #[ORM\Column(length: 16, enumType: PriceType::class)]
    public PriceType $type = PriceType::OneTime {
        set {
            $this->assertUnlocked();
            $this->type = $value;
        }
    }

    #[ORM\Column(length: 16, nullable: true, enumType: BillingInterval::class)]
    public ?BillingInterval $billingInterval = null {
        set {
            $this->assertUnlocked();
            $this->billingInterval = $value;
        }
    }

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    public ?int $intervalCount = null {
        set {
            $this->assertUnlocked();
            $this->intervalCount = $value;
        }
    }

    #[ORM\Column]
    public bool $active = true;

    #[ORM\Column]
    public private(set) bool $locked = false;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?ProductPrice $replacedBy = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    public array $providerReferences = [];

    public function __construct(ProductTier $tier, string $currency)
    {
        $this->tier = $tier;
        $this->currency = $currency;
    }

    public function isFree(): bool
    {
        return $this->amountMinor === 0;
    }

    public function lock(): void
    {
        $this->locked = true;
    }

    public function replaceWith(ProductPrice $replacement): void
    {
        if ($replacement === $this || $replacement->tier !== $this->tier) {
            throw new InvalidArgumentException('A price can only be replaced by another price in the same tier.');
        }

        $this->replacedBy = $replacement;
        $this->active = false;
    }

    #[Assert\Callback]
    public function validateBillingInterval(ExecutionContextInterface $context): void
    {
        if ($this->type === PriceType::Recurring) {
            if ($this->billingInterval === null) {
                $context->buildViolation('ecommerce.price.interval_required')->atPath('billingInterval')->addViolation();
            }
            if ($this->intervalCount === null) {
                $context->buildViolation('ecommerce.price.interval_required')->atPath('intervalCount')->addViolation();
            }
            return;
        }

        if ($this->billingInterval !== null || $this->intervalCount !== null) {
            $context->buildViolation('ecommerce.price.interval_not_allowed')->atPath('billingInterval')->addViolation();
        }
    }

    public function getIdentifierForAudit(): string
    {
        return (string)$this->getId();
    }

    public function getNameForAudit(): string
    {
        $name = "{$this->tier->getNameForAudit()}: {$this->amount}";
        if ($this->billingInterval !== null) {
            $name .= " / {$this->intervalCount} {$this->billingInterval->value}";
        }

        return $name;
    }

    private function assertUnlocked(): void
    {
        if ($this->locked) {
            throw new PriceLockedException($this);
        }
    }
}

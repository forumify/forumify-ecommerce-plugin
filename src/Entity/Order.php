<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Brick\Money\Money;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\OrderRepository;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table('ecommerce_order')]
class Order
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 32, unique: true)]
    public private(set) string $reference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $user;

    #[ORM\Column(length: 16, enumType: OrderStatus::class)]
    public private(set) OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(length: 32, nullable: true)]
    public private(set) ?string $providerKey;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $providerReference = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?Product $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?ProductPrice $price;

    #[ORM\Column(length: 128)]
    public private(set) string $productKey;

    #[ORM\Column(length: 255)]
    public private(set) string $productTitle;

    #[ORM\Column(length: 64)]
    public private(set) string $tierKey;

    #[ORM\Column(length: 255)]
    public private(set) string $tierLabel;

    #[ORM\Column(name: 'amount')]
    public private(set) int $amountMinor;

    #[ORM\Column(length: 3)]
    public private(set) string $currency;

    #[ORM\Column(length: 16, enumType: PriceType::class)]
    public private(set) PriceType $priceType;

    #[ORM\Column(length: 16, nullable: true, enumType: BillingInterval::class)]
    public private(set) ?BillingInterval $billingInterval;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $intervalCount;

    public Money $amount {
        get => Money::ofMinor($this->amountMinor, $this->currency);
    }

    public function __construct(User $user, ProductPrice $price, ?string $providerKey)
    {
        $tier = $price->tier;
        $product = $tier->product;

        $this->reference = strtoupper(bin2hex(random_bytes(6)));
        $this->user = $user;
        $this->providerKey = $providerKey;
        $this->product = $product;
        $this->price = $price;
        $this->productKey = $product->key;
        $this->productTitle = $product->title;
        $this->tierKey = $tier->key;
        $this->tierLabel = $tier->label;
        $this->amountMinor = $price->amountMinor;
        $this->currency = $price->currency;
        $this->priceType = $price->type;
        $this->billingInterval = $price->billingInterval;
        $this->intervalCount = $price->intervalCount;
    }

    public function isOwnedBy(?UserInterface $user): bool
    {
        return $user instanceof User && $this->user?->getId() === $user->getId();
    }

    public function isFree(): bool
    {
        return $this->amountMinor === 0;
    }

    public function complete(): bool
    {
        if ($this->status === OrderStatus::Completed) {
            return false;
        }

        $this->status = OrderStatus::Completed;
        $this->completedAt = new DateTimeImmutable();

        return true;
    }

    public function fail(): bool
    {
        return $this->leavePending(OrderStatus::Failed);
    }

    public function cancel(): bool
    {
        return $this->leavePending(OrderStatus::Cancelled);
    }

    private function leavePending(OrderStatus $status): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        $this->status = $status;

        return true;
    }
}

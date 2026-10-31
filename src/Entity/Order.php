<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Brick\Money\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\OrderRepository;
use LogicException;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table('ecommerce_order')]
#[ORM\UniqueConstraint(fields: ['providerKey', 'providerReference'])]
#[ORM\Index(name: 'idx_ecommerce_order_revenue', fields: ['status', 'paidAt'])]
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
    public private(set) ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $refundedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $refundPeriodDays;

    #[ORM\OneToOne(mappedBy: 'order')]
    public private(set) ?RefundRequest $refundRequest = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $completedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?Subscription $subscription;

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

    #[ORM\Column(length: 64)]
    public private(set) string $fulfillmentType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $fulfillmentData;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $fulfillmentNote = null;

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: Types::JSON)]
    public array $metadata = [] {
        set {
            $this->assertPending('Metadata can only be attached while an order is pending.');
            $this->metadata = $value;
        }
    }

    /**
     * @param array<string, mixed> $fulfillmentData
     */
    public function __construct(
        ?User $user,
        ProductPrice $price,
        ?string $providerKey,
        ?Subscription $subscription = null,
        array $fulfillmentData = [],
    ) {
        $tier = $price->tier;
        $product = $tier->product;

        $this->reference = strtoupper(bin2hex(random_bytes(6)));
        $this->user = $user;
        $this->providerKey = $providerKey;
        $this->subscription = $subscription;
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
        $this->refundPeriodDays = $product->refundPeriodDays;
        $this->fulfillmentType = $subscription?->initialOrder->fulfillmentType ?? $product->fulfillmentType;
        $this->fulfillmentData = $subscription?->initialOrder->fulfillmentData ?? $fulfillmentData;
    }

    public function getAmount(): Money
    {
        return Money::ofMinor($this->amountMinor, $this->currency);
    }

    public function isOwnedBy(?UserInterface $user): bool
    {
        return $user instanceof User && $this->user?->getId() === $user->getId();
    }

    public function isRenewal(): bool
    {
        return $this->subscription !== null && $this->subscription->initialOrder !== $this;
    }

    public function startSubscription(Subscription $subscription): void
    {
        if ($subscription->initialOrder !== $this || $this->subscription !== null) {
            throw new LogicException('An order can only start the subscription it is the initial order of.');
        }

        $this->subscription = $subscription;
    }

    public function isFree(): bool
    {
        return $this->amountMinor === 0;
    }

    public function getRefundDeadline(): ?DateTimeImmutable
    {
        if ($this->refundPeriodDays === null || $this->completedAt === null) {
            return null;
        }

        return $this->completedAt->modify("+{$this->refundPeriodDays} days");
    }

    public function isRefundable(): bool
    {
        if ($this->refundPeriodDays === null || $this->isFree()) {
            return false;
        }

        if ($this->status === OrderStatus::Paid) {
            return true;
        }

        $deadline = $this->getRefundDeadline();

        return $this->status === OrderStatus::Completed
            && $deadline !== null
            && $deadline >= new DateTimeImmutable();
    }

    public function periodEndFrom(DateTimeImmutable $start): DateTimeImmutable
    {
        if ($this->billingInterval === null) {
            throw new LogicException('Only a recurring order has a billing period.');
        }

        return $this->billingInterval->addTo($start, $this->intervalCount ?? 1);
    }

    public function pay(): bool
    {
        $settled = [OrderStatus::Paid, OrderStatus::Completed, OrderStatus::Refunded];
        if (in_array($this->status, $settled, true)) {
            return false;
        }

        $this->status = OrderStatus::Paid;
        $this->paidAt = new DateTimeImmutable();

        return true;
    }

    public function complete(?User $by = null, ?string $note = null): bool
    {
        if ($this->status !== OrderStatus::Paid) {
            return false;
        }

        $this->status = OrderStatus::Completed;
        $this->completedAt = new DateTimeImmutable();
        $this->completedBy = $by;
        $this->fulfillmentNote = $note !== null && trim($note) !== '' ? trim($note) : null;

        return true;
    }

    public function refund(): bool
    {
        if ($this->status !== OrderStatus::Paid && $this->status !== OrderStatus::Completed) {
            return false;
        }

        $this->status = OrderStatus::Refunded;
        $this->refundedAt = new DateTimeImmutable();

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

    private function assertPending(string $message): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new LogicException($message);
        }
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

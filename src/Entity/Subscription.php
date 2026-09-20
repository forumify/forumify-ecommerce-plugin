<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use InvalidArgumentException;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table('ecommerce_subscription')]
#[ORM\UniqueConstraint(fields: ['providerKey', 'providerReference'])]
class Subscription
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    public const GRACE_DAYS = 30;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $user;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $initialOrder;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?ProductPrice $price;

    #[ORM\Column(length: 32)]
    public private(set) string $providerKey;

    #[ORM\Column(length: 255)]
    public private(set) string $providerReference;

    #[ORM\Column(length: 16, enumType: SubscriptionStatus::class)]
    public private(set) SubscriptionStatus $status = SubscriptionStatus::Active;

    #[ORM\Column]
    public private(set) DateTimeImmutable $currentPeriodEnd;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $endedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $pastDueSince = null;

    public function __construct(Order $initialOrder, string $providerReference, DateTimeImmutable $currentPeriodEnd)
    {
        if ($initialOrder->providerKey === null || $initialOrder->priceType !== PriceType::Recurring) {
            throw new InvalidArgumentException(
                'Only a recurring order paid through a payment provider can start a subscription.',
            );
        }

        $this->user = $initialOrder->user;
        $this->initialOrder = $initialOrder;
        $this->price = $initialOrder->price;
        $this->providerKey = $initialOrder->providerKey;
        $this->providerReference = $providerReference;
        $this->currentPeriodEnd = $currentPeriodEnd;
    }

    public function getGraceEndsAt(): ?DateTimeImmutable
    {
        if ($this->pastDueSince === null) {
            return null;
        }

        return min(
            $this->pastDueSince->modify('+' . self::GRACE_DAYS . ' days'),
            $this->initialOrder->periodEndFrom($this->currentPeriodEnd),
        );
    }

    public function isOwnedBy(?UserInterface $user): bool
    {
        return $user instanceof User && $this->user?->getId() === $user->getId();
    }

    public function renew(DateTimeImmutable $periodEnd): bool
    {
        if ($this->status === SubscriptionStatus::Ended) {
            return false;
        }

        if ($periodEnd <= $this->currentPeriodEnd && $this->status !== SubscriptionStatus::PastDue) {
            return false;
        }

        $this->currentPeriodEnd = max($this->currentPeriodEnd, $periodEnd);
        $this->status = SubscriptionStatus::Active;
        $this->pastDueSince = null;

        return true;
    }

    public function markPastDue(): bool
    {
        if (!$this->transition([SubscriptionStatus::Active], SubscriptionStatus::PastDue)) {
            return false;
        }

        $this->pastDueSince ??= new DateTimeImmutable();

        return true;
    }

    public function cancel(): bool
    {
        return $this->transition(
            [SubscriptionStatus::Active, SubscriptionStatus::PastDue],
            SubscriptionStatus::Cancelled,
        );
    }

    public function resume(): bool
    {
        return $this->transition(
            [SubscriptionStatus::Cancelled],
            $this->pastDueSince === null ? SubscriptionStatus::Active : SubscriptionStatus::PastDue,
        );
    }

    public function end(): bool
    {
        if ($this->status === SubscriptionStatus::Ended) {
            return false;
        }

        $this->status = SubscriptionStatus::Ended;
        $this->endedAt = new DateTimeImmutable();

        return true;
    }

    /**
     * @param list<SubscriptionStatus> $from
     */
    private function transition(array $from, SubscriptionStatus $to): bool
    {
        if (!in_array($this->status, $from, true)) {
            return false;
        }

        $this->status = $to;

        return true;
    }
}

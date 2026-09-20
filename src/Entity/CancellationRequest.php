<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\CancellationRequestRepository;

#[ORM\Entity(repositoryClass: CancellationRequestRepository::class)]
#[ORM\Table('ecommerce_cancellation_request')]
class CancellationRequest
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\OneToOne(inversedBy: 'cancellationRequest')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Subscription $subscription;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $completedBy = null;

    #[ORM\Column(nullable: true)]
    public ?DateTimeImmutable $remindedAt = null;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function isPending(): bool
    {
        return $this->completedAt === null;
    }

    public function complete(?User $by): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->completedAt = new DateTimeImmutable();
        $this->completedBy = $by;

        return true;
    }
}

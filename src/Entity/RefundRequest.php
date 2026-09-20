<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\RefundRequestRepository;

#[ORM\Entity(repositoryClass: RefundRequestRepository::class)]
#[ORM\Table('ecommerce_refund_request')]
class RefundRequest
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\OneToOne(inversedBy: 'refundRequest')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $user;

    #[ORM\Column(type: Types::TEXT)]
    public private(set) string $reason;

    #[ORM\Column(length: 16, enumType: RefundRequestStatus::class)]
    public private(set) RefundRequestStatus $status = RefundRequestStatus::Pending;

    #[ORM\Column(nullable: true)]
    public private(set) ?DateTimeImmutable $handledAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?User $handledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $note = null;

    public function __construct(Order $order, ?User $user, string $reason)
    {
        $this->order = $order;
        $this->user = $user;
        $this->reason = $reason;
    }

    public function handle(RefundRequestStatus $status, ?User $by, ?string $note): bool
    {
        if ($this->status !== RefundRequestStatus::Pending || $status === RefundRequestStatus::Pending) {
            return false;
        }

        $this->status = $status;
        $this->handledAt = new DateTimeImmutable();
        $this->handledBy = $by;
        $this->note = $note !== null && trim($note) !== '' ? trim($note) : null;

        return true;
    }
}

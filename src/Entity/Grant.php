<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\GrantRepository;
use Forumify\Forum\Entity\Badge;

#[ORM\Entity(repositoryClass: GrantRepository::class)]
#[ORM\Table('ecommerce_grant')]
class Grant
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public private(set) User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public private(set) ?Role $role;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public private(set) ?Badge $badge;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public private(set) ?Subscription $subscription;

    #[ORM\Column]
    public private(set) bool $preExisting;

    private function __construct(User $user, ?Role $role, ?Badge $badge, Order $order, ?Subscription $subscription, bool $preExisting)
    {
        $this->user = $user;
        $this->role = $role;
        $this->badge = $badge;
        $this->order = $order;
        $this->subscription = $subscription;
        $this->preExisting = $preExisting;
    }

    public static function role(User $user, Role $role, Order $order, ?Subscription $subscription, bool $preExisting): self
    {
        return new self($user, $role, null, $order, $subscription, $preExisting);
    }

    public static function badge(User $user, Badge $badge, Order $order, ?Subscription $subscription, bool $preExisting): self
    {
        return new self($user, null, $badge, $order, $subscription, $preExisting);
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Component;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus as Status;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Ecommerce\\OrderStatus', '@ForumifyEcommercePlugin/frontend/components/order_status.html.twig')]
class OrderStatus
{
    use DefaultActionTrait;

    #[LiveProp]
    public Order $order;

    public function isPending(): bool
    {
        return $this->order->status === Status::Pending;
    }
}

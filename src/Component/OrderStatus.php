<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Component;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus as Status;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderCapability;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Service\RefundService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('Ecommerce\\OrderStatus', '@ForumifyEcommercePlugin/frontend/components/order_status.html.twig')]
class OrderStatus
{
    use DefaultActionTrait;

    #[LiveProp]
    public Order $order;

    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly RefundService $refundService,
    ) {
    }

    public function isPending(): bool
    {
        return $this->order->status === Status::Pending;
    }

    public function hasInvoice(): bool
    {
        return $this->order->paidAt !== null
            && $this->registry->findCapableFor($this->order, PaymentProviderCapability::Invoice) !== null;
    }

    public function canPay(): bool
    {
        return $this->isPending()
            && $this->registry->findCapableFor($this->order, PaymentProviderCapability::HostedPayment) !== null;
    }

    public function canRequestRefund(): bool
    {
        return $this->order->refundRequest === null
            && $this->order->isRefundable()
            && $this->refundService->canRefund($this->order);
    }
}

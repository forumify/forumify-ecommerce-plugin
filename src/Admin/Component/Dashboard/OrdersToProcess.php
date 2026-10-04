<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\List\AbstractDoctrineList;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

/**
 * @extends AbstractDoctrineList<Order>
 */
#[AsLiveComponent(
    'Ecommerce\\Dashboard\\OrdersToProcess',
    '@ForumifyEcommercePlugin/admin/dashboard/components/orders_to_process.html.twig',
)]
#[IsGranted('ecommerce.admin.dashboard')]
class OrdersToProcess extends AbstractDoctrineList
{
    public function __construct()
    {
        $this->limit = 5;
    }

    protected function getEntityClass(): string
    {
        return Order::class;
    }

    protected function getQuery(): QueryBuilder
    {
        return parent::getQuery()
            ->where('e.status = :status')
            ->setParameter('status', OrderStatus::Paid)
            ->orderBy('e.paidAt', 'ASC')
            ->addOrderBy('e.id', 'ASC');
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Component;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\List\AbstractDoctrineList;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * @extends AbstractDoctrineList<Order>
 */
#[AsLiveComponent('Ecommerce\\MyOrderList', '@ForumifyEcommercePlugin/frontend/components/my_order_list.html.twig')]
#[IsGranted('ROLE_USER')]
class MyOrderList extends AbstractDoctrineList
{
    #[LiveProp]
    public ?Subscription $subscription = null;

    public function __construct(private readonly Security $security)
    {
    }

    protected function getEntityClass(): string
    {
        return Order::class;
    }

    protected function getQuery(): QueryBuilder
    {
        $query = parent::getQuery()
            ->andWhere('e.user = :user')
            ->setParameter('user', $this->security->getUser())
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if ($this->subscription !== null) {
            $query
                ->andWhere('e.subscription = :subscription')
                ->setParameter('subscription', $this->subscription);
        }

        return $query;
    }
}

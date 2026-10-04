<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\List\AbstractDoctrineList;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\RefundRequestStatus;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

/**
 * @extends AbstractDoctrineList<RefundRequest>
 */
#[AsLiveComponent(
    'Ecommerce\\Dashboard\\PendingRefunds',
    '@ForumifyEcommercePlugin/admin/dashboard/components/pending_refunds.html.twig',
)]
#[IsGranted('ecommerce.admin.dashboard')]
class PendingRefunds extends AbstractDoctrineList
{
    public function __construct()
    {
        $this->limit = 5;
    }

    protected function getEntityClass(): string
    {
        return RefundRequest::class;
    }

    protected function getQuery(): QueryBuilder
    {
        return parent::getQuery()
            ->where('e.status = :status')
            ->setParameter('status', RefundRequestStatus::Pending)
            ->orderBy('e.createdAt', 'ASC')
            ->addOrderBy('e.id', 'ASC');
    }
}

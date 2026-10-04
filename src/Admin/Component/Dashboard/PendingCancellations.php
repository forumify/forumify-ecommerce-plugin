<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\List\AbstractDoctrineList;
use Forumify\Ecommerce\Entity\CancellationRequest;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

/**
 * @extends AbstractDoctrineList<CancellationRequest>
 */
#[AsLiveComponent(
    'Ecommerce\\Dashboard\\PendingCancellations',
    '@ForumifyEcommercePlugin/admin/dashboard/components/pending_cancellations.html.twig',
)]
#[IsGranted('ecommerce.admin.dashboard')]
class PendingCancellations extends AbstractDoctrineList
{
    public function __construct()
    {
        $this->limit = 5;
    }

    protected function getEntityClass(): string
    {
        return CancellationRequest::class;
    }

    protected function getQuery(): QueryBuilder
    {
        return parent::getQuery()
            ->where('e.completedAt IS NULL')
            ->orderBy('e.createdAt', 'ASC')
            ->addOrderBy('e.id', 'ASC');
    }
}

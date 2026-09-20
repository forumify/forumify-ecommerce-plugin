<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\RefundRequestStatus;

/**
 * @extends AbstractRepository<RefundRequest>
 */
class RefundRequestRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return RefundRequest::class;
    }

    public function countPending(): int
    {
        return $this->count(['status' => RefundRequestStatus::Pending]);
    }
}

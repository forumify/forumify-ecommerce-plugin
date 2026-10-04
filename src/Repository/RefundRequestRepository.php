<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\RefundRequest;

/**
 * @extends AbstractRepository<RefundRequest>
 */
class RefundRequestRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return RefundRequest::class;
    }
}

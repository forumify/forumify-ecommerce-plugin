<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

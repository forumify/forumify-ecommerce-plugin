<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

enum ProductState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

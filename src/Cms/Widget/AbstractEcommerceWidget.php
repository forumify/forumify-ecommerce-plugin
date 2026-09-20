<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Forumify\Cms\Widget\AbstractWidget;

abstract class AbstractEcommerceWidget extends AbstractWidget
{
    public function getCategory(): string
    {
        return 'ecommerce';
    }
}

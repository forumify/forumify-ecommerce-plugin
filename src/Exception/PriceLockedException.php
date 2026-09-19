<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Exception;

use Forumify\Ecommerce\Entity\ProductPrice;
use LogicException;

class PriceLockedException extends LogicException
{
    public function __construct(public readonly ProductPrice $price)
    {
        parent::__construct('This price has been used in an order and can no longer be changed. Use "Change price" to replace it instead.');
    }
}

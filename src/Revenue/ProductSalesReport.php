<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Money\Money;

/**
 * Sales of a period per product, best seller first, in the store currency.
 */
class ProductSalesReport
{
    /**
     * @param list<ProductSales> $products
     */
    public function __construct(
        public readonly SalesPeriod $period,
        public readonly array $products,
        public readonly Money $revenue,
        public readonly ?Money $previousRevenue,
    ) {
    }

    public function getChange(): ?float
    {
        return self::changeBetween($this->revenue, $this->previousRevenue);
    }

    public static function changeBetween(Money $current, ?Money $previous): ?float
    {
        if ($previous === null || $previous->isZero()) {
            return null;
        }

        return $current->minus($previous)->getAmount()->toFloat() / $previous->getAmount()->toFloat();
    }
}

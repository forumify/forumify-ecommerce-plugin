<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Money\Money;

/**
 * What one product, or one tier of it, sold in a period, in the store currency.
 */
class ProductSales
{
    /**
     * @param float $share part of the period's revenue, 0 to 1
     * @param list<ProductSales> $tiers
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly int $newOrders,
        public readonly int $renewals,
        public readonly Money $revenue,
        public readonly ?Money $previousRevenue,
        public readonly float $share,
        public readonly array $tiers = [],
    ) {
    }

    public function getOrders(): int
    {
        return $this->newOrders + $this->renewals;
    }

    /**
     * Whether it sold in this period but not in the one before.
     */
    public function isNew(): bool
    {
        return $this->previousRevenue?->isZero() ?? false;
    }

    /**
     * The change against the period before, as a ratio. Null when there is nothing to compare against.
     */
    public function getChange(): ?float
    {
        return ProductSalesReport::changeBetween($this->revenue, $this->previousRevenue);
    }
}

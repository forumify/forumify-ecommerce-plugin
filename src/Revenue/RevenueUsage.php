<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;

/**
 * What a store earned over the rolling window, against what its tier allows. Everything in it is in the
 * store currency: the tier's dollar limit is converted before it gets here.
 */
class RevenueUsage
{
    public const WARNING_RATIO = 0.9;

    public function __construct(
        public readonly RevenueTier $tier,
        public readonly RationalMoney $revenue,
        public readonly int $windowMonths,
        public readonly ?Money $cap,
    ) {
    }

    public function getAverage(): Money
    {
        return $this->round($this->revenue);
    }

    /**
     * The average this store lands on with one more order of this amount.
     */
    public function getProjectedAverage(Money $amount): Money
    {
        return $this->round($this->revenue->plus($amount));
    }

    public function allows(Money $amount): bool
    {
        return $this->cap === null || $this->getProjectedAverage($amount)->isLessThanOrEqualTo($this->cap);
    }

    public function getRatio(): float
    {
        $cap = $this->cap?->getAmount()->toFloat() ?? 0.0;

        return $cap > 0.0
            ? $this->getAverage()->getAmount()->toFloat() / $cap
            : 0.0;
    }

    public function getPercentage(): int
    {
        return (int)floor($this->getRatio() * 100);
    }

    public function isNearCap(): bool
    {
        return $this->cap !== null && $this->getRatio() >= self::WARNING_RATIO;
    }

    public function isOverCap(): bool
    {
        return $this->cap !== null && $this->getAverage()->isGreaterThan($this->cap);
    }

    private function round(RationalMoney $total): Money
    {
        return $total
            ->dividedBy($this->windowMonths)
            ->toContext(new DefaultContext(), RoundingMode::HalfUp);
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use DateTimeImmutable;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\StoreSettings;

/**
 * How much a store is allowed to earn, and how close it is to that.
 */
class RevenueCapService
{
    public const WINDOW_MONTHS = 3;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly RevenueTierResolver $tierResolver,
        private readonly StoreSettings $storeSettings,
        private readonly CurrencyConverter $currencyConverter,
    ) {
    }

    public function getUsage(): RevenueUsage
    {
        $currency = $this->storeSettings->getCurrency();
        $tier = $this->tierResolver->getTier();
        $cap = $tier->getMonthlyCap();

        $since = new DateTimeImmutable('-' . self::WINDOW_MONTHS . ' months');

        return new RevenueUsage(
            $tier,
            $this->toStoreCurrency($this->orderRepository->sumRevenueSince($since), $currency),
            self::WINDOW_MONTHS,
            $cap !== null
                ? $this->currencyConverter->convert(Money::of($cap, RevenueTier::CAP_CURRENCY), $currency)
                : null,
        );
    }

    /**
     * Whether an order of this amount would take the store over what its tier allows.
     */
    public function isBlocked(Money $amount): bool
    {
        $usage = $this->getUsage();

        return !$usage->allows($this->currencyConverter->convert(
            $amount,
            $usage->revenue->getCurrency()->getCurrencyCode(),
        ));
    }

    /**
     * Revenue per calendar month, oldest first, months without orders included.
     *
     * @return array<string, Money> "2026-09" => revenue
     */
    public function getMonthlyRevenue(int $months): array
    {
        $currency = $this->storeSettings->getCurrency();
        $start = new DateTimeImmutable('first day of this month midnight')
            ->modify('-' . ($months - 1) . ' months');
        $revenue = $this->orderRepository->sumRevenueByMonth($start);

        $series = [];
        for ($month = 0; $month < $months; $month++) {
            $key = $start->modify("+$month months")->format('Y-m');
            $series[$key] = $this->toStoreCurrency($revenue[$key] ?? [], $currency)
                ->toContext(new DefaultContext(), RoundingMode::HalfUp);
        }

        return $series;
    }

    /**
     * @param array<string, int> $minorPerCurrency
     */
    private function toStoreCurrency(array $minorPerCurrency, string $currency): RationalMoney
    {
        $total = RationalMoney::zero($currency);
        foreach ($minorPerCurrency as $orderCurrency => $minor) {
            $total = $total->plus(
                $this->currencyConverter->convertRational(Money::ofMinor($minor, $orderCurrency), $currency),
            );
        }

        return $total;
    }
}

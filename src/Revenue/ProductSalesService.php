<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use DateTimeImmutable;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\StoreSettings;

/**
 * @phpstan-type Sales array{title: string, lastPaidAt: string, new: int, renewals: int, revenue: RationalMoney}
 * @phpstan-type ProductTotals array{sales: Sales, tiers: array<string, Sales>}
 */
class ProductSalesService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CurrencyConverter $currencyConverter,
        private readonly StoreSettings $storeSettings,
        private readonly TimezoneResolver $timezoneResolver,
    ) {
    }

    public function getReport(SalesPeriod $period): ProductSalesReport
    {
        $currency = $this->storeSettings->getCurrency();
        $now = new DateTimeImmutable('now', $this->timezoneResolver->getTimezone());

        $current = $this->aggregate($period->getRange($now) ?? [null, null], $currency);
        $previousRange = $period->getPreviousRange($now);
        $previous = $previousRange !== null ? $this->aggregate($previousRange, $currency) : null;

        $total = self::sum($current, $currency);
        $products = [];
        foreach ($current as $key => $product) {
            $before = $previous === null ? null : $previous[$key] ?? ['sales' => self::empty($currency), 'tiers' => []];

            $tiers = [];
            foreach ($product['tiers'] as $tierKey => $tier) {
                $previousTier = $before === null ? null : $before['tiers'][$tierKey] ?? self::empty($currency);
                $tiers[] = self::toSales((string)$tierKey, $tier, $previousTier, $total);
            }

            $products[] = self::toSales((string)$key, $product['sales'], $before['sales'] ?? null, $total, $tiers);
        }

        return new ProductSalesReport(
            $period,
            self::sorted($products),
            self::round($total),
            $previous !== null ? self::round(self::sum($previous, $currency)) : null,
        );
    }

    /**
     * @param array{?DateTimeImmutable, ?DateTimeImmutable} $range
     * @return array<string, ProductTotals>
     */
    private function aggregate(array $range, string $currency): array
    {
        $products = [];
        foreach ($this->orderRepository->sumSalesByProduct(...$range) as $row) {
            $revenue = $this->currencyConverter->convertRational(
                Money::ofMinor($row['total'], $row['currency']),
                $currency,
            );

            $product = $products[$row['productKey']] ?? ['sales' => self::empty($currency), 'tiers' => []];
            $product['sales'] = self::add($product['sales'], $row['productTitle'], $row, $revenue);
            $product['tiers'][$row['tierKey']] = self::add(
                $product['tiers'][$row['tierKey']] ?? self::empty($currency),
                $row['tierLabel'],
                $row,
                $revenue,
            );
            $products[$row['productKey']] = $product;
        }

        return $products;
    }

    /**
     * @return Sales
     */
    private static function empty(string $currency): array
    {
        return [
            'title' => '',
            'lastPaidAt' => '',
            'new' => 0,
            'renewals' => 0,
            'revenue' => RationalMoney::zero($currency),
        ];
    }

    /**
     * The title of the most recent sale wins, so a renamed product shows under its current name.
     *
     * @param Sales $sales
     * @param array{renewal: bool, count: int, lastPaidAt: string} $row
     * @return Sales
     */
    private static function add(array $sales, string $title, array $row, RationalMoney $revenue): array
    {
        if ($row['lastPaidAt'] >= $sales['lastPaidAt']) {
            $sales['title'] = $title;
            $sales['lastPaidAt'] = $row['lastPaidAt'];
        }

        $sales[$row['renewal'] ? 'renewals' : 'new'] += $row['count'];
        $sales['revenue'] = $sales['revenue']->plus($revenue);

        return $sales;
    }

    /**
     * @param array<string, ProductTotals>|null $products
     */
    private static function sum(?array $products, string $currency): RationalMoney
    {
        $total = RationalMoney::zero($currency);
        foreach ($products ?? [] as $product) {
            $total = $total->plus($product['sales']['revenue']);
        }

        return $total;
    }

    /**
     * @param Sales $sales
     * @param Sales|null $previous null when there is no period before to compare against
     * @param list<ProductSales> $tiers
     */
    private static function toSales(
        string $key,
        array $sales,
        ?array $previous,
        RationalMoney $total,
        array $tiers = [],
    ): ProductSales {
        return new ProductSales(
            $key,
            $sales['title'],
            $sales['new'],
            $sales['renewals'],
            self::round($sales['revenue']),
            $previous !== null ? self::round($previous['revenue']) : null,
            $total->isZero() ? 0.0 : $sales['revenue']->getAmount()->dividedBy($total->getAmount())->toFloat(),
            self::sorted($tiers),
        );
    }

    /**
     * @param list<ProductSales> $sales
     * @return list<ProductSales>
     */
    private static function sorted(array $sales): array
    {
        usort($sales, static fn(ProductSales $a, ProductSales $b) =>
            $b->revenue->compareTo($a->revenue) ?: $b->getOrders() <=> $a->getOrders());

        return $sales;
    }

    private static function round(RationalMoney $money): Money
    {
        return $money->toContext(new DefaultContext(), RoundingMode::HalfUp);
    }
}

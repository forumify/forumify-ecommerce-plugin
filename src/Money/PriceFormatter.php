<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Money;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\ProductPrice;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class PriceFormatter implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly MoneyFormatter $moneyFormatter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function format(ProductPrice|Order $price): string
    {
        if ($price->isFree()) {
            return $this->translator->trans('ecommerce.price.free');
        }

        $amount = $this->moneyFormatter->format($price->getAmount());
        $interval = $price->billingInterval;
        if ($interval === null) {
            return $amount;
        }

        return $this->translator->trans('ecommerce.price.recurring', [
            'amount' => $amount,
            'count' => $price->intervalCount ?? 1,
            'interval' => $interval->value,
        ]);
    }

    /**
     * @param list<ProductPrice> $prices
     */
    public function formatStartingPrice(array $prices): ?string
    {
        $cheapest = ProductPrice::cheapest($prices);
        if ($cheapest === null) {
            return null;
        }

        if (count($prices) === 1 || $cheapest->isFree()) {
            return $this->format($cheapest);
        }

        return $this->translator->trans('ecommerce.price.starting_at', ['price' => $this->format($cheapest)]);
    }
}

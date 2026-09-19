<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Money;

use Forumify\Ecommerce\Entity\ProductPrice;
use Symfony\Contracts\Translation\TranslatorInterface;

class PriceFormatter
{
    public function __construct(
        private readonly MoneyFormatter $moneyFormatter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function format(ProductPrice $price): string
    {
        $amount = $this->moneyFormatter->format($price->amount);
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
}

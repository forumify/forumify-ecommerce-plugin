<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use LogicException;

class PriceChangeService
{
    public function __construct(
        private readonly ProductPriceRepository $priceRepository,
        private readonly StoreSettings $storeSettings,
    ) {
    }

    public function canChange(ProductPrice $price): bool
    {
        return $price->locked && $price->replacedBy === null;
    }

    public function createReplacement(ProductPrice $price): ProductPrice
    {
        if (!$this->canChange($price)) {
            throw new LogicException('Only locked prices that have not been replaced yet can be changed.');
        }

        $replacement = new ProductPrice($price->tier, $this->storeSettings->getCurrency());
        $replacement->type = $price->type;
        $replacement->billingInterval = $price->billingInterval;
        $replacement->intervalCount = $price->intervalCount;
        if ($replacement->currency === $price->currency) {
            $replacement->amountMinor = $price->amountMinor;
        }

        return $replacement;
    }

    public function changePrice(ProductPrice $price, ProductPrice $replacement): void
    {
        if (!$this->canChange($price)) {
            throw new LogicException('Only locked prices that have not been replaced yet can be changed.');
        }

        $this->priceRepository->save($replacement, false);
        $price->replaceWith($replacement);
        $this->priceRepository->save($price);
    }
}

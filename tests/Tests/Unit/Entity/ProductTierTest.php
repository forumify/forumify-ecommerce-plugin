<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use PHPUnit\Framework\TestCase;

class ProductTierTest extends TestCase
{
    public function testStorefrontOnlyOffersActivePrices(): void
    {
        $product = new Product();
        $free = $this->tier($product, [0]);
        $premium = $this->tier($product, [1500, 999, 500]);
        $retired = $this->tier($product, [100]);

        $premium->prices[2]->active = false;
        $retired->prices[0]->active = false;

        self::assertSame([1500, 999], array_map(fn(ProductPrice $p) => $p->amountMinor, $premium->getActivePrices()));
        self::assertSame(999, $premium->getCheapestPrice()?->amountMinor);
        self::assertNull($retired->getCheapestPrice());

        self::assertSame([$free, $premium], $product->getPurchasableTiers());
        self::assertSame($free->prices[0], $product->getCheapestPrice());
    }

    /**
     * @param list<int> $amounts
     */
    private function tier(Product $product, array $amounts): ProductTier
    {
        $tier = new ProductTier($product);
        $product->tiers->add($tier);
        foreach ($amounts as $amount) {
            $price = new ProductPrice($tier, 'USD');
            $price->amountMinor = $amount;
            $tier->prices->add($price);
        }

        return $tier;
    }
}

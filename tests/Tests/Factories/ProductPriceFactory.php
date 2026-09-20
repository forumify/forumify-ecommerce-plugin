<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\ProductPrice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductPrice>
 */
class ProductPriceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductPrice::class;
    }

    public function recurring(BillingInterval $interval = BillingInterval::Month, int $count = 1): static
    {
        return $this->with([
            'type' => PriceType::Recurring,
            'billingInterval' => $interval,
            'intervalCount' => $count,
        ]);
    }

    public function locked(): static
    {
        return $this->afterInstantiate(static fn(ProductPrice $price) => $price->lock());
    }

    protected function defaults(): array|callable
    {
        return [
            'tier' => ProductTierFactory::new(),
            'currency' => 'USD',
            'amountMinor' => self::faker()->numberBetween(100, 10000),
        ];
    }
}

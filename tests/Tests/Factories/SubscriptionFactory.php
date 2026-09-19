<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use DateTimeImmutable;
use Forumify\Ecommerce\Entity\Subscription;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Subscription>
 */
class SubscriptionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Subscription::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'initialOrder' => OrderFactory::new(['price' => ProductPriceFactory::new()->recurring()])->completed(),
            'providerReference' => 'fake_sub_' . self::faker()->unique()->bothify('????????'),
            'currentPeriodEnd' => new DateTimeImmutable('+1 month'),
        ];
    }
}

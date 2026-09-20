<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Testing\Factories\Core\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Order>
 */
class OrderFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Order::class;
    }

    public function paid(): static
    {
        return $this->afterInstantiate(static fn(Order $order) => $order->pay());
    }

    public function completed(): static
    {
        return $this->afterInstantiate(static fn(Order $order) => $order->pay() && $order->complete());
    }

    protected function defaults(): array|callable
    {
        return [
            'user' => UserFactory::new(),
            'price' => ProductPriceFactory::new(),
            'providerKey' => FakePaymentProvider::KEY,
        ];
    }
}

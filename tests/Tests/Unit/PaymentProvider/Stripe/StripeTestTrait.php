<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\PaymentProvider\Stripe;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use LogicException;
use ReflectionProperty;
use Stripe\StripeClient;

trait StripeTestTrait
{
    /**
     * @param array<string, object> $services
     */
    private function mockStripeClient(array $services): StripeClient
    {
        $client = $this->createMock(StripeClient::class);
        $client->method('__get')->willReturnCallback(
            static fn(string $name) => $services[$name] ?? throw new LogicException("Unexpected Stripe service {$name}."),
        );

        return $client;
    }

    private function createPrice(int $amountMinor = 999, ?BillingInterval $interval = null, ?ProductTier $tier = null): ProductPrice
    {
        if ($tier === null) {
            $product = self::withId(new Product(), 3);
            $product->title = 'Milsim';
            $product->key = 'milsim';
            $tier = new ProductTier($product);
            $tier->key = 'premium';
            $tier->label = 'Premium';
        }

        $price = self::withId(new ProductPrice($tier, 'EUR'), 7 + $tier->prices->count());
        $price->amountMinor = $amountMinor;
        if ($interval !== null) {
            $price->type = PriceType::Recurring;
            $price->billingInterval = $interval;
            $price->intervalCount = 1;
        }
        $tier->prices->add($price);

        return $price;
    }

    private function createUser(): User
    {
        $user = self::withId(new User(), 42);
        $user->setUsername('jannes');
        $user->setEmail('jannes@example.com');

        return $user;
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private static function withId(object $entity, int $id): object
    {
        new ReflectionProperty($entity, 'id')->setValue($entity, $id);

        return $entity;
    }
}

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Factories;

use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PaymentProviderConfig>
 */
class PaymentProviderConfigFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PaymentProviderConfig::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'providerKey' => FakePaymentProvider::KEY,
            'enabled' => true,
        ];
    }
}

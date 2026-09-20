<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<PaymentProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(PaymentProviderInterface::TAG)]
        iterable $providers,
        private readonly PaymentProviderConfigRepository $configRepository,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getKey()] = $provider;
        }
    }

    /**
     * @return list<PaymentProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function get(string $key): ?PaymentProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function getConfig(PaymentProviderInterface $provider): PaymentProviderConfig
    {
        $key = $provider->getKey();

        return $this->configRepository->findAllByKey()[$key] ?? new PaymentProviderConfig($key);
    }

    public function isConfigured(PaymentProviderInterface $provider): bool
    {
        return $provider->isConfigured($this->getConfig($provider));
    }

    public function findConfigured(string $key): ?PaymentProviderInterface
    {
        $provider = $this->get($key);

        return $provider !== null && $this->isConfigured($provider) ? $provider : null;
    }

    /**
     * The provider that settled an order, if it is configured and can do what is being asked of it.
     */
    public function findCapableFor(Order $order, PaymentProviderCapability $capability): ?PaymentProviderInterface
    {
        $provider = $order->providerKey === null ? null : $this->findConfigured($order->providerKey);

        return $provider !== null && in_array($capability, $provider->getCapabilities(), true)
            ? $provider
            : null;
    }

    /**
     * @return list<PaymentProviderInterface>
     */
    public function findAvailableFor(ProductPrice $price): array
    {
        $configs = $this->configRepository->findAllByKey();

        $available = [];
        foreach ($this->providers as $key => $provider) {
            $config = $configs[$key] ?? null;
            if ($config === null || !$config->enabled) {
                continue;
            }

            if ($provider->isConfigured($config) && $provider->supports($price)) {
                $available[] = $provider;
            }
        }

        return $available;
    }
}

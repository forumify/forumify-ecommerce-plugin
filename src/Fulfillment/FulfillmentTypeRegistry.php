<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class FulfillmentTypeRegistry
{
    /** @var array<string, FulfillmentTypeInterface> */
    private array $types = [];

    /**
     * @param iterable<FulfillmentTypeInterface> $types
     */
    public function __construct(
        #[AutowireIterator(FulfillmentTypeInterface::TAG)]
        iterable $types,
    ) {
        foreach ($types as $type) {
            $this->types[$type::getKey()] = $type;
        }
    }

    /**
     * @return array<string, FulfillmentTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }

    public function get(string $key): ?FulfillmentTypeInterface
    {
        return $this->types[$key] ?? null;
    }

    public function requiresManualFulfillment(string $key): bool
    {
        return $this->get($key)?->requiresManualFulfillment() ?? true;
    }

    public function getLabel(string $key): string
    {
        return $this->get($key)?->getLabel() ?? $key;
    }
}

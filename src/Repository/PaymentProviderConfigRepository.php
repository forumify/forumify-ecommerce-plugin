<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;

/**
 * @extends AbstractRepository<PaymentProviderConfig>
 */
class PaymentProviderConfigRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return PaymentProviderConfig::class;
    }

    /**
     * @return array<string, PaymentProviderConfig>
     */
    public function findAllByKey(): array
    {
        $configs = [];
        foreach ($this->findAll() as $config) {
            $configs[$config->providerKey] = $config;
        }

        return $configs;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\Role;
use Forumify\Core\Repository\RoleRepository;
use Forumify\Core\Repository\SettingRepository;

class StoreSettings
{
    public const CURRENCY = 'ecommerce.currency';
    public const STORE_ROLE = 'ecommerce.store_role';
    public const DEFAULT_CURRENCY = 'USD';
    public const SUPPORTED_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'INR',
        'JPY', 'KRW', 'MXN', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD', 'ZAR',
    ];

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    public function getCurrency(): string
    {
        $currency = $this->settingRepository->get(self::CURRENCY);

        return is_string($currency) && $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * The role that runs the store. It is notified about the store itself, not about single orders.
     */
    public function getStoreRole(): ?Role
    {
        $role = $this->settingRepository->get(self::STORE_ROLE);
        if (!is_int($role) && !is_string($role)) {
            return null;
        }

        return $this->roleRepository->find((int)$role);
    }
}

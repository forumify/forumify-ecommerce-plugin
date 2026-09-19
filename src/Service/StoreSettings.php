<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Repository\SettingRepository;

class StoreSettings
{
    public const CURRENCY = 'ecommerce.currency';
    public const DEFAULT_CURRENCY = 'USD';
    public const SUPPORTED_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'INR',
        'JPY', 'KRW', 'MXN', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD', 'ZAR',
    ];

    public function __construct(private readonly SettingRepository $settingRepository)
    {
    }

    public function getCurrency(): string
    {
        $currency = $this->settingRepository->get(self::CURRENCY);

        return is_string($currency) && $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }
}

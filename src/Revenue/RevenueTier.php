<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

/**
 * The plugin's subscription versions, each with the monthly revenue it allows.
 */
enum RevenueTier: string
{
    public const CAP_CURRENCY = 'USD';

    case Hobbyist = 'hobbyist';
    case Merchant = 'merchant';
    case Marketplace = 'marketplace';

    /**
     * The highest monthly revenue this tier allows, in whole US dollars. Null means uncapped.
     */
    public function getMonthlyCap(): ?int
    {
        return match ($this) {
            self::Hobbyist => 250,
            self::Merchant => 5000,
            self::Marketplace => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Money;

use Brick\Money\Money;
use Locale;
use Twig\Extension\RuntimeExtensionInterface;

class MoneyFormatter implements RuntimeExtensionInterface
{
    public function format(Money $money, ?string $locale = null): string
    {
        return $money->formatToLocale($locale ?? Locale::getDefault());
    }
}

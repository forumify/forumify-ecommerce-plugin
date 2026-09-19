<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Twig;

use Forumify\Ecommerce\Money\MoneyFormatter;
use Forumify\Ecommerce\Money\PriceFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ecommerce_money', [MoneyFormatter::class, 'format']),
            new TwigFilter('ecommerce_money_parts', [MoneyFormatter::class, 'formatParts']),
            new TwigFilter('ecommerce_price', [PriceFormatter::class, 'format']),
            new TwigFilter('ecommerce_starting_price', [PriceFormatter::class, 'formatStartingPrice']),
        ];
    }
}

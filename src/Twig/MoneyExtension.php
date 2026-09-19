<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Twig;

use Forumify\Ecommerce\Money\MoneyFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ecommerce_money', [MoneyFormatter::class, 'format']),
        ];
    }
}

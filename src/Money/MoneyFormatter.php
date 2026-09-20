<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Money;

use Brick\Money\Money;
use Locale;
use NumberFormatter;
use Twig\Extension\RuntimeExtensionInterface;

class MoneyFormatter implements RuntimeExtensionInterface
{
    public function format(Money $money, ?string $locale = null): string
    {
        return $money->formatToLocale($locale ?? Locale::getDefault());
    }

    /**
     * @return array{whole: string, fraction: string, rest: string}
     */
    public function formatParts(Money $money, ?string $locale = null): array
    {
        $locale ??= Locale::getDefault();
        $formatted = $this->format($money, $locale);
        $scale = $money->getAmount()->getScale();
        $separator = (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->getSymbol(NumberFormatter::MONETARY_SEPARATOR_SYMBOL);
        $position = $scale > 0 && $separator !== false ? mb_strrpos($formatted, $separator) : false;
        if ($position === false) {
            return ['whole' => $formatted, 'fraction' => '', 'rest' => ''];
        }

        $fractionEnd = $position + mb_strlen($separator) + $scale;

        return [
            'whole' => mb_substr($formatted, 0, $position),
            'fraction' => mb_substr($formatted, $position, $fractionEnd - $position),
            'rest' => mb_substr($formatted, $fractionEnd),
        ];
    }
}

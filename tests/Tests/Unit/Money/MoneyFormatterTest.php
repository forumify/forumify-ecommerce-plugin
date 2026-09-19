<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Money;

use Brick\Money\Money;
use Forumify\Ecommerce\Money\MoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    #[DataProvider('formatProvider')]
    public function testFormatUsesCurrencyFractionDigitsAndLocale(Money $money, string $locale, string $expected): void
    {
        $formatted = (new MoneyFormatter())->format($money, $locale);

        self::assertSame($expected, str_replace(["\u{a0}", "\u{202f}"], ' ', $formatted));
    }

    /**
     * @return iterable<string, array{Money, string, string}>
     */
    public static function formatProvider(): iterable
    {
        yield 'two decimals' => [Money::ofMinor(123456, 'USD'), 'en', '$1,234.56'];
        yield 'zero decimals' => [Money::ofMinor(1500, 'JPY'), 'en', '¥1,500'];
        yield 'locale aware' => [Money::ofMinor(123456, 'EUR'), 'nl', '€ 1.234,56'];
        yield 'free' => [Money::zero('EUR'), 'en', '€0.00'];
    }
}

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Money;

use Brick\Money\Money;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CurrencyConverterTest extends TestCase
{
    public function testAmountsAreConvertedThroughTheDollarRates(): void
    {
        $converter = $this->createConverter(['EUR' => '0.872600000000', 'JPY' => '157.890000000000']);

        self::assertSame('EUR 87.26', (string)$converter->convert(Money::of(100, 'USD'), 'EUR'));
        self::assertSame('USD 100.00', (string)$converter->convert(Money::of('87.26', 'EUR'), 'USD'));
        self::assertSame('JPY 18094', (string)$converter->convert(Money::of(100, 'EUR'), 'JPY'));
    }

    public function testAnUnknownCurrencyFallsBackToFaceValue(): void
    {
        $converter = $this->createConverter([]);

        self::assertSame('EUR 100.00', (string)$converter->convert(Money::of(100, 'USD'), 'EUR'));
    }

    /**
     * @param array<string, string> $rates
     */
    private function createConverter(array $rates): CurrencyConverter
    {
        $exchangeRates = $this->createMock(ExchangeRateRepository::class);
        $exchangeRates->method('findAllRates')->willReturn($rates);

        return new CurrencyConverter($exchangeRates, new NullLogger());
    }
}

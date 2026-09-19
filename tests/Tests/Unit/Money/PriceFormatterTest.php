<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Money;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Money\MoneyFormatter;
use Forumify\Ecommerce\Money\PriceFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class PriceFormatterTest extends TestCase
{
    private PriceFormatter $formatter;
    private ProductTier $tier;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../../translations/messages+intl-icu.en.yaml', 'en', 'messages+intl-icu');

        $this->formatter = new PriceFormatter(new MoneyFormatter(), $translator);
        $this->tier = new ProductTier(new Product());
    }

    public function testFormatsSinglePrices(): void
    {
        self::assertSame('Free', $this->formatter->format($this->price(0)));
        self::assertSame('$25.00', $this->formatter->format($this->price(2500)));
        self::assertSame('$9.99 per month', $this->formatter->format($this->price(999, BillingInterval::Month)));
        self::assertSame('$99.00 every 3 years', $this->formatter->format($this->price(9900, BillingInterval::Year, 3)));
    }

    public function testStartingPriceIsTheCheapestPrice(): void
    {
        $monthly = $this->price(999, BillingInterval::Month);
        $yearly = $this->price(9900, BillingInterval::Year);
        $lifetime = $this->price(19900);

        self::assertNull($this->formatter->formatStartingPrice([]));
        self::assertSame('$199.00', $this->formatter->formatStartingPrice([$lifetime]));
        self::assertSame('From $9.99 per month', $this->formatter->formatStartingPrice([$lifetime, $yearly, $monthly]));
        self::assertSame('Free', $this->formatter->formatStartingPrice([$monthly, $this->price(0)]));
    }

    private function price(int $amountMinor, ?BillingInterval $interval = null, int $count = 1): ProductPrice
    {
        $price = new ProductPrice($this->tier, 'USD');
        $price->amountMinor = $amountMinor;
        if ($interval !== null) {
            $price->type = PriceType::Recurring;
            $price->billingInterval = $interval;
            $price->intervalCount = $count;
        }

        return $price;
    }
}

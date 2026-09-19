<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use Forumify\Ecommerce\Service\PriceChangeService;
use Forumify\Ecommerce\Service\StoreSettings;
use LogicException;
use PHPUnit\Framework\TestCase;

class PriceChangeServiceTest extends TestCase
{
    public function testChangePriceReplacesLockedPrice(): void
    {
        $price = $this->createMonthlyPrice('EUR', 1000);
        $price->lock();

        $repository = $this->createMock(ProductPriceRepository::class);
        $service = new PriceChangeService($repository, $this->storeSettings('EUR'));

        $replacement = $service->createReplacement($price);
        self::assertSame($price->tier, $replacement->tier);
        self::assertSame(PriceType::Recurring, $replacement->type);
        self::assertSame(BillingInterval::Month, $replacement->billingInterval);
        self::assertSame(1, $replacement->intervalCount);
        self::assertSame(1000, $replacement->amountMinor);
        self::assertFalse($replacement->locked);

        $replacement->amountMinor = 1200;
        $repository->expects(self::exactly(2))->method('save');
        $service->changePrice($price, $replacement);

        self::assertFalse($price->active);
        self::assertSame($replacement, $price->replacedBy);
        self::assertFalse($service->canChange($price));
    }

    public function testReplacementUsesCurrentStoreCurrency(): void
    {
        $price = $this->createMonthlyPrice('USD', 1000);
        $price->lock();

        $service = new PriceChangeService($this->createMock(ProductPriceRepository::class), $this->storeSettings('EUR'));
        $replacement = $service->createReplacement($price);

        self::assertSame('EUR', $replacement->currency);
        self::assertSame(0, $replacement->amountMinor);
    }

    public function testUnlockedPriceCannotBeChanged(): void
    {
        $service = new PriceChangeService($this->createMock(ProductPriceRepository::class), $this->storeSettings('USD'));

        $this->expectException(LogicException::class);
        $service->createReplacement($this->createMonthlyPrice('USD', 1000));
    }

    private function createMonthlyPrice(string $currency, int $amount): ProductPrice
    {
        $price = new ProductPrice(new ProductTier(new Product()), $currency);
        $price->type = PriceType::Recurring;
        $price->billingInterval = BillingInterval::Month;
        $price->intervalCount = 1;
        $price->amountMinor = $amount;

        return $price;
    }

    private function storeSettings(string $currency): StoreSettings
    {
        $storeSettings = $this->createMock(StoreSettings::class);
        $storeSettings->method('getCurrency')->willReturn($currency);

        return $storeSettings;
    }
}

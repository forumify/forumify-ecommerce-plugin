<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Entity;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Exception\PriceLockedException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class ProductPriceTest extends TestCase
{
    /**
     * @return iterable<string, array{PriceType, BillingInterval|null, int|null, int, array<string>}>
     */
    public static function priceProvider(): iterable
    {
        yield 'free one-time' => [PriceType::OneTime, null, null, 0, []];
        yield 'monthly' => [PriceType::Recurring, BillingInterval::Month, 1, 999, []];
        yield 'recurring without interval' => [PriceType::Recurring, null, null, 999, ['billingInterval', 'intervalCount']];
        yield 'one-time with interval' => [PriceType::OneTime, BillingInterval::Year, 1, 999, ['billingInterval']];
        yield 'zero interval count' => [PriceType::Recurring, BillingInterval::Week, 0, 999, ['intervalCount']];
        yield 'negative amount' => [PriceType::OneTime, null, null, -1, ['amountMinor']];
    }

    /**
     * @param array<string> $expectedViolations
     */
    #[DataProvider('priceProvider')]
    public function testPriceValidation(
        PriceType $type,
        ?BillingInterval $interval,
        ?int $intervalCount,
        int $amount,
        array $expectedViolations,
    ): void {
        $price = $this->createPrice();
        $price->type = $type;
        $price->billingInterval = $interval;
        $price->intervalCount = $intervalCount;
        $price->amountMinor = $amount;

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($price);

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        self::assertEqualsCanonicalizing($expectedViolations, $paths);
    }

    public function testLockedPriceOnlyAllowsTogglingActive(): void
    {
        $price = $this->createPrice();
        $price->amountMinor = 500;
        $price->lock();

        $price->active = false;
        self::assertFalse($price->active);

        $this->expectException(PriceLockedException::class);
        $price->amountMinor = 600;
    }

    public function testReplacementDeactivatesPrice(): void
    {
        $price = $this->createPrice();
        $replacement = new ProductPrice($price->tier, 'USD');

        $price->replaceWith($replacement);

        self::assertFalse($price->active);
        self::assertSame($replacement, $price->replacedBy);

        $this->expectException(InvalidArgumentException::class);
        $price->replaceWith($this->createPrice());
    }

    private function createPrice(): ProductPrice
    {
        return new ProductPrice(new ProductTier(new Product()), 'USD');
    }
}

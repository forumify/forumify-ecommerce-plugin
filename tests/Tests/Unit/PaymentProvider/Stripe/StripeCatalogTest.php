<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\PaymentProvider\Stripe;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProviderCustomer;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripeCatalog;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripeSettings;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use Forumify\Ecommerce\Repository\ProviderCustomerRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\Service\CustomerService;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;

class StripeCatalogTest extends TestCase
{
    use StripeTestTrait;

    private PriceService&MockObject $prices;
    private ProductService&MockObject $products;
    private CustomerService&MockObject $customers;
    private ProviderCustomerRepository&MockObject $customerRepository;
    private StripeCatalog $catalog;

    protected function setUp(): void
    {
        $this->prices = $this->createMock(PriceService::class);
        $this->products = $this->createMock(ProductService::class);
        $this->customers = $this->createMock(CustomerService::class);
        $this->customerRepository = $this->createMock(ProviderCustomerRepository::class);

        $this->catalog = new StripeCatalog(
            $this->createMock(ProductTierRepository::class),
            $this->createMock(ProductPriceRepository::class),
            $this->customerRepository,
            new NullLogger(),
        );
    }

    public function testFirstSyncCreatesProductAndPriceThenReusesThem(): void
    {
        $price = $this->createPrice(999, BillingInterval::Month);
        $this->products->expects(self::once())->method('create')->with([
            'name' => 'Milsim – Premium',
            'metadata' => ['ecommerce_product_id' => '3', 'ecommerce_product_key' => 'milsim', 'ecommerce_tier_key' => 'premium'],
        ])->willReturn(Product::constructFrom(['id' => 'prod_1']));
        $this->prices->expects(self::once())->method('create')->with([
            'product' => 'prod_1',
            'currency' => 'eur',
            'unit_amount' => 999,
            'metadata' => ['ecommerce_product_id' => '3', 'ecommerce_tier_key' => 'premium', 'ecommerce_price_id' => '7'],
            'recurring' => ['interval' => 'month', 'interval_count' => 1],
        ])->willReturn(Price::constructFrom(['id' => 'price_1']));
        $this->prices->expects(self::never())->method('update');

        $settings = new StripeSettings('sk_live_abc', 'whsec_abc');
        self::assertSame('price_1', $this->sync($settings, $price));
        self::assertSame('price_1', $this->sync($settings, $price));
        self::assertSame(['stripe' => 'price_1'], $price->providerReferences);
        self::assertSame(['stripe' => 'prod_1'], $price->tier->providerReferences);
    }

    public function testTestModeKeepsItsOwnReferences(): void
    {
        $price = $this->createPrice();
        $price->providerReferences = ['stripe' => 'price_live'];
        $price->tier->providerReferences = ['stripe' => 'prod_live'];
        $price->tier->label = 'Milsim';
        $this->products->expects(self::once())->method('create')
            ->with(self::callback(static fn(array $params) => $params['name'] === 'Milsim'))
            ->willReturn(Product::constructFrom(['id' => 'prod_test']));
        $this->prices->expects(self::once())->method('create')
            ->with(self::callback(static fn(array $params) => $params['product'] === 'prod_test' && !isset($params['recurring'])))
            ->willReturn(Price::constructFrom(['id' => 'price_test']));

        self::assertSame('price_test', $this->sync(new StripeSettings('sk_test_abc', 'whsec_abc'), $price));
        self::assertSame(['stripe' => 'price_live', 'stripe_test' => 'price_test'], $price->providerReferences);
    }

    public function testReplacementArchivesSyncedPredecessors(): void
    {
        $original = $this->createPrice(500);
        $original->providerReferences = ['stripe' => 'price_original'];
        $unsynced = $this->createPrice(700, tier: $original->tier);
        $replacement = $this->createPrice(900, tier: $original->tier);
        $original->replaceWith($unsynced);
        $unsynced->replaceWith($replacement);
        $original->tier->providerReferences = ['stripe' => 'prod_1'];

        $this->products->expects(self::never())->method('create');
        $this->prices->method('create')->willReturn(Price::constructFrom(['id' => 'price_new']));
        $this->prices->expects(self::once())->method('update')->with('price_original', ['active' => false]);

        self::assertSame('price_new', $this->sync(new StripeSettings('sk_live_abc', 'whsec_abc'), $replacement));
    }

    public function testCustomerIsCreatedOnceAndReused(): void
    {
        $user = $this->createUser();
        $settings = new StripeSettings('sk_live_abc', 'whsec_abc');
        $client = $this->mockStripeClient(['customers' => $this->customers]);

        $this->customers->expects(self::once())->method('create')->with([
            'email' => 'jannes@example.com',
            'name' => 'jannes',
            'metadata' => ['forumify_user_id' => '42', 'forumify_username' => 'jannes'],
        ])->willReturn(Customer::constructFrom(['id' => 'cus_1']));
        $this->customerRepository->method('findOneFor')->willReturnOnConsecutiveCalls(null, new ProviderCustomer($user, 'stripe', 'cus_1'));
        $this->customerRepository->expects(self::once())->method('save')->with(self::callback(
            static fn(ProviderCustomer $customer) => $customer->externalId === 'cus_1' && $customer->providerKey === 'stripe',
        ));

        self::assertSame('cus_1', $this->catalog->syncCustomer($client, $settings, $user));
        self::assertSame('cus_1', $this->catalog->syncCustomer($client, $settings, $user));
    }

    private function sync(StripeSettings $settings, ProductPrice $price): string
    {
        $client = $this->mockStripeClient(['prices' => $this->prices, 'products' => $this->products]);

        return $this->catalog->syncPrice($client, $settings, $price);
    }
}

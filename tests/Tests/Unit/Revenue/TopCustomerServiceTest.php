<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Revenue;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Entity\ExchangeRate;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Revenue\SalesPeriod;
use Forumify\Ecommerce\Revenue\TopCustomer;
use Forumify\Ecommerce\Revenue\TopCustomerService;
use Forumify\Ecommerce\Service\StoreSettings;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The ranking happens in SQL, so this runs against the database instead of mocks.
 */
class TopCustomerServiceTest extends KernelTestCase
{
    use Factories;

    public function testCustomersAreRankedAcrossCurrenciesAndPeriods(): void
    {
        self::getContainer()->get(SettingRepository::class)->set(StoreSettings::CURRENCY, 'USD');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $rate = $em->find(ExchangeRate::class, 'EUR') ?? new ExchangeRate('EUR', '0.5', new DateTimeImmutable());
        $rate->rate = '0.5';
        $em->persist($rate);
        $em->flush();

        $alice = UserFactory::createOne(['username' => 'alice']);
        $bob = UserFactory::createOne(['username' => 'bob']);
        $dave = UserFactory::createOne(['username' => 'dave']);

        $this->order($alice, 3000, 'EUR');
        $this->order($bob, 2000, 'USD');
        $this->order($bob, 2000, 'USD');
        $this->order($dave, 10000, 'USD', new DateTimeImmutable('-2 years'));
        OrderFactory::new()
            ->afterInstantiate(static fn(Order $order) => $order->pay() && $order->refund())
            ->create(['price' => ProductPriceFactory::new(['amountMinor' => 99999, 'currency' => 'USD'])]);
        OrderFactory::new()
            ->paid()
            ->create(['user' => null, 'price' => ProductPriceFactory::new(['amountMinor' => 99999, 'currency' => 'USD'])]);

        $service = self::getContainer()->get(TopCustomerService::class);

        $allTime = $service->getTopCustomers(SalesPeriod::AllTime, 10);
        self::assertSame(['dave', 'alice', 'bob'], self::names($allTime));
        self::assertSame('USD 60.00', (string)$allTime[1]->revenue);
        self::assertSame(2, $allTime[2]->orders);
        self::assertSame(
            new DateTimeImmutable('-2 years')->format('Y-m'),
            $allTime[0]->customerSince->format('Y-m'),
        );

        self::assertSame(['alice', 'bob'], self::names($service->getTopCustomers(SalesPeriod::ThisMonth, 10)));
        self::assertSame(['dave'], self::names($service->getTopCustomers(SalesPeriod::AllTime, 1)));
    }

    private function order(User $user, int $amountMinor, string $currency, ?DateTimeImmutable $paidAt = null): void
    {
        OrderFactory::new()->paid($paidAt)->create([
            'user' => $user,
            'price' => ProductPriceFactory::new(['amountMinor' => $amountMinor, 'currency' => $currency]),
        ]);
    }

    /**
     * @param list<TopCustomer> $customers
     * @return list<string>
     */
    private static function names(array $customers): array
    {
        return array_map(static fn(TopCustomer $customer) => $customer->user->getUsername(), $customers);
    }
}

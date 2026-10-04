<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Service\StoreSettings;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use PluginTests\Tests\Factories\SubscriptionFactory;

class DashboardTest extends EcommerceWebTestCase
{
    public function testTheDashboardShowsTheWorkWaitingAndHowTheStoreIsDoing(): void
    {
        self::getContainer()->get(SettingRepository::class)->set(StoreSettings::CURRENCY, 'USD');
        $lastMonth = new DateTimeImmutable('first day of last month noon')->modify('+4 days');
        $alice = UserFactory::createOne(['username' => 'alice']);
        $bob = UserFactory::createOne(['username' => 'bob']);
        $carol = UserFactory::createOne(['username' => 'carol']);

        $vip = ProductFactory::createOne(['title' => 'VIP rank']);
        $monthly = $this->createPrice($vip, 1000, BillingInterval::Month);
        $yearly = $this->createPrice($vip, 12000, BillingInterval::Year);
        $hoodie = ProductPriceFactory::createOne([
            'tier' => ProductTierFactory::new(['product' => ProductFactory::new(['title' => 'Hoodie'])]),
            'amountMinor' => 2500,
        ]);

        $active = $this->createSubscription($alice, $monthly, $lastMonth);
        OrderFactory::new()
            ->paid($lastMonth)
            ->create(['user' => $alice, 'price' => $monthly, 'subscription' => $active]);
        SubscriptionFactory::new()
            ->pastDueSince(new DateTimeImmutable('-2 days'))
            ->create(['initialOrder' => OrderFactory::new(['user' => $alice, 'price' => $yearly])->completed()]);

        $toProcess = OrderFactory::new()->paid($lastMonth)->create(['user' => $bob, 'price' => $hoodie]);
        OrderFactory::new()
            ->afterInstantiate(static fn(Order $order) => $order->pay() && $order->refund())
            ->create(['user' => $carol, 'price' => $hoodie]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist(new RefundRequest($toProcess, $bob, 'It does not fit.'));
        SubscriptionFactory::new()
            ->afterInstantiate(static fn(Subscription $subscription) =>
                $subscription->requestCancellation() && $subscription->cancel())
            ->create(['initialOrder' => OrderFactory::new(['price' => $monthly])->completed()]);
        $em->flush();

        $this->client->request('GET', '/admin/ecommerce/');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.ecommerce-dashboard-orders', $toProcess->reference);
        self::assertSelectorTextContains('.ecommerce-dashboard-orders', 'Stuck');
        self::assertSelectorTextContains('.ecommerce-dashboard-refunds', 'It does not fit.');
        self::assertSelectorTextContains('.ecommerce-dashboard-cancellations', 'VIP rank');

        self::assertSelectorTextContains('.ecommerce-dashboard-mrr', '$20.00');
        self::assertSelectorTextContains('.ecommerce-dashboard-mrr', '$10.00 at risk, 1 subscription past due');
        self::assertSelectorTextContains('.ecommerce-dashboard-subscriptions', '2');

        $products = $this->client->getCrawler()->filter('.ecommerce-dashboard-products tbody tr');
        self::assertCount(2, $products);
        self::assertStringContainsString('Hoodie', $products->eq(0)->text());
        self::assertStringContainsString('$25.00', $products->eq(0)->text());
        self::assertStringContainsString('VIP rank', $products->eq(1)->text());
        self::assertStringContainsString('1 new, 1 renewal', $products->eq(1)->text());

        $customers = $this->client->getCrawler()->filter('.ecommerce-dashboard-customers tbody tr')->each(
            static fn($row) => $row->text(),
        );
        self::assertStringContainsString('alice', $customers[0]);
        self::assertStringContainsString('$140.00', $customers[0]);
        self::assertStringContainsString('2 active', $customers[0]);
        self::assertStringContainsString('bob', $customers[1]);
        self::assertStringNotContainsString('carol', implode(' ', $customers));

        self::assertSelectorExists('a[href="/admin/ecommerce/"]');
        self::assertSelectorTextNotContains('nav, aside', 'Orders (');
    }

    public function testTheDashboardNeedsItsOwnPermission(): void
    {
        $role = RoleFactory::createOne([
            'administrator' => true,
            'permissions' => ['ecommerce.admin.view', 'ecommerce.admin.orders.view'],
        ]);
        $this->client->loginUser(UserFactory::createOne(['roleEntities' => new ArrayCollection([$role])]));

        $this->client->request('GET', '/admin/ecommerce/orders');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav, aside', 'Orders');
        self::assertSelectorNotExists('a[href="/admin/ecommerce/"]');

        $this->client->request('GET', '/admin/ecommerce/');
        self::assertResponseStatusCodeSame(403);
    }

    private function createPrice(object $product, int $amountMinor, BillingInterval $interval): ProductPrice
    {
        return ProductPriceFactory::new()
            ->recurring($interval)
            ->create(['tier' => ProductTierFactory::new(['product' => $product]), 'amountMinor' => $amountMinor]);
    }

    private function createSubscription(User $user, ProductPrice $price, DateTimeImmutable $paidAt): Subscription
    {
        return SubscriptionFactory::createOne([
            'initialOrder' => OrderFactory::new(['user' => $user, 'price' => $price])->paid($paidAt),
        ]);
    }
}

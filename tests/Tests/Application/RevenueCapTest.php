<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Notification\RevenueCapReachedNotificationType;
use Forumify\Ecommerce\Notification\RevenueCapWarningNotificationType;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\StoreSettings;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;

class RevenueCapTest extends EcommerceWebTestCase
{
    public function testCheckoutIsDisabledOverTheRevenueCap(): void
    {
        $manager = $this->createStoreManager();
        $this->createRevenue(80000);
        $price = $this->createPrice();

        $this->client->loginUser(UserFactory::createOne());
        $crawler = $this->client->request('GET', "/store/checkout/{$price->getId()}");

        self::assertSelectorTextContains('.ecommerce-checkout-form', 'cannot take new orders');
        self::assertSelectorNotExists('.ecommerce-checkout-form button');

        $this->client->submit($crawler->filter('form.ecommerce-checkout-form')->form());

        self::assertNull($this->findOrder());
        self::assertSame([RevenueCapReachedNotificationType::TYPE], $this->notificationTypes($manager));
    }

    public function testStoreManagersAreWarnedBeforeTheCapAndAdminsSeeTheRevenue(): void
    {
        $manager = $this->createStoreManager();
        $this->createRevenue(70000);
        $price = $this->createPrice();

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $9.99');

        self::assertNotNull($this->findOrder());
        self::assertSame([RevenueCapWarningNotificationType::TYPE], $this->notificationTypes($manager));

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/ecommerce/orders');

        self::assertSelectorTextContains('.box', '$233.33');
        self::assertSelectorTextContains('.box', 'of the $250.00 your plan allows, 93% used');
        self::assertSelectorTextContains('.box .tag', 'Hobbyist');
    }

    private function createStoreManager(): User
    {
        $role = RoleFactory::createOne(['title' => 'Store managers']);
        $manager = UserFactory::createOne(['roleEntities' => new ArrayCollection([$role])]);
        self::getContainer()->get(SettingRepository::class)->set(StoreSettings::STORE_ROLE, $role->getId());

        return $manager;
    }

    private function createRevenue(int $amountMinor): void
    {
        OrderFactory::new()
            ->paid()
            ->create(['price' => ProductPriceFactory::new(['amountMinor' => $amountMinor])]);
    }

    private function createPrice(): ProductPrice
    {
        PaymentProviderConfigFactory::createOne();

        return ProductPriceFactory::createOne(['amountMinor' => 999]);
    }

    private function findOrder(): ?Order
    {
        return self::getContainer()->get(OrderRepository::class)->findOneBy(['status' => 'pending']);
    }

    /**
     * @return list<string>
     */
    private function notificationTypes(User $user): array
    {
        return array_map(
            static fn(Notification $notification) => $notification->getType(),
            self::getContainer()->get(NotificationRepository::class)->findBy(['recipient' => $user], ['id' => 'ASC']),
        );
    }
}

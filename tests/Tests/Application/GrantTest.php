<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Event\OrderPaidEvent;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use Forumify\Testing\Factories\Forum\BadgeFactory;
use RuntimeException;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;

class GrantTest extends EcommerceWebTestCase
{
    public function testSubscriptionGrantsRoleAndBadgeUntilItEnds(): void
    {
        PaymentProviderConfigFactory::createOne();
        $vip = RoleFactory::createOne(['title' => 'VIP']);
        $supporter = BadgeFactory::createOne(['name' => 'Supporter']);
        $product = ProductFactory::createOne([
            'title' => 'VIP membership',
            'grantedRoles' => new ArrayCollection([$vip]),
            'grantedBadges' => new ArrayCollection([$supporter]),
        ]);
        $price = ProductPriceFactory::new()->recurring()->create(['tier' => ProductTierFactory::createOne(['product' => $product]), 'amountMinor' => 500]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $5.00');
        $this->client->submitForm('Pay successfully');

        self::assertSame(['VIP'], $this->roleTitles($customer));
        self::assertSame(['Supporter'], $this->badgeNames($customer));

        $subscription = self::getContainer()->get(SubscriptionRepository::class)->findOneBy([]);
        self::assertNotNull($subscription);
        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'ended', 'subscription' => $subscription->providerReference], [
            'HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE,
        ]);
        self::assertResponseStatusCodeSame(204);

        self::assertSame([], $this->roleTitles($customer));
        self::assertSame([], $this->badgeNames($customer));
    }

    public function testRefundingASubscriptionPaymentEndsTheSubscription(): void
    {
        PaymentProviderConfigFactory::createOne();
        $vip = RoleFactory::createOne(['title' => 'VIP']);
        $product = ProductFactory::createOne([
            'title' => 'VIP membership',
            'refundPeriodDays' => 14,
            'grantedRoles' => new ArrayCollection([$vip]),
        ]);
        $price = ProductPriceFactory::new()->recurring()->create(['tier' => ProductTierFactory::createOne(['product' => $product]), 'amountMinor' => 500]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $5.00');
        $this->client->submitForm('Pay successfully');
        $subscription = self::getContainer()->get(SubscriptionRepository::class)->findOneBy([]);
        self::assertNotNull($subscription);
        $reference = $subscription->initialOrder->reference;

        $this->webhook(['type' => 'refunded', 'order' => $reference]);
        self::assertSame(OrderStatus::Refunded, $this->findSubscription()->initialOrder->status);
        self::assertSame(SubscriptionStatus::Active, $this->findSubscription()->status, 'the provider did not end it');
        self::assertSame(['VIP'], $this->roleTitles($customer), 'its grants last as long as the subscription');

        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'renewed', 'subscription' => $subscription->providerReference], [
            'HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE,
        ]);
        $renewal = self::getContainer()->get(OrderRepository::class)->findOneBy(['subscription' => $this->findSubscription()], ['id' => 'DESC']);
        self::assertTrue($renewal?->isRenewal());

        $this->client->request('GET', "/store/order/{$renewal->reference}/refund");
        self::assertSelectorTextContains('body', 'A refund ends your subscription right away');
        $this->client->submitForm('Send request', ['refund_request[reason]' => 'I forgot to cancel.']);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', "/admin/ecommerce/orders/{$renewal->getId()}");
        $this->client->submitForm('Approve and refund');
        self::assertSelectorTextContains('body', 'The order was refunded');

        self::assertSame(SubscriptionStatus::Ended, $this->findSubscription()->status);
        self::assertSame([], $this->roleTitles($customer));
    }

    public function testAFailingListenerLeavesThePaymentForTheProviderToRetry(): void
    {
        PaymentProviderConfigFactory::createOne();
        $vip = RoleFactory::createOne(['title' => 'VIP']);
        $product = ProductFactory::createOne(['grantedRoles' => new ArrayCollection([$vip])]);
        $customer = UserFactory::createOne();
        $order = OrderFactory::createOne([
            'user' => $customer,
            'price' => ProductPriceFactory::new(['tier' => ProductTierFactory::new(['product' => $product])]),
        ]);

        self::getContainer()->get('event_dispatcher')->addListener(OrderPaidEvent::class, static function (): void {
            throw new RuntimeException('The integration is down.');
        }, 100);
        $this->webhook(['type' => 'paid', 'order' => $order->reference], 500);
        self::assertSame(OrderStatus::Pending, $this->findOrder($order->reference)->status, 'the payment rolled back');
        self::assertSame([], $this->roleTitles($customer));

        $this->webhook(['type' => 'paid', 'order' => $order->reference]);
        self::assertSame(OrderStatus::Completed, $this->findOrder($order->reference)->status);
        self::assertSame(['VIP'], $this->roleTitles($customer));
    }

    /**
     * @param array<string, string> $payload
     */
    private function webhook(array $payload, int $status = 204): void
    {
        $this->client->jsonRequest('POST', '/store/webhook/fake', $payload, [
            'HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE,
        ]);
        self::assertResponseStatusCodeSame($status);
    }

    private function findSubscription(): Subscription
    {
        $repository = self::getContainer()->get(SubscriptionRepository::class);
        $repository->getEntityManager()->clear();

        return $repository->findOneBy([]) ?? self::fail('There is no subscription.');
    }

    private function findOrder(string $reference): Order
    {
        $repository = self::getContainer()->get(OrderRepository::class);
        $repository->getEntityManager()->clear();

        return $repository->findOneByReference($reference) ?? self::fail('The order is gone.');
    }

    /**
     * @return list<string>
     */
    private function roleTitles(User $user): array
    {
        return $this->reload($user)->getRoleEntities()->map(static fn($role) => $role->getTitle())->getValues();
    }

    /**
     * @return list<string>
     */
    private function badgeNames(User $user): array
    {
        return $this->reload($user)->getBadges()->map(static fn($badge) => $badge->getName())->getValues();
    }

    private function reload(User $user): User
    {
        $repository = self::getContainer()->get(UserRepository::class);
        $repository->getEntityManager()->clear();
        $reloaded = $repository->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }
}

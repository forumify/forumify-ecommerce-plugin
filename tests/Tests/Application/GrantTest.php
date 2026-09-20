<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use Forumify\Testing\Factories\Forum\BadgeFactory;
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

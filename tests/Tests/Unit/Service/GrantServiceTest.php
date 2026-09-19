<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Service\GrantService;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use PluginTests\Tests\Factories\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class GrantServiceTest extends KernelTestCase
{
    use Factories;

    private Role $vip;

    protected function setUp(): void
    {
        $this->vip = RoleFactory::createOne(['title' => 'VIP']);
    }

    public function testOverlappingSubscriptionsKeepTheRoleUntilTheLastOneEnds(): void
    {
        $user = UserFactory::createOne();
        $first = $this->subscribe($user);
        $second = $this->subscribe($user);

        $this->service()->revoke($first);
        self::assertTrue($user->getRoleEntities()->contains($this->vip));

        $this->service()->revoke($second);
        self::assertFalse($user->getRoleEntities()->contains($this->vip));
    }

    public function testRoleTheUserAlreadyHadSurvives(): void
    {
        $user = UserFactory::createOne(['roleEntities' => new ArrayCollection([$this->vip])]);
        $first = $this->subscribe($user);
        $this->service()->revoke($first);
        $second = $this->subscribe($user);
        $third = $this->subscribe($user);

        $this->service()->revoke($third);
        $this->service()->revoke($second);

        self::assertTrue($user->getRoleEntities()->contains($this->vip));
    }

    public function testOneTimePurchasesAreNeverRevoked(): void
    {
        $user = UserFactory::createOne();
        $product = ProductFactory::createOne(['grantedRoles' => new ArrayCollection([$this->vip])]);
        $order = OrderFactory::new()->completed()->create([
            'user' => $user,
            'price' => ProductPriceFactory::createOne(['tier' => ProductTierFactory::createOne(['product' => $product])]),
        ]);
        $this->service()->grant($order, null);
        $subscription = $this->subscribe($user);

        $this->service()->revoke($subscription);

        self::assertTrue($user->getRoleEntities()->contains($this->vip));
    }

    private function subscribe(User $user): Subscription
    {
        $product = ProductFactory::createOne(['grantedRoles' => new ArrayCollection([$this->vip])]);
        $order = OrderFactory::new()->completed()->create([
            'user' => $user,
            'price' => ProductPriceFactory::new()->recurring()->create(['tier' => ProductTierFactory::createOne(['product' => $product])]),
        ]);
        $subscription = SubscriptionFactory::createOne(['initialOrder' => $order]);
        $this->service()->grant($order, $subscription);

        return $subscription;
    }

    private function service(): GrantService
    {
        return self::getContainer()->get(GrantService::class);
    }
}

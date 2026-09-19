<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTimeImmutable;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Notification\SubscriptionEndedNotificationType;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PastDueTest extends EcommerceWebTestCase
{
    public function testTaskEndsSubscriptionsWhoseGracePeriodExpired(): void
    {
        PaymentProviderConfigFactory::createOne();
        $expired = SubscriptionFactory::new()
            ->pastDueSince(new DateTimeImmutable('-40 days'))
            ->create(['currentPeriodEnd' => new DateTimeImmutable('-45 days')]);
        $inGrace = SubscriptionFactory::new()
            ->pastDueSince(new DateTimeImmutable('-5 days'))
            ->create(['currentPeriodEnd' => new DateTimeImmutable('-5 days')]);
        $active = SubscriptionFactory::createOne(['currentPeriodEnd' => new DateTimeImmutable('-1 day')]);

        $command = new CommandTester(new Application(self::$kernel)->find('ecommerce:subscriptions:end-past-due'));
        self::assertSame(0, $command->execute([]));

        self::assertSame(SubscriptionStatus::Ended, $this->reload($expired)->status);
        self::assertSame(SubscriptionStatus::PastDue, $this->reload($inGrace)->status);
        self::assertSame(SubscriptionStatus::Active, $this->reload($active)->status);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findBy(['recipient' => $expired->user]);
        self::assertSame(
            [SubscriptionEndedNotificationType::TYPE],
            array_map(static fn (Notification $notification) => $notification->getType(), $notifications),
        );
    }

    private function reload(Subscription $subscription): Subscription
    {
        $reloaded = self::getContainer()->get(SubscriptionRepository::class)->find($subscription->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}

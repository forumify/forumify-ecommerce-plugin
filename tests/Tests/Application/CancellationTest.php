<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Fulfillment\Type\ManualFulfillmentType;
use Forumify\Ecommerce\Notification\CancellationToHandleNotificationType;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\CancellationRequestService;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use PluginTests\Tests\Factories\SubscriptionFactory;

class CancellationTest extends EcommerceWebTestCase
{
    public function testBuyerCancelsAManuallyFulfilledSubscription(): void
    {
        PaymentProviderConfigFactory::createOne();
        $fulfillers = RoleFactory::createOne(['title' => 'Game masters']);
        $fulfiller = UserFactory::createOne(['roleEntities' => new ArrayCollection([$fulfillers])]);
        $product = ProductFactory::createOne([
            'title' => 'Game server',
            'fulfillmentType' => ManualFulfillmentType::KEY,
            'fulfillmentRole' => $fulfillers,
        ]);
        $price = ProductPriceFactory::new()->recurring()->create([
            'tier' => ProductTierFactory::createOne(['product' => $product]),
            'amountMinor' => 500,
        ]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $5.00');
        $this->client->submitForm('Pay successfully');

        $subscription = $this->findSubscription();
        self::assertSame(SubscriptionStatus::Active, $subscription?->status);

        $this->client->request('GET', "/store/subscription/{$subscription->getId()}");
        $this->client->clickLink('Cancel subscription');
        self::assertSelectorTextContains('body', 'An admin finishes the cancellation');
        $this->client->submitForm('Cancel subscription');
        self::assertSelectorTextContains('body', 'Your subscription was cancelled');

        $subscription = $this->findSubscription();
        self::assertSame(SubscriptionStatus::Cancelled, $subscription?->status);
        self::assertTrue($subscription->cancellationRequest?->isPending());
        self::assertContains(CancellationToHandleNotificationType::TYPE, $this->notificationTypes($fulfiller));
        $this->assertNotificationReads($fulfiller, 'Cancellation to handle: Game server', 'runs until');
        self::assertSelectorTextContains('.ecommerce-plan', 'Cancellation requested');
        self::assertSelectorTextContains('.ecommerce-plan', 'Ends on');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', "/admin/ecommerce/subscriptions/{$subscription->getId()}");
        self::assertSelectorTextContains('nav, aside', 'Subscriptions (1)');
        $this->client->submitForm('Mark as handled');
        self::assertSelectorTextContains('body', 'The cancellation was marked as handled');

        $subscription = $this->findSubscription();
        self::assertFalse($subscription?->cancellationRequest?->isPending());
        self::assertSame($this->admin->getId(), $subscription->cancellationRequest?->completedBy?->getId());

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/subscription/{$subscription->getId()}");
        self::assertSelectorTextNotContains('.ecommerce-plan', 'Cancellation requested');
    }

    public function testAnInstantSubscriptionIsCancelledWithoutAnyAdminWork(): void
    {
        PaymentProviderConfigFactory::createOne();
        $customer = UserFactory::createOne();
        $subscription = SubscriptionFactory::createOne([
            'initialOrder' => OrderFactory::new([
                'user' => $customer,
                'price' => ProductPriceFactory::new()->recurring(),
            ])->completed(),
        ]);

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/subscription/{$subscription->getId()}/cancel");
        self::assertSelectorTextNotContains('body', 'An admin finishes the cancellation');
        $this->client->submitForm('Cancel subscription');

        $subscription = $this->findSubscription();
        self::assertSame(SubscriptionStatus::Cancelled, $subscription?->status);
        self::assertNull($subscription->cancellationRequest);

        $this->client->request('GET', "/store/subscription/{$subscription->getId()}/cancel");
        self::assertResponseStatusCodeSame(404, 'it can only be cancelled once');
    }

    public function testFulfillersAreRemindedWhenAPaidPeriodRunsOut(): void
    {
        $fulfillers = RoleFactory::createOne(['title' => 'Game masters']);
        $fulfiller = UserFactory::createOne(['roleEntities' => new ArrayCollection([$fulfillers])]);
        $product = ProductFactory::createOne([
            'fulfillmentType' => ManualFulfillmentType::KEY,
            'fulfillmentRole' => $fulfillers,
        ]);
        $subscription = SubscriptionFactory::createOne([
            'initialOrder' => OrderFactory::new([
                'price' => ProductPriceFactory::new([
                    'tier' => ProductTierFactory::new(['product' => $product]),
                ])->recurring(),
            ])->completed(),
            'currentPeriodEnd' => new DateTimeImmutable('-1 day'),
        ]);

        $cancellations = self::getContainer()->get(CancellationRequestService::class);
        $cancellations->open($subscription);

        self::assertSame(1, $cancellations->remindPending());
        self::assertSame(0, $cancellations->remindPending(), 'a reminder is only sent once');
        self::assertSame(
            [CancellationToHandleNotificationType::TYPE, CancellationToHandleNotificationType::TYPE],
            $this->notificationTypes($fulfiller),
        );
    }

    public function testAdminCancelsAndRefundsTheLastPaymentInOneAction(): void
    {
        PaymentProviderConfigFactory::createOne();
        $customer = UserFactory::createOne();
        $subscription = SubscriptionFactory::createOne([
            'initialOrder' => OrderFactory::new([
                'user' => $customer,
                'price' => ProductPriceFactory::new()->recurring(),
            ])->completed(),
        ]);

        $this->client->request('GET', "/admin/ecommerce/subscriptions/{$subscription->getId()}/cancel");
        self::assertSelectorTextContains('body', 'The last payment is');
        $this->client->submitForm('Cancel subscription', [
            'subscription_cancel[atPeriodEnd]' => '0',
            'subscription_cancel[refundLastOrder]' => '1',
        ]);
        self::assertSelectorTextContains('body', 'The last payment was refunded');

        self::assertSame(SubscriptionStatus::Ended, $this->findSubscription()?->status);
        $order = self::getContainer()->get(OrderRepository::class)->find($subscription->initialOrder->getId());
        self::assertSame(OrderStatus::Refunded, $order?->status);
    }

    private function findSubscription(): ?Subscription
    {
        return self::getContainer()->get(SubscriptionRepository::class)->findOneBy([], ['id' => 'DESC']);
    }

    private function assertNotificationReads(User $user, string $title, string $description): void
    {
        $notification = self::getContainer()->get(NotificationRepository::class)
            ->findOneBy(['recipient' => $user], ['id' => 'DESC']);
        self::assertInstanceOf(Notification::class, $notification);

        $type = self::getContainer()->get(CancellationToHandleNotificationType::class);
        self::assertSame($title, $type->getTitle($notification));
        self::assertStringContainsString($description, $type->getDescription($notification));
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

<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use DateTimeImmutable;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;
use PluginTests\Tests\Factories\SubscriptionFactory;

class SubscriptionTest extends EcommerceWebTestCase
{
    public function testSubscriptionLivesThroughRenewalsUntilAdminCancelsIt(): void
    {
        PaymentProviderConfigFactory::createOne();
        $product = ProductFactory::createOne(['title' => 'Milsim']);
        $tier = ProductTierFactory::createOne(['product' => $product, 'label' => 'Premium']);
        ProductPriceFactory::new()->recurring()->create(['tier' => $tier, 'amountMinor' => 999]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/{$product->getSlug()}");
        $this->client->submitForm('Get Milsim', [], 'GET');
        $this->client->submitForm('Pay $9.99');
        $this->client->submitForm('Pay successfully');
        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');

        $subscription = $this->findSubscription();
        self::assertSame(SubscriptionStatus::Active, $subscription->status);
        self::assertSame('fake_sub_' . $subscription->initialOrder->reference, $subscription->providerReference);
        $firstPeriodEnd = $subscription->currentPeriodEnd;
        self::assertEqualsWithDelta(new DateTimeImmutable('+1 month'), $firstPeriodEnd, 60);

        $this->client->request('GET', '/store/orders');
        self::assertSelectorTextContains('.ecommerce-plan', 'Milsim');
        self::assertSelectorTextContains('.ecommerce-plan', 'Renews on ' . $firstPeriodEnd->format('j M Y'));

        $subscriptionReference = $subscription->providerReference;
        $this->webhook(['type' => 'renewal_started', 'subscription' => $subscriptionReference, 'payment' => 'in_1']);
        self::assertSame(OrderStatus::Pending, $this->findRenewal('in_1')->status);
        self::assertEquals($firstPeriodEnd, $this->findSubscription()->currentPeriodEnd);
        $this->client->request('GET', '/store/orders');
        self::assertSelectorTextContains('a.ecommerce-ledger-row', 'Renewal');
        self::assertSelectorTextContains('a.ecommerce-ledger-row', 'Pending');
        $this->client->request('GET', "/store/order/{$this->findRenewal('in_1')->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'This renewal is waiting for payment');

        $this->webhook(['type' => 'renewed', 'subscription' => $subscriptionReference, 'payment' => 'in_1']);
        self::assertSame(OrderStatus::Completed, $this->findRenewal('in_1')->status);
        $secondPeriodEnd = $this->findSubscription()->currentPeriodEnd;
        self::assertGreaterThan($firstPeriodEnd, $secondPeriodEnd);

        $this->webhook(['type' => 'payment_failed', 'subscription' => $subscriptionReference, 'payment' => 'in_2']);
        self::assertSame(SubscriptionStatus::PastDue, $this->findSubscription()->status);
        self::assertSame(OrderStatus::Pending, $this->findRenewal('in_2')->status);
        $this->client->request('GET', '/store/orders');
        self::assertSelectorTextContains('.ecommerce-plan.is-past_due', 'Past due');
        self::assertSelectorTextContains('.ecommerce-plan-alert', 'Access ends on');

        $this->webhook(['type' => 'renewed', 'subscription' => $subscriptionReference, 'payment' => 'in_2', 'period_end' => '+3 months']);
        self::assertSame(SubscriptionStatus::Active, $this->findSubscription()->status);
        self::assertSame(OrderStatus::Completed, $this->findRenewal('in_2')->status);
        self::assertGreaterThan($secondPeriodEnd, $this->findSubscription()->currentPeriodEnd);

        $this->webhook(['type' => 'renewal_started', 'subscription' => $subscriptionReference, 'payment' => 'in_3']);

        $this->client->request('GET', "/store/subscription/{$subscription->getId()}");
        self::assertSelectorTextContains('.ecommerce-plan', 'Milsim');
        self::assertCount(4, $this->client->getCrawler()->filter('.ecommerce-ledger-row'));

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', "/store/subscription/{$subscription->getId()}");
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/ecommerce/subscriptions');
        self::assertSelectorTextContains('.table-wrapper', 'Milsim - Premium');
        self::assertSelectorTextContains('.table-wrapper', 'Active');

        $this->client->request('GET', "/admin/ecommerce/subscriptions/{$subscription->getId()}");
        $orderReferences = $this->client->getCrawler()->filter('.table-wrapper tbody tr td:first-child')->each(static fn($td) => $td->text());
        self::assertEqualsCanonicalizing(
            [$subscription->initialOrder->reference, $this->findRenewal('in_1')->reference, $this->findRenewal('in_2')->reference, $this->findRenewal('in_3')->reference],
            $orderReferences,
        );

        $this->client->request('GET', "/admin/ecommerce/orders/{$subscription->initialOrder->getId()}");
        $this->client->clickLink('Active');
        $this->client->clickLink('Cancel subscription');
        self::assertSelectorTextContains('.box', 'paid until');
        $this->client->submitForm('Cancel subscription', ['subscription_cancel[atPeriodEnd]' => '1']);
        self::assertSelectorTextContains('body', 'will end at the end of its current period');
        self::assertSelectorTextContains('.box', 'Cancelled');
        self::assertSame(SubscriptionStatus::Cancelled, $this->findSubscription()->status);

        $this->webhook(['type' => 'ended', 'subscription' => $subscriptionReference]);
        self::assertSame(SubscriptionStatus::Ended, $this->findSubscription()->status);
        self::assertSame(OrderStatus::Failed, $this->findRenewal('in_3')->status);
        self::assertNotNull($this->findSubscription()->endedAt);

        $this->client->loginUser($customer);
        $this->client->request('GET', '/store/orders');
        self::assertSelectorNotExists('.ecommerce-plan');
        self::assertSelectorTextContains('.ecommerce-ledger-row', 'Milsim');
    }

    public function testAdminEndsSubscriptionImmediately(): void
    {
        PaymentProviderConfigFactory::createOne();
        $subscription = SubscriptionFactory::createOne();

        $this->client->request('GET', "/admin/ecommerce/subscriptions/{$subscription->getId()}/cancel");
        $this->client->submitForm('Cancel subscription', ['subscription_cancel[atPeriodEnd]' => '0']);

        self::assertSelectorTextContains('body', 'The subscription was ended');
        self::assertSame(SubscriptionStatus::Ended, $this->findSubscription()->status);
        self::assertSelectorNotExists('a[href$="/cancel"]');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function webhook(array $payload): void
    {
        $this->client->jsonRequest('POST', '/store/webhook/fake', $payload, ['HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE]);
        self::assertResponseStatusCodeSame(204);
    }

    private function findRenewal(string $payment): Order
    {
        $order = self::getContainer()->get(OrderRepository::class)->findOneByProviderReference(FakePaymentProvider::KEY, $payment);
        self::assertNotNull($order);

        return $order;
    }

    private function findSubscription(): Subscription
    {
        $subscription = self::getContainer()->get(SubscriptionRepository::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($subscription);

        return $subscription;
    }
}

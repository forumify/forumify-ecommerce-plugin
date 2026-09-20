<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Doctrine\Common\Collections\ArrayCollection;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Core\Repository\UserRepository;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\RefundRequestStatus;
use Forumify\Ecommerce\Notification\OrderRefundedNotificationType;
use Forumify\Ecommerce\Notification\RefundRejectedNotificationType;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\RefundRequestRepository;
use Forumify\Testing\Factories\Core\RoleFactory;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;

class RefundTest extends EcommerceWebTestCase
{
    public function testBuyerAsksForARefundAndAnAdminApprovesIt(): void
    {
        PaymentProviderConfigFactory::createOne();
        $vip = RoleFactory::createOne(['title' => 'VIP']);
        $product = ProductFactory::createOne([
            'title' => 'Milsim',
            'refundPeriodDays' => 14,
            'grantedRoles' => new ArrayCollection([$vip]),
        ]);
        $price = ProductPriceFactory::createOne([
            'tier' => ProductTierFactory::createOne(['product' => $product]),
            'amountMinor' => 1999,
        ]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");
        $this->client->submitForm('Pay $19.99');
        $this->client->submitForm('Pay successfully');

        $order = $this->findOrder();
        self::assertSame(OrderStatus::Completed, $order?->status);
        self::assertSame(['VIP'], $this->roleTitles($customer));
        self::assertSame(14, $order->refundPeriodDays, 'the window is snapshotted on the order');

        $this->client->request('GET', "/store/order/{$order->reference}/invoice");
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string)$this->client->getResponse()->getContent());

        $this->client->request('GET', "/store/order/{$order->reference}");
        $this->client->clickLink('Request a refund');
        $this->client->submitForm('Send request', ['refund_request[reason]' => 'I bought the wrong tier.']);
        self::assertSelectorTextContains('body', 'Your refund request was sent');
        self::assertSelectorTextContains('.ecommerce-order-summary', 'Refund requested');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/ecommerce/refunds');
        self::assertSelectorTextContains('.table-wrapper', $order->reference);
        self::assertSelectorTextContains('nav, aside', 'Refunds (1)');

        $this->client->request('GET', "/admin/ecommerce/orders/{$order->getId()}");
        self::assertSelectorTextContains('body', 'I bought the wrong tier.');
        $this->client->submitForm('Approve and refund', ['refund_request_handle[note]' => 'Sorry about that!']);
        self::assertSelectorTextContains('body', 'The order was refunded');

        $refunded = $this->findOrder();
        self::assertSame(OrderStatus::Refunded, $refunded?->status);
        self::assertNotNull($refunded->refundedAt);
        self::assertSame(RefundRequestStatus::Approved, $refunded->refundRequest?->status);
        self::assertSame([], $this->roleTitles($customer), 'a refund takes the granted role back');
        self::assertContains(OrderRefundedNotificationType::TYPE, $this->notificationTypes($customer));

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'This order was refunded');
        self::assertSelectorTextContains('.ecommerce-order-summary', 'Sorry about that!');
    }

    public function testAdminRejectsARefundRequest(): void
    {
        PaymentProviderConfigFactory::createOne();
        $product = ProductFactory::createOne(['title' => 'Milsim', 'refundPeriodDays' => 14]);
        $customer = UserFactory::createOne();
        $order = OrderFactory::new([
            'user' => $customer,
            'price' => ProductPriceFactory::new(['tier' => ProductTierFactory::new(['product' => $product])]),
        ])->completed()->create();

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/order/{$order->reference}/refund");
        $this->client->submitForm('Send request', ['refund_request[reason]' => 'Changed my mind.']);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', "/admin/ecommerce/orders/{$order->getId()}");
        $this->client->submitForm('Reject', ['refund_request_handle[note]' => 'This product is not refundable.']);
        self::assertSelectorTextContains('body', 'The refund request was rejected');

        $request = self::getContainer()->get(RefundRequestRepository::class)->findOneBy([]);
        self::assertSame(RefundRequestStatus::Rejected, $request?->status);
        self::assertSame($this->admin->getId(), $request->handledBy?->getId());
        self::assertSame(OrderStatus::Completed, $this->findOrder()?->status, 'the order is untouched');
        self::assertContains(RefundRejectedNotificationType::TYPE, $this->notificationTypes($customer));

        $this->client->loginUser($customer);
        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertSelectorTextContains('.ecommerce-order-summary', 'Your refund request was declined');
        self::assertSelectorTextContains('.ecommerce-order-summary', 'This product is not refundable.');
        $this->client->request('GET', "/store/order/{$order->reference}/refund");
        self::assertResponseStatusCodeSame(404, 'a handled order can not be requested again');
    }

    public function testProviderRefundIsMirroredOnTheOrder(): void
    {
        PaymentProviderConfigFactory::createOne();
        $order = OrderFactory::new(['user' => UserFactory::createOne()])->completed()->create();

        $this->client->jsonRequest('POST', '/store/webhook/fake', [
            'type' => 'refunded',
            'order' => $order->reference,
        ], ['HTTP_X_FAKE_SIGNATURE' => 'valid']);
        self::assertResponseStatusCodeSame(204);

        self::assertSame(OrderStatus::Refunded, $this->findOrder()?->status);
    }

    private function findOrder(): ?Order
    {
        return self::getContainer()->get(OrderRepository::class)->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * @return list<string>
     */
    private function roleTitles(User $user): array
    {
        $user = self::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertInstanceOf(User::class, $user);

        return $user->getRoleEntities()->map(static fn($role) => $role->getTitle())->getValues();
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

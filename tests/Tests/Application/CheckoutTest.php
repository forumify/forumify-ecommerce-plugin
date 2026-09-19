<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\PaymentProvider\Fake\FakePaymentProvider;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Testing\Factories\Core\UserFactory;
use PluginTests\Tests\Factories\OrderFactory;
use PluginTests\Tests\Factories\PaymentProviderConfigFactory;
use PluginTests\Tests\Factories\ProductFactory;
use PluginTests\Tests\Factories\ProductPriceFactory;
use PluginTests\Tests\Factories\ProductTierFactory;

class CheckoutTest extends EcommerceWebTestCase
{
    public function testCustomerPaysForOrderThroughProvider(): void
    {
        PaymentProviderConfigFactory::createOne();
        $product = ProductFactory::createOne(['title' => 'Milsim']);
        $tier = ProductTierFactory::createOne(['product' => $product, 'key' => 'premium', 'label' => 'Premium']);
        ProductPriceFactory::createOne(['tier' => $tier, 'amountMinor' => 999]);
        $customer = UserFactory::createOne();
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/{$product->getSlug()}");
        $this->client->submitForm('Get Milsim', [], 'GET');
        self::assertSelectorTextContains('.ecommerce-checkout-price', '$9.99');

        $this->client->followRedirects(false);
        $this->client->submitForm('Pay $9.99');

        $order = $this->findOrder();
        self::assertResponseRedirects("http://localhost/store/fake-payment/{$order->reference}");
        self::assertSame(OrderStatus::Pending, $order->status);
        self::assertSame('fake_' . $order->reference, $order->providerReference);
        self::assertSame(['Milsim', 'premium', 999], [$order->productTitle, $order->tierKey, $order->amountMinor]);
        self::assertTrue($order->price?->locked);

        $this->client->followRedirects();
        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'waiting for your payment');

        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'paid', 'order' => $order->reference]);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/store/webhook/fake', server: ['HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE], content: 'not json');
        self::assertResponseStatusCodeSame(400);
        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'paid'], ['HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(OrderStatus::Pending, $this->findOrder()->status);

        $server = ['HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE];
        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'paid', 'order' => $order->reference], $server);
        self::assertResponseStatusCodeSame(204);
        $this->client->jsonRequest('POST', '/store/webhook/fake', ['type' => 'paid', 'order' => $order->reference], $server);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Completed, $this->findOrder()->status);
        self::assertNotNull($this->findOrder()->completedAt);

        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');

        $this->client->request('GET', '/store/orders');
        self::assertSelectorTextContains('.ecommerce-order-row', 'Milsim');
        self::assertSelectorTextContains('.ecommerce-order-row', 'Completed');

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', "/store/order/{$order->reference}");
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/store/orders');
        self::assertSelectorNotExists('.ecommerce-order-row');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/ecommerce/orders');
        self::assertSelectorTextContains('.table-wrapper', $order->reference);
        self::assertSelectorTextContains('.table-wrapper', 'Milsim - Premium');
        self::assertSelectorTextContains('.table-wrapper', 'Completed');

        $this->client->request('GET', "/admin/ecommerce/orders/{$order->getId()}");
        self::assertSelectorTextContains('.box', 'fake_' . $order->reference);
    }

    public function testFreeTierCompletesInstantly(): void
    {
        $product = ProductFactory::createOne(['title' => 'Milsim']);
        $tier = ProductTierFactory::createOne(['product' => $product]);
        ProductPriceFactory::createOne(['tier' => $tier, 'amountMinor' => 0]);
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request('GET', "/store/{$product->getSlug()}");
        $this->client->submitForm('Get Milsim', [], 'GET');
        $this->client->submitForm('Get it for free');

        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');
        $order = $this->findOrder();
        self::assertSame(OrderStatus::Completed, $order->status);
        self::assertNull($order->providerKey);
        self::assertTrue($order->price?->locked);
    }

    public function testCustomerFinishesCheckoutOnTheFakePaymentPage(): void
    {
        PaymentProviderConfigFactory::createOne();
        $customer = UserFactory::createOne();
        $paid = OrderFactory::createOne(['user' => $customer]);
        $failed = OrderFactory::createOne(['user' => $customer]);
        $cancelled = OrderFactory::createOne(['user' => $customer]);
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/fake-payment/{$paid->reference}");
        self::assertSelectorTextContains('.alert-warning', 'development simulator');
        $this->client->submitForm('Pay successfully');
        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');

        $this->client->request('GET', "/store/fake-payment/{$paid->reference}");
        self::assertSelectorTextContains('.ecommerce-order-status', 'Your order is complete');

        $this->client->request('GET', "/store/fake-payment/{$failed->reference}");
        $this->client->submitForm('Fail the payment');
        self::assertSelectorTextContains('.ecommerce-order-status', 'did not go through');

        $this->client->request('GET', "/store/fake-payment/{$cancelled->reference}");
        $this->client->submitForm('Cancel and go back');
        self::assertSelectorTextContains('.ecommerce-order-status', 'You cancelled this order');

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', "/store/fake-payment/{$paid->reference}");
        self::assertResponseStatusCodeSame(404);
    }

    public function testAbandonedCheckoutIsCancelledButStillCompletesWhenPaid(): void
    {
        PaymentProviderConfigFactory::createOne();
        $customer = UserFactory::createOne();
        $order = OrderFactory::createOne(['user' => $customer]);
        $this->client->loginUser($customer);

        $this->client->request('GET', "/store/order/{$order->reference}/cancel");
        self::assertSelectorTextContains('.ecommerce-order-status', 'You cancelled this order');

        $this->client->jsonRequest(
            'POST',
            '/store/webhook/fake',
            ['type' => 'paid', 'order' => $order->reference],
            ['HTTP_X_FAKE_SIGNATURE' => FakePaymentProvider::SIGNATURE],
        );
        self::assertSame(OrderStatus::Completed, $this->findOrder()->status);
    }

    public function testGuestIsSentToLogin(): void
    {
        $price = ProductPriceFactory::createOne();
        $this->client->getCookieJar()->clear();
        $this->client->followRedirects(false);

        $this->client->request('GET', "/store/checkout/{$price->getId()}");

        self::assertResponseRedirects('http://localhost/login');
    }

    private function findOrder(): Order
    {
        $order = self::getContainer()->get(OrderRepository::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($order);

        return $order;
    }
}

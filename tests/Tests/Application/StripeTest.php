<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application;

use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripePaymentProvider;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use PluginTests\Tests\Factories\OrderFactory;

class StripeTest extends EcommerceWebTestCase
{
    private const WEBHOOK_SECRET = 'whsec_testsecret';

    public function testAdminConfiguresStripeAndItsWebhookCompletesAnOrder(): void
    {
        $crawler = $this->client->request('GET', '/admin/ecommerce/payment-providers');
        $this->client->click($crawler->filter('a[aria-label="Edit Stripe"]')->link());
        self::assertSelectorTextContains('.alert-info code', '/store/webhook/stripe');

        $this->client->submitForm('Save', [
            'payment_provider_config[enabled]' => '1',
            'payment_provider_config[settings][secretKey]' => 'pk_test_publishable',
            'payment_provider_config[settings][webhookSecret]' => self::WEBHOOK_SECRET,
        ]);
        self::assertSelectorTextContains('form', 'This is not a Stripe secret key');

        $this->client->submitForm('Save', [
            'payment_provider_config[enabled]' => '1',
            'payment_provider_config[settings][secretKey]' => 'sk_test_secret',
            'payment_provider_config[settings][webhookSecret]' => self::WEBHOOK_SECRET,
        ]);
        self::assertSelectorTextContains('tbody', 'Stripe');

        $this->client->click($this->client->getCrawler()->filter('a[aria-label="Edit Stripe"]')->link());
        $this->client->submitForm('Save');
        $config = self::getContainer()->get(PaymentProviderConfigRepository::class)->findOneByKey(StripePaymentProvider::KEY);
        self::assertNotNull($config);
        self::assertTrue($config->enabled);
        self::assertSame(['secretKey' => 'sk_test_secret', 'webhookSecret' => self::WEBHOOK_SECRET], $config->settings);

        $order = OrderFactory::createOne(['providerKey' => StripePaymentProvider::KEY]);
        $payload = str_replace('%ORDER_REFERENCE%', $order->reference, (string)file_get_contents(TEST_DATA_DIR . '/stripe/checkout.session.completed.json'));

        $this->postWebhook($payload, 'whsec_wrong');
        self::assertResponseStatusCodeSame(400);
        self::assertSame(OrderStatus::Pending, $this->findOrderStatus($order->reference));

        $this->postWebhook($payload, self::WEBHOOK_SECRET);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Completed, $this->findOrderStatus($order->reference));
    }

    private function postWebhook(string $payload, string $secret): void
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $this->client->request('POST', '/store/webhook/stripe', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], content: $payload);
    }

    private function findOrderStatus(string $reference): ?OrderStatus
    {
        return self::getContainer()->get(OrderRepository::class)->findOneByReference($reference)?->status;
    }
}

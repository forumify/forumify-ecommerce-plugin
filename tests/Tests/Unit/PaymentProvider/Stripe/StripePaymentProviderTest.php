<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\PaymentProvider\Stripe;

use Brick\Money\Money;
use DateTimeImmutable;
use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentRefunded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionPaymentFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalStarted;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalVoided;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionUpdated;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripeCatalog;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripeClientFactory;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripeEventMapper;
use Forumify\Ecommerce\PaymentProvider\Stripe\StripePaymentProvider;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Collection;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\ChargeService;
use Stripe\Service\Checkout\CheckoutServiceFactory;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\InvoicePaymentService;
use Stripe\Service\InvoiceService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\Service\SubscriptionService;
use Stripe\Subscription as StripeSubscription;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentProviderTest extends TestCase
{
    use StripeTestTrait;

    private const WEBHOOK_SECRET = 'whsec_test';
    private const PERIOD_END = 1790000000;

    private SessionService&MockObject $sessions;
    private SubscriptionService&MockObject $subscriptions;
    private RefundService&MockObject $refunds;
    private StripeCatalog&MockObject $catalog;
    private StripePaymentProvider $provider;

    protected function setUp(): void
    {
        $this->sessions = $this->createMock(SessionService::class);
        $this->subscriptions = $this->createMock(SubscriptionService::class);
        $this->subscriptions->method('retrieve')->willReturn(StripeSubscription::constructFrom([
            'id' => 'sub_1',
            'items' => ['object' => 'list', 'data' => [['object' => 'subscription_item', 'current_period_end' => self::PERIOD_END]]],
        ]));
        $checkout = $this->createMock(CheckoutServiceFactory::class);
        $checkout->method('__get')->with('sessions')->willReturn($this->sessions);

        $this->refunds = $this->createMock(RefundService::class);

        $clientFactory = $this->createMock(StripeClientFactory::class);
        $clientFactory->method('create')->willReturn($this->mockStripeClient([
            'checkout' => $checkout,
            'subscriptions' => $this->subscriptions,
            'refunds' => $this->refunds,
            ...$this->mockPaymentLookups(),
        ]));

        $config = new PaymentProviderConfig(StripePaymentProvider::KEY);
        $config->settings = ['secretKey' => 'sk_test_abc', 'webhookSecret' => self::WEBHOOK_SECRET];
        $configRepository = $this->createMock(PaymentProviderConfigRepository::class);
        $configRepository->method('findOneByKey')->with('stripe')->willReturn($config);

        $this->catalog = $this->createMock(StripeCatalog::class);
        $this->catalog->method('syncPrice')->willReturn('price_1');
        $this->catalog->method('syncCustomer')->willReturn('cus_1');

        $this->provider = new StripePaymentProvider($configRepository, $clientFactory, $this->catalog, new StripeEventMapper());
    }

    public function testCheckoutSessionModeFollowsThePriceType(): void
    {
        $oneTime = new Order($this->createUser(), $this->createPrice(), StripePaymentProvider::KEY);
        $recurring = new Order($this->createUser(), $this->createPrice(interval: BillingInterval::Year), StripePaymentProvider::KEY);
        $metadata = static fn(Order $order) => ['ecommerce_order_reference' => $order->reference];
        $expected = static fn(Order $order, string $mode, string $metadataKey) => [
            'mode' => $mode,
            'line_items' => [['price' => 'price_1', 'quantity' => 1]],
            'customer' => 'cus_1',
            'client_reference_id' => $order->reference,
            'success_url' => 'https://shop.test/order',
            'cancel_url' => 'https://shop.test/cancel',
            'metadata' => $metadata($order),
            $metadataKey => ['metadata' => $metadata($order)],
            // one-time payments only get an invoice when the session asks for one
            ...($mode === 'payment' ? ['invoice_creation' => ['enabled' => true]] : []),
        ];

        $this->sessions->expects(self::exactly(2))->method('create')->willReturnCallback(
            static fn(array $params) => Session::constructFrom(['id' => 'cs_' . $params['mode'], 'url' => 'https://checkout.stripe.com/pay']),
        )->with(self::callback(static fn(array $params) => $params === $expected($oneTime, 'payment', 'payment_intent_data')
            || $params === $expected($recurring, 'subscription', 'subscription_data')));

        $urls = new CheckoutUrls('https://shop.test/order', 'https://shop.test/cancel');
        $redirect = $this->provider->startCheckout($oneTime, $urls);
        self::assertSame(['https://checkout.stripe.com/pay', 'cs_payment'], [$redirect->url, $redirect->providerReference]);
        self::assertSame('cs_subscription', $this->provider->startCheckout($recurring, $urls)->providerReference);
    }

    /**
     * @param array<string, mixed> $object
     * @param list<PaymentEvent> $expected
     */
    #[DataProvider('webhookEvents')]
    public function testWebhookEventsAreMapped(string $type, array $object, array $expected): void
    {
        self::assertEquals($expected, $this->provider->handleWebhook($this->signedRequest($type, $object)));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, list<PaymentEvent>}>
     */
    public static function webhookEvents(): iterable
    {
        $periodEnd = new DateTimeImmutable('@' . self::PERIOD_END);
        $session = static fn(array $fields) => ['object' => 'checkout.session', 'id' => 'cs_1', 'client_reference_id' => 'ORDER1', 'customer' => 'cus_1', 'subscription' => null, ...$fields];
        $invoice = static fn(string $reason, array $fields = []) => [
            'object' => 'invoice',
            'id' => 'in_1',
            'billing_reason' => $reason,
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_1']],
            'lines' => ['object' => 'list', 'data' => [['period' => ['start' => self::PERIOD_END - 100, 'end' => self::PERIOD_END]]]],
            ...$fields,
        ];
        $subscription = static fn(array $fields) => ['object' => 'subscription', 'id' => 'sub_1', 'status' => 'active', 'cancel_at' => null, 'cancel_at_period_end' => false, ...$fields];

        yield 'one-time checkout paid' => ['checkout.session.completed', $session(['status' => 'complete', 'payment_status' => 'paid']), [new CheckoutCompleted('ORDER1', 'cus_1')]];
        yield 'subscription checkout paid' => [
            'checkout.session.completed',
            $session(['status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_1']),
            [new CheckoutCompleted('ORDER1', 'cus_1', 'sub_1', $periodEnd)],
        ];
        yield 'async payment still processing' => ['checkout.session.completed', $session(['status' => 'complete', 'payment_status' => 'unpaid']), []];
        yield 'async payment succeeded' => ['checkout.session.async_payment_succeeded', $session(['status' => 'complete', 'payment_status' => 'paid']), [new CheckoutCompleted('ORDER1', 'cus_1')]];
        yield 'async payment failed' => ['checkout.session.async_payment_failed', $session(['status' => 'complete', 'payment_status' => 'unpaid']), [new CheckoutFailed('ORDER1')]];
        yield 'checkout expired' => ['checkout.session.expired', $session(['status' => 'expired', 'payment_status' => 'unpaid']), [new CheckoutCancelled('ORDER1')]];
        yield 'checkout of someone else' => ['checkout.session.completed', $session(['client_reference_id' => null, 'status' => 'complete', 'payment_status' => 'paid']), []];
        yield 'renewal invoice created' => ['invoice.created', $invoice('subscription_cycle'), [new SubscriptionRenewalStarted('sub_1', 'in_1')]];
        yield 'first invoice created' => ['invoice.created', $invoice('subscription_create'), []];
        yield 'renewal invoice paid' => ['invoice.paid', $invoice('subscription_cycle'), [new SubscriptionRenewed('sub_1', 'in_1', $periodEnd)]];
        yield 'renewal invoice paid, older API version' => [
            'invoice.paid',
            $invoice('subscription_cycle', ['parent' => null, 'subscription' => 'sub_1', 'lines' => ['object' => 'list', 'data' => []]]),
            [new SubscriptionRenewed('sub_1', 'in_1', $periodEnd)],
        ];
        yield 'first invoice paid' => ['invoice.paid', $invoice('subscription_create'), []];
        yield 'renewal payment failed' => ['invoice.payment_failed', $invoice('subscription_cycle'), [new SubscriptionPaymentFailed('sub_1', 'in_1')]];
        yield 'first payment failed' => ['invoice.payment_failed', $invoice('subscription_create'), [new SubscriptionPaymentFailed('sub_1')]];
        yield 'invoice without subscription' => ['invoice.paid', $invoice('manual', ['parent' => null]), []];
        yield 'cancelled at period end' => ['customer.subscription.updated', $subscription(['cancel_at_period_end' => true]), [new SubscriptionUpdated('sub_1', true)]];
        yield 'cancelled at a date' => ['customer.subscription.updated', $subscription(['cancel_at' => self::PERIOD_END]), [new SubscriptionUpdated('sub_1', true)]];
        yield 'resumed' => ['customer.subscription.updated', $subscription([]), [new SubscriptionUpdated('sub_1', false)]];
        yield 'subscription ended' => ['customer.subscription.deleted', $subscription(['status' => 'canceled']), [new SubscriptionEnded('sub_1')]];
        yield 'renewal voided' => ['invoice.voided', $invoice('subscription_cycle'), [new SubscriptionRenewalVoided('sub_1', 'in_1')]];
        yield 'renewal uncollectible' => ['invoice.marked_uncollectible', $invoice('subscription_cycle'), [new SubscriptionRenewalVoided('sub_1', 'in_1')]];
        yield 'first invoice voided' => ['invoice.voided', $invoice('subscription_create'), []];

        $charge = static fn(array $fields) => ['object' => 'charge', 'id' => 'ch_1', 'amount_refunded' => 999, 'metadata' => [], ...$fields];
        yield 'one-time payment refunded' => [
            'charge.refunded',
            $charge(['payment_intent' => 'pi_one']),
            [new PaymentRefunded('ORDER1', amountMinor: 999)],
        ];
        yield 'one-time payment refunded, reference on the charge' => [
            'charge.refunded',
            $charge(['metadata' => ['ecommerce_order_reference' => 'ORDER1']]),
            [new PaymentRefunded('ORDER1', amountMinor: 999)],
        ];
        yield 'first subscription payment refunded' => [
            'charge.refunded',
            $charge(['payment_intent' => 'pi_first']),
            [new PaymentRefunded('ORDER1', amountMinor: 999)],
        ];
        yield 'renewal refunded' => [
            'charge.refunded',
            $charge(['payment_intent' => 'pi_renewal']),
            [new PaymentRefunded(externalPaymentId: 'in_1', amountMinor: 999)],
        ];
        yield 'renewal refunded, older API version' => [
            'charge.refunded',
            $charge(['payment_intent' => 'pi_renewal', 'invoice' => 'in_1']),
            [new PaymentRefunded(externalPaymentId: 'in_1', amountMinor: 999)],
        ];
        yield 'dispute lost on a renewal' => [
            'charge.dispute.closed',
            ['object' => 'dispute', 'id' => 'dp_1', 'status' => 'lost', 'charge' => 'ch_renewal', 'amount' => 999],
            [new PaymentRefunded(externalPaymentId: 'in_1', amountMinor: 999)],
        ];
        yield 'dispute won' => [
            'charge.dispute.closed',
            ['object' => 'dispute', 'id' => 'dp_1', 'status' => 'won', 'charge' => 'ch_1', 'amount' => 999],
            [],
        ];
        yield 'unhandled event' => ['payment_intent.created', ['object' => 'payment_intent', 'id' => 'pi_1'], []];
    }

    public function testRefundFindsThePaymentOfEveryKindOfOrder(): void
    {
        $oneTime = new Order($this->createUser(), $this->createPrice(), StripePaymentProvider::KEY);
        $oneTime->providerReference = 'cs_one';
        $first = new Order($this->createUser(), $this->createPrice(interval: BillingInterval::Month), StripePaymentProvider::KEY);
        $first->providerReference = 'cs_first';
        $renewal = new Order(
            $first->user,
            $first->price ?? self::fail(),
            StripePaymentProvider::KEY,
            new Subscription($first, 'sub_1', new DateTimeImmutable('+1 month')),
        );
        $renewal->providerReference = 'in_1';

        $this->sessions->method('retrieve')->willReturnCallback(static fn(string $id) => Session::constructFrom(match ($id) {
            'cs_one' => ['id' => $id, 'mode' => 'payment', 'payment_intent' => 'pi_one', 'invoice' => 'in_one'],
            'cs_first' => ['id' => $id, 'mode' => 'subscription', 'payment_intent' => null, 'invoice' => 'in_first'],
        }));
        $refunded = [];
        $this->refunds->expects(self::exactly(3))->method('create')->willReturnCallback(
            static function (array $params) use (&$refunded) {
                $refunded[] = $params['payment_intent'];

                return Refund::constructFrom(['id' => 're_1', 'amount' => 999]);
            },
        );

        foreach ([$oneTime, $first, $renewal] as $order) {
            self::assertEquals([new PaymentRefunded($order->reference, amountMinor: 999)], $this->provider->refund($order));
        }
        self::assertSame(['pi_one', 'pi_first', 'pi_renewal'], $refunded);
    }

    public function testSettlementReadsTheBalanceTransactionOfTheOrdersPayment(): void
    {
        $order = new Order($this->createUser(), $this->createPrice(interval: BillingInterval::Month), StripePaymentProvider::KEY);
        self::assertNull($this->provider->getSettlement($order));

        $order->providerReference = 'cs_first';
        $this->sessions->method('retrieve')->willReturn(
            Session::constructFrom(['id' => 'cs_first', 'mode' => 'subscription', 'payment_intent' => null, 'invoice' => 'in_first']),
        );

        $settlement = $this->provider->getSettlement($order);

        self::assertNotNull($settlement);
        self::assertTrue($settlement->gross->isEqualTo(Money::ofMinor(921, 'EUR')));
        self::assertTrue($settlement->net->isEqualTo(Money::ofMinor(864, 'EUR')));
        self::assertTrue($settlement->getFee()->isEqualTo(Money::ofMinor(57, 'EUR')));
    }

    public function testWebhookWithBadSignatureIsRejected(): void
    {
        $request = $this->signedRequest('checkout.session.completed', ['object' => 'checkout.session', 'id' => 'cs_1'], 'whsec_other');

        $this->expectException(InvalidWebhookException::class);
        $this->provider->handleWebhook($request);
    }

    public function testSyncOrderReadsTheCheckoutSession(): void
    {
        $order = new Order($this->createUser(), $this->createPrice(), StripePaymentProvider::KEY);
        self::assertSame([], $this->provider->syncOrder($order));

        $order->providerReference = 'cs_1';
        $this->sessions->expects(self::once())->method('retrieve')->with('cs_1')->willReturn(Session::constructFrom([
            'id' => 'cs_1',
            'client_reference_id' => $order->reference,
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer' => 'cus_1',
        ]));

        self::assertEquals([new CheckoutCompleted($order->reference, 'cus_1')], $this->provider->syncOrder($order));
    }

    public function testCancelSubscriptionAtPeriodEndOrRightAway(): void
    {
        $order = new Order($this->createUser(), $this->createPrice(interval: BillingInterval::Month), StripePaymentProvider::KEY);
        $subscription = new Subscription($order, 'sub_1', new DateTimeImmutable('+1 month'));
        $this->subscriptions->expects(self::once())->method('update')->with('sub_1', ['cancel_at_period_end' => true])
            ->willReturn(StripeSubscription::constructFrom(['id' => 'sub_1', 'status' => 'active', 'cancel_at_period_end' => true, 'cancel_at' => null]));
        $this->subscriptions->expects(self::once())->method('cancel')->with('sub_1')
            ->willReturn(StripeSubscription::constructFrom(['id' => 'sub_1', 'status' => 'canceled', 'cancel_at_period_end' => false, 'cancel_at' => null]));

        self::assertEquals([new SubscriptionUpdated('sub_1', true)], $this->provider->cancelSubscription($subscription, true));
        self::assertEquals([new SubscriptionEnded('sub_1')], $this->provider->cancelSubscription($subscription, false));
    }

    /**
     * Stripe as it answers on API version 2025-03-31 and later: a charge no longer names its invoice, and a
     * subscription's payment intent carries no metadata.
     *
     * @return array<string, object>
     */
    private function mockPaymentLookups(): array
    {
        $paymentIntents = $this->createMock(PaymentIntentService::class);
        $paymentIntents->method('retrieve')->willReturnCallback(static fn(string $id) => PaymentIntent::constructFrom([
            'id' => $id,
            'metadata' => $id === 'pi_one' ? ['ecommerce_order_reference' => 'ORDER1'] : [],
            'latest_charge' => [
                'object' => 'charge',
                'id' => "ch_{$id}",
                'balance_transaction' => ['object' => 'balance_transaction', 'amount' => 921, 'net' => 864, 'currency' => 'eur'],
            ],
        ]));

        $invoicePaymentIntents = ['in_first' => 'pi_first', 'in_1' => 'pi_renewal'];
        $invoicePayments = $this->createMock(InvoicePaymentService::class);
        $invoicePayments->method('all')->willReturnCallback(static function (array $params) use ($invoicePaymentIntents) {
            $invoice = $params['invoice'] ?? array_search($params['payment']['payment_intent'], $invoicePaymentIntents, true);
            $data = $invoice === false ? [] : [[
                'object' => 'invoice_payment',
                'invoice' => $invoice,
                'status' => 'paid',
                'payment' => ['type' => 'payment_intent', 'payment_intent' => $invoicePaymentIntents[$invoice]],
            ]];

            return Collection::constructFrom(['object' => 'list', 'data' => $data]);
        });

        $invoices = $this->createMock(InvoiceService::class);
        $invoices->method('retrieve')->willReturnCallback(static fn(string $id) => Invoice::constructFrom([
            'id' => $id,
            'billing_reason' => $id === 'in_first' ? 'subscription_create' : 'subscription_cycle',
            'parent' => ['type' => 'subscription_details', 'subscription_details' => [
                'subscription' => 'sub_1',
                'metadata' => ['ecommerce_order_reference' => 'ORDER1'],
            ]],
        ]));

        $charges = $this->createMock(ChargeService::class);
        $charges->method('retrieve')->willReturnCallback(static fn(string $id) => Charge::constructFrom([
            'id' => $id,
            'payment_intent' => 'pi_renewal',
            'metadata' => [],
        ]));

        return [
            'paymentIntents' => $paymentIntents,
            'invoicePayments' => $invoicePayments,
            'invoices' => $invoices,
            'charges' => $charges,
        ];
    }

    /**
     * @param array<string, mixed> $object
     */
    private function signedRequest(string $type, array $object, string $secret = self::WEBHOOK_SECRET): Request
    {
        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return Request::create('/store/webhook/stripe', 'POST', server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"], content: $payload);
    }
}

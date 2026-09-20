<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentRefunded;
use Forumify\Ecommerce\PaymentProvider\InvoiceDocument;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderCapability;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use LogicException;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

class StripePaymentProvider implements PaymentProviderInterface
{
    public const KEY = 'stripe';

    public function __construct(
        private readonly PaymentProviderConfigRepository $configRepository,
        private readonly StripeClientFactory $clientFactory,
        private readonly StripeCatalog $catalog,
        private readonly StripeEventMapper $eventMapper,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'Stripe';
    }

    public function getSettingsFormType(): string
    {
        return StripeSettingsType::class;
    }

    public function isConfigured(PaymentProviderConfig $config): bool
    {
        return StripeSettings::fromConfig($config)->isConfigured();
    }

    public function supports(ProductPrice $price): bool
    {
        return true;
    }

    public function getCapabilities(): array
    {
        return [
            PaymentProviderCapability::Refund,
            PaymentProviderCapability::Invoice,
            PaymentProviderCapability::HostedPayment,
        ];
    }

    public function startCheckout(Order $order, CheckoutUrls $urls): CheckoutRedirect
    {
        $price = $order->price ?? throw new LogicException('An order without a price can not be paid.');
        $user = $order->user ?? throw new LogicException('An order without a customer can not be paid.');
        $settings = $this->getSettings();
        $client = $this->clientFactory->create($settings);

        $recurring = $order->priceType === PriceType::Recurring;
        $metadata = [StripeEventMapper::ORDER_REFERENCE_KEY => $order->reference];
        $session = $client->checkout->sessions->create([
            'mode' => $recurring ? Session::MODE_SUBSCRIPTION : Session::MODE_PAYMENT,
            'line_items' => [['price' => $this->catalog->syncPrice($client, $settings, $price), 'quantity' => 1]],
            'customer' => $this->catalog->syncCustomer($client, $settings, $user),
            'client_reference_id' => $order->reference,
            'success_url' => $urls->successUrl,
            'cancel_url' => $urls->cancelUrl,
            'metadata' => $metadata,
            $recurring ? 'subscription_data' : 'payment_intent_data' => ['metadata' => $metadata],
            ...($recurring ? [] : ['invoice_creation' => ['enabled' => true]]),
        ]);

        return new CheckoutRedirect(
            $session->url ?? throw new UnexpectedValueException('Stripe did not return a checkout URL.'),
            $session->id,
        );
    }

    public function syncOrder(Order $order): array
    {
        if ($order->providerReference === null) {
            return [];
        }

        $client = $this->createClient();

        $session = $client->checkout->sessions->retrieve($order->providerReference);

        return $this->eventMapper->fromSession($client, $session);
    }

    public function handleWebhook(Request $request): array
    {
        $settings = $this->getSettings();

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get('Stripe-Signature') ?? '',
                $settings->webhookSecret,
            );
        } catch (SignatureVerificationException | UnexpectedValueException $e) {
            throw new InvalidWebhookException($e->getMessage(), previous: $e);
        }

        return $this->eventMapper->fromEvent($this->clientFactory->create($settings), $event);
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): array
    {
        $client = $this->createClient();
        $stripeSubscription = $atPeriodEnd
            ? $client->subscriptions->update($subscription->providerReference, ['cancel_at_period_end' => true])
            : $client->subscriptions->cancel($subscription->providerReference);

        return $this->eventMapper->fromSubscription($stripeSubscription);
    }

    public function refund(Order $order): array
    {
        $client = $this->createClient();
        $paymentIntent = $this->findPaymentIntentId($client, $order)
            ?? throw new UnexpectedValueException('Stripe has no payment to refund for this order.');

        $refund = $client->refunds->create(['payment_intent' => $paymentIntent]);

        return [new PaymentRefunded($order->reference, amountMinor: $refund->amount)];
    }

    public function getHostedPaymentUrl(Order $order): ?string
    {
        if ($order->providerReference === null) {
            return null;
        }

        $client = $this->createClient();
        if ($order->isRenewal()) {
            return $client->invoices->retrieve($order->providerReference)->hosted_invoice_url;
        }

        return $client->checkout->sessions->retrieve($order->providerReference)->url;
    }

    public function getInvoice(Order $order): ?InvoiceDocument
    {
        $pdf = $this->findInvoice($this->createClient(), $order)?->invoice_pdf;

        return $pdf === null
            ? null
            : InvoiceDocument::url($pdf, 'invoice-' . strtolower($order->reference) . '.pdf');
    }

    private function findInvoice(StripeClient $client, Order $order): ?Invoice
    {
        if ($order->providerReference === null) {
            return null;
        }

        if ($order->isRenewal()) {
            return $client->invoices->retrieve($order->providerReference);
        }

        $invoiceId = StripeEventMapper::id(
            $client->checkout->sessions->retrieve($order->providerReference)['invoice'] ?? null,
        );

        return $invoiceId === null ? null : $client->invoices->retrieve($invoiceId);
    }

    private function findPaymentIntentId(StripeClient $client, Order $order): ?string
    {
        if ($order->providerReference === null) {
            return null;
        }

        if (!$order->isRenewal()) {
            return StripeEventMapper::id(
                $client->checkout->sessions->retrieve($order->providerReference)['payment_intent'] ?? null,
            );
        }

        return StripeEventMapper::invoicePaymentIntentId($client->invoices->retrieve($order->providerReference));
    }

    private function getSettings(): StripeSettings
    {
        $config = $this->configRepository->findOneByKey(self::KEY) ?? new PaymentProviderConfig(self::KEY);

        return StripeSettings::fromConfig($config);
    }

    private function createClient(): StripeClient
    {
        return $this->clientFactory->create($this->getSettings());
    }
}

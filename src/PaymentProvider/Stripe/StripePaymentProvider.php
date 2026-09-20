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
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use LogicException;
use Stripe\Checkout\Session;
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

    public function startCheckout(Order $order, CheckoutUrls $urls): CheckoutRedirect
    {
        $price = $order->price ?? throw new LogicException('An order without a price can not be paid.');
        $user = $order->user ?? throw new LogicException('An order without a customer can not be paid.');
        $settings = $this->getSettings();
        $client = $this->clientFactory->create($settings);

        $recurring = $order->priceType === PriceType::Recurring;
        $metadata = ['ecommerce_order_reference' => $order->reference];
        $session = $client->checkout->sessions->create([
            'mode' => $recurring ? Session::MODE_SUBSCRIPTION : Session::MODE_PAYMENT,
            'line_items' => [['price' => $this->catalog->syncPrice($client, $settings, $price), 'quantity' => 1]],
            'customer' => $this->catalog->syncCustomer($client, $settings, $user),
            'client_reference_id' => $order->reference,
            'success_url' => $urls->successUrl,
            'cancel_url' => $urls->cancelUrl,
            'metadata' => $metadata,
            $recurring ? 'subscription_data' : 'payment_intent_data' => ['metadata' => $metadata],
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

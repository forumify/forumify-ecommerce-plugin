<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Fake;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[WhenNot(env: 'prod')]
class FakePaymentProvider implements PaymentProviderInterface
{
    public const KEY = 'fake';
    public const SIGNATURE = 'valid';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'Fake payments (development only)';
    }

    public function getSettingsFormType(): string
    {
        return FakeSettingsType::class;
    }

    public function isConfigured(PaymentProviderConfig $config): bool
    {
        return true;
    }

    public function supports(ProductPrice $price): bool
    {
        return true;
    }

    public function startCheckout(Order $order, CheckoutUrls $urls): CheckoutRedirect
    {
        return new CheckoutRedirect(
            $this->urlGenerator->generate('ecommerce_fake_payment_pay', ['reference' => $order->reference], UrlGeneratorInterface::ABSOLUTE_URL),
            'fake_' . $order->reference,
        );
    }

    public function syncOrder(Order $order): array
    {
        return [];
    }

    public function handleWebhook(Request $request): array
    {
        if ($request->headers->get('X-Fake-Signature') !== self::SIGNATURE) {
            throw new InvalidWebhookException();
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            throw new InvalidWebhookException();
        }

        $reference = $payload['order'] ?? null;
        if (!is_string($reference)) {
            throw new InvalidWebhookException();
        }

        return match ($payload['type'] ?? null) {
            'paid' => [new CheckoutCompleted($reference)],
            'failed' => [new CheckoutFailed($reference)],
            default => [],
        };
    }
}

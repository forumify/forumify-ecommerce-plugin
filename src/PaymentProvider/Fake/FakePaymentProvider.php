<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Fake;

use DateTimeImmutable;
use Exception;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionEnded;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionPaymentFailed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewalStarted;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionUpdated;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[WhenNot(env: 'prod')]
class FakePaymentProvider implements PaymentProviderInterface
{
    public const KEY = 'fake';
    public const SIGNATURE = 'valid';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public static function completed(string $orderReference): CheckoutCompleted
    {
        return new CheckoutCompleted($orderReference, externalSubscriptionId: 'fake_sub_' . $orderReference);
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
        $payUrl = $this->urlGenerator->generate(
            'ecommerce_fake_payment_pay',
            ['reference' => $order->reference],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new CheckoutRedirect($payUrl, 'fake_' . $order->reference);
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

        $event = $this->toEvent($payload);

        return $event === null ? [] : [$event];
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): array
    {
        return $atPeriodEnd
            ? [new SubscriptionUpdated($subscription->providerReference, true)]
            : [new SubscriptionEnded($subscription->providerReference)];
    }

    /**
     * @param array<mixed> $payload
     */
    private function toEvent(array $payload): ?PaymentEvent
    {
        $order = self::string($payload, 'order');
        $subscription = self::string($payload, 'subscription');
        $payment = self::string($payload, 'payment');

        return match ($payload['type'] ?? null) {
            'paid' => self::completed($order ?? throw new InvalidWebhookException()),
            'failed' => new CheckoutFailed($order ?? throw new InvalidWebhookException()),
            'renewal_started' => new SubscriptionRenewalStarted(
                $subscription ?? throw new InvalidWebhookException(),
                $payment ?? throw new InvalidWebhookException(),
            ),
            'renewed' => $this->renewed(
                $subscription ?? throw new InvalidWebhookException(),
                $payment ?? 'fake_inv_' . bin2hex(random_bytes(6)),
                self::date($payload, 'period_end'),
            ),
            'payment_failed' => new SubscriptionPaymentFailed(
                $subscription ?? throw new InvalidWebhookException(),
                $payment,
            ),
            'updated' => new SubscriptionUpdated(
                $subscription ?? throw new InvalidWebhookException(),
                ($payload['cancel_at_period_end'] ?? false) === true,
            ),
            'ended' => new SubscriptionEnded($subscription ?? throw new InvalidWebhookException()),
            default => null,
        };
    }

    private function renewed(string $reference, string $payment, ?DateTimeImmutable $periodEnd): SubscriptionRenewed
    {
        if ($periodEnd === null) {
            $subscription = $this->subscriptionRepository->findOneByProviderReference(self::KEY, $reference)
                ?? throw new InvalidWebhookException();
            $periodEnd = $subscription->initialOrder->periodEndFrom($subscription->currentPeriodEnd);
        }

        return new SubscriptionRenewed($reference, $payment, $periodEnd);
    }

    /**
     * @param array<mixed> $payload
     */
    private static function string(array $payload, string $key): ?string
    {
        return is_string($payload[$key] ?? null) ? $payload[$key] : null;
    }

    /**
     * @param array<mixed> $payload
     */
    private static function date(array $payload, string $key): ?DateTimeImmutable
    {
        $value = self::string($payload, $key);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidWebhookException();
        }
    }
}

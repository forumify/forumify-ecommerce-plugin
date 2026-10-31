<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag(PaymentProviderInterface::TAG)]
interface PaymentProviderInterface
{
    public const TAG = 'ecommerce.payment_provider';

    public function getKey(): string;

    public function getLabel(): string;

    /**
     * @return class-string<FormTypeInterface<array<string, mixed>>>
     */
    public function getSettingsFormType(): string;

    public function isConfigured(PaymentProviderConfig $config): bool;

    public function supports(ProductPrice $price): bool;

    /**
     * Everything beyond taking a payment is optional. Only the capabilities listed here are offered in the
     * admin and to buyers.
     *
     * @return list<PaymentProviderCapability>
     */
    public function getCapabilities(): array;

    public function startCheckout(Order $order, CheckoutUrls $urls): CheckoutRedirect;

    /**
     * @return list<PaymentEvent>
     */
    public function syncOrder(Order $order): array;

    /**
     * @return list<PaymentEvent>
     * @throws InvalidWebhookException
     */
    public function handleWebhook(Request $request): array;

    /**
     * @return list<PaymentEvent>
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): array;

    /**
     * Send the full amount of a paid order back to the buyer.
     *
     * @return list<PaymentEvent>
     */
    public function refund(Order $order): array;

    /**
     * A page where the buyer can pay an unpaid order and update their payment method, or null when there
     * is nothing to settle.
     */
    public function getHostedPaymentUrl(Order $order): ?string;

    public function getInvoice(Order $order): ?InvoiceDocument;

    /**
     * What the provider paid out for a paid order, in the currency it settles in, or null while that isn't
     * known yet.
     */
    public function getSettlement(Order $order): ?Settlement;
}

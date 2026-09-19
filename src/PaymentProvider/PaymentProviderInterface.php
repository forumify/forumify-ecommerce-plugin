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
}

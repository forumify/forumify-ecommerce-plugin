<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormTypeInterface;

#[AutoconfigureTag(FulfillmentTypeInterface::TAG)]
interface FulfillmentTypeInterface
{
    public const TAG = 'ecommerce.fulfillment_type';

    public static function getKey(): string;

    /**
     * A label or translation key.
     */
    public function getLabel(): string;

    /**
     * Whether an admin has to mark paid orders as completed.
     */
    public function requiresManualFulfillment(): bool;

    /**
     * The form for Product::$fulfillmentSettings, with array data.
     *
     * @return class-string<FormTypeInterface<array<string, mixed>>>|null
     */
    public function getProductSettingsFormType(): ?string;

    /**
     * The form the buyer fills in at checkout. It gets the "product", "price" and "user" options.
     *
     * @return class-string<FormTypeInterface<mixed>>|null
     */
    public function getCheckoutFormType(): ?string;

    /**
     * Turns the checkout form's data into Order::$fulfillmentData: scalars only
     * (ids and display labels, never entities).
     *
     * @return array<string, mixed>
     */
    public function normalizeCheckoutData(mixed $data): array;

    /**
     * A template rendered with "order" on the order pages and in the order notifications.
     */
    public function getDetailsTemplate(): ?string;
}

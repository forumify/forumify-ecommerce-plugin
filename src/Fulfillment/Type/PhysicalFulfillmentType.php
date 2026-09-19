<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Type;

use Forumify\Ecommerce\Fulfillment\Form\ShippingAddressType;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeInterface;

class PhysicalFulfillmentType implements FulfillmentTypeInterface
{
    public const KEY = 'physical';

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'ecommerce.fulfillment.physical.label';
    }

    public function requiresManualFulfillment(): bool
    {
        return true;
    }

    public function getProductSettingsFormType(): ?string
    {
        return null;
    }

    public function getCheckoutFormType(): string
    {
        return ShippingAddressType::class;
    }

    public function normalizeCheckoutData(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $address = [];
        foreach (ShippingAddressType::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $address[$field] = is_string($value) ? trim($value) : '';
        }

        return $address;
    }

    public function getDetailsTemplate(): string
    {
        return '@ForumifyEcommercePlugin/fulfillment/physical_details.html.twig';
    }
}

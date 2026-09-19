<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Type;

use Forumify\Ecommerce\Fulfillment\Form\ManualCheckoutType;
use Forumify\Ecommerce\Fulfillment\Form\ManualSettingsType;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeInterface;

class ManualFulfillmentType implements FulfillmentTypeInterface
{
    public const KEY = 'manual';

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'ecommerce.fulfillment.manual.label';
    }

    public function requiresManualFulfillment(): bool
    {
        return true;
    }

    public function getProductSettingsFormType(): string
    {
        return ManualSettingsType::class;
    }

    public function getCheckoutFormType(): string
    {
        return ManualCheckoutType::class;
    }

    public function normalizeCheckoutData(mixed $data): array
    {
        if (!is_array($data) || !is_string($data['value'] ?? null) || !is_string($data['label'] ?? null)) {
            return [];
        }

        return ['label' => $data['label'], 'value' => trim($data['value'])];
    }

    public function getDetailsTemplate(): string
    {
        return '@ForumifyEcommercePlugin/fulfillment/manual_details.html.twig';
    }
}

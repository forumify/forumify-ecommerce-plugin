<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Type;

use Forumify\Ecommerce\Fulfillment\FulfillmentTypeInterface;

class InstantFulfillmentType implements FulfillmentTypeInterface
{
    public const KEY = 'instant';

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'ecommerce.fulfillment.instant.label';
    }

    public function requiresManualFulfillment(): bool
    {
        return false;
    }

    public function getProductSettingsFormType(): ?string
    {
        return null;
    }

    public function getCheckoutFormType(): ?string
    {
        return null;
    }

    public function normalizeCheckoutData(mixed $data): array
    {
        return [];
    }

    public function getDetailsTemplate(): ?string
    {
        return null;
    }
}

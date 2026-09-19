<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use Forumify\Ecommerce\Entity\PaymentProviderConfig;

class StripeSettings
{
    public const SECRET_KEY = 'secretKey';
    public const WEBHOOK_SECRET = 'webhookSecret';

    public bool $configured {
        get => $this->secretKey !== '' && $this->webhookSecret !== '';
    }

    public bool $testMode {
        get => str_contains($this->secretKey, '_test_');
    }

    public string $referenceKey {
        get => $this->testMode ? StripePaymentProvider::KEY . '_test' : StripePaymentProvider::KEY;
    }

    public function __construct(
        public readonly string $secretKey,
        public readonly string $webhookSecret,
    ) {
    }

    public static function fromConfig(PaymentProviderConfig $config): self
    {
        return new self(
            self::string($config->settings, self::SECRET_KEY),
            self::string($config->settings, self::WEBHOOK_SECRET),
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function string(array $settings, string $key): string
    {
        return is_string($settings[$key] ?? null) ? trim($settings[$key]) : '';
    }
}

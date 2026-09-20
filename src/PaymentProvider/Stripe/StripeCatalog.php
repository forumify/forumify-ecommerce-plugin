<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Entity\ProviderCustomer;
use Forumify\Ecommerce\Repository\ProductPriceRepository;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use Forumify\Ecommerce\Repository\ProviderCustomerRepository;
use LogicException;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCatalog
{
    public function __construct(
        private readonly ProductTierRepository $tierRepository,
        private readonly ProductPriceRepository $priceRepository,
        private readonly ProviderCustomerRepository $customerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncPrice(StripeClient $client, StripeSettings $settings, ProductPrice $price): string
    {
        $key = $settings->getReferenceKey();
        if (isset($price->providerReferences[$key])) {
            return $price->providerReferences[$key];
        }

        $tier = $price->tier;
        $params = [
            'product' => $this->syncTier($client, $key, $tier),
            'currency' => strtolower($price->currency),
            'unit_amount' => $price->amountMinor,
            'metadata' => [
                'ecommerce_product_id' => (string)$tier->product->getId(),
                'ecommerce_tier_key' => $tier->key,
                'ecommerce_price_id' => (string)$price->getId(),
            ],
        ];
        if ($price->type === PriceType::Recurring) {
            $interval = $price->billingInterval
                ?? throw new LogicException('A recurring price needs a billing interval.');
            $params['recurring'] = ['interval' => $interval->value, 'interval_count' => $price->intervalCount ?? 1];
        }

        $stripePrice = $client->prices->create($params);
        $price->providerReferences[$key] = $stripePrice->id;
        $this->priceRepository->save($price);

        $this->archivePredecessors($client, $key, $price);

        return $stripePrice->id;
    }

    public function syncCustomer(StripeClient $client, StripeSettings $settings, User $user): string
    {
        $key = $settings->getReferenceKey();
        $existing = $this->customerRepository->findOneFor($user, $key);
        if ($existing !== null) {
            return $existing->externalId;
        }

        $customer = $client->customers->create(array_filter([
            'email' => $user->getEmail(),
            'name' => $user->getDisplayName(),
            'metadata' => [
                'forumify_user_id' => (string)$user->getId(),
                'forumify_username' => $user->getUsername(),
            ],
        ]));
        $this->customerRepository->save(new ProviderCustomer($user, $key, $customer->id));

        return $customer->id;
    }

    private function syncTier(StripeClient $client, string $key, ProductTier $tier): string
    {
        if (isset($tier->providerReferences[$key])) {
            return $tier->providerReferences[$key];
        }

        $title = $tier->product->title;
        $product = $client->products->create([
            'name' => $tier->label === $title ? $title : "{$title} – {$tier->label}",
            'metadata' => [
                'ecommerce_product_id' => (string)$tier->product->getId(),
                'ecommerce_product_key' => $tier->product->key,
                'ecommerce_tier_key' => $tier->key,
            ],
        ]);
        $tier->providerReferences[$key] = $product->id;
        $this->tierRepository->save($tier);

        return $product->id;
    }

    private function archivePredecessors(StripeClient $client, string $key, ProductPrice $price): void
    {
        foreach ($price->tier->prices as $predecessor) {
            if ($predecessor->replacedBy !== $price) {
                continue;
            }

            $reference = $predecessor->providerReferences[$key] ?? null;
            if ($reference === null) {
                $this->archivePredecessors($client, $key, $predecessor);
                continue;
            }

            try {
                $client->prices->update($reference, ['active' => false]);
            } catch (ApiErrorException $e) {
                $this->logger->warning('Archiving a replaced price at Stripe failed.', [
                    'price' => $reference,
                    'exception' => $e,
                ]);
            }
        }
    }
}

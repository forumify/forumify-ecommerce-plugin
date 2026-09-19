<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Psr\Log\LoggerInterface;
use Throwable;

class SubscriptionCancellationService
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly PaymentEventHandler $eventHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws SubscriptionException
     */
    public function cancel(Subscription $subscription, bool $atPeriodEnd): void
    {
        if ($subscription->status === SubscriptionStatus::Ended) {
            throw SubscriptionException::alreadyEnded();
        }

        $provider = $this->registry->findConfigured($subscription->providerKey)
            ?? throw SubscriptionException::providerUnavailable();

        try {
            $events = $provider->cancelSubscription($subscription, $atPeriodEnd);
        } catch (Throwable $e) {
            $this->logger->error('Cancelling a subscription with its payment provider failed.', [
                'subscription' => $subscription->providerReference,
                'provider' => $provider->getKey(),
                'exception' => $e,
            ]);
            throw SubscriptionException::providerFailed();
        }

        $this->eventHandler->handleAll($events, $provider->getKey());
    }
}

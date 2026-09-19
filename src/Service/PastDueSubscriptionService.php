<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use DateTimeImmutable;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Psr\Log\LoggerInterface;

class PastDueSubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function endExpiredGracePeriods(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $ended = 0;

        foreach ($this->subscriptionRepository->findPastDue() as $subscription) {
            if ($subscription->graceEndsAt === null || $subscription->graceEndsAt > $now) {
                continue;
            }

            try {
                $this->cancellationService->cancel($subscription, false);
                $ended++;
            } catch (SubscriptionException $e) {
                $this->logger->warning('A past due subscription could not be ended, it is retried on the next run.', [
                    'subscription' => $subscription->providerReference,
                    'provider' => $subscription->providerKey,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return $ended;
    }
}

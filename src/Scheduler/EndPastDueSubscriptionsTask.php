<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Scheduler;

use Forumify\Ecommerce\Service\PastDueSubscriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand('ecommerce:subscriptions:end-past-due')]
#[AsPeriodicTask('1 hour', jitter: 120)]
class EndPastDueSubscriptionsTask
{
    public function __construct(private readonly PastDueSubscriptionService $pastDueSubscriptionService)
    {
    }

    public function __invoke(): int
    {
        $this->pastDueSubscriptionService->endExpiredGracePeriods();

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Scheduler;

use Forumify\Ecommerce\Service\CancellationRequestService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('ecommerce:subscriptions:remind-cancellations')]
#[AsCronTask('@daily', jitter: 1800)]
class RemindCancellationRequestsTask
{
    public function __construct(private readonly CancellationRequestService $cancellationRequests)
    {
    }

    public function __invoke(): int
    {
        $this->cancellationRequests->remindPending();

        return Command::SUCCESS;
    }
}

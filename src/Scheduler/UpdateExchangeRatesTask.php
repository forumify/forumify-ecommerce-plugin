<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Scheduler;

use Forumify\Ecommerce\Service\ExchangeRateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand('ecommerce:exchange-rates:update')]
#[AsCronTask('@daily', jitter: 1800)]
class UpdateExchangeRatesTask
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService)
    {
    }

    public function __invoke(): int
    {
        $this->exchangeRateService->refresh();

        return Command::SUCCESS;
    }
}

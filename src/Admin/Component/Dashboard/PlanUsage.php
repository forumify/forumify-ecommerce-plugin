<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Forumify\Ecommerce\Revenue\RevenueCapService;
use Forumify\Ecommerce\Revenue\RevenueUsage;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    'Ecommerce\\Dashboard\\PlanUsage',
    '@ForumifyEcommercePlugin/admin/dashboard/components/plan_usage.html.twig',
)]
class PlanUsage
{
    private ?RevenueUsage $usage = null;

    public function __construct(private readonly RevenueCapService $revenueCapService)
    {
    }

    public function getUsage(): RevenueUsage
    {
        return $this->usage ??= $this->revenueCapService->getUsage();
    }
}

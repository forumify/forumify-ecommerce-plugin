<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use DateTimeImmutable;
use Forumify\Core\Component\Graph\AbstractGraph;
use Forumify\Core\Component\Graph\GraphDataPoint;
use Forumify\Ecommerce\Revenue\RevenueCapService;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    'Ecommerce\\Dashboard\\RevenueGraph',
    '@ForumifyEcommercePlugin/admin/dashboard/components/revenue_graph.html.twig',
)]
class RevenueGraph extends AbstractGraph
{
    public const MONTHS = 12;

    public function __construct(private readonly RevenueCapService $revenueCapService)
    {
    }

    public function getType(): string
    {
        return self::TYPE_LINE;
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $points = [];
        foreach ($this->revenueCapService->getMonthlyRevenue(self::MONTHS) as $month => $revenue) {
            $label = DateTimeImmutable::createFromFormat('Y-m-d', "$month-01");

            $points[] = new GraphDataPoint(
                $label !== false ? $label->format('M') : $month,
                $revenue->getAmount()->toFloat(),
            );
        }

        return $points;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Forumify\Ecommerce\Revenue\Mrr;
use Forumify\Ecommerce\Revenue\MrrService;
use Forumify\Ecommerce\Revenue\ProductSalesReport;
use Forumify\Ecommerce\Revenue\ProductSalesService;
use Forumify\Ecommerce\Revenue\SalesPeriod;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Ecommerce\\Dashboard\\Kpis', '@ForumifyEcommercePlugin/admin/dashboard/components/kpis.html.twig')]
class Kpis
{
    private ?ProductSalesReport $thisMonth = null;

    public function __construct(
        private readonly MrrService $mrrService,
        private readonly ProductSalesService $productSalesService,
    ) {
    }

    public function getMrr(): Mrr
    {
        return $this->mrrService->getMrr();
    }

    public function getThisMonth(): ProductSalesReport
    {
        return $this->thisMonth ??= $this->productSalesService->getReport(SalesPeriod::ThisMonth);
    }
}

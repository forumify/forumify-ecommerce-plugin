<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Forumify\Ecommerce\Revenue\ProductSalesReport;
use Forumify\Ecommerce\Revenue\ProductSalesService;
use Forumify\Ecommerce\Revenue\SalesPeriod;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    'Ecommerce\\Dashboard\\ProductBreakdown',
    '@ForumifyEcommercePlugin/admin/dashboard/components/product_breakdown.html.twig',
)]
#[IsGranted('ecommerce.admin.dashboard')]
class ProductBreakdown
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $period = SalesPeriod::LastMonth->value;

    /** @var list<string> */
    #[LiveProp]
    public array $expanded = [];

    private ?ProductSalesReport $report = null;

    public function __construct(private readonly ProductSalesService $productSalesService)
    {
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $product): void
    {
        $this->expanded = in_array($product, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$product]))
            : [...$this->expanded, $product];
    }

    /**
     * @return list<SalesPeriod>
     */
    public function getPeriods(): array
    {
        return [SalesPeriod::ThisMonth, SalesPeriod::LastMonth, SalesPeriod::Last3Months, SalesPeriod::Last12Months];
    }

    public function getReport(): ProductSalesReport
    {
        $period = SalesPeriod::tryFrom($this->period);

        return $this->report ??= $this->productSalesService->getReport(
            in_array($period, $this->getPeriods(), true) ? $period : SalesPeriod::LastMonth,
        );
    }
}

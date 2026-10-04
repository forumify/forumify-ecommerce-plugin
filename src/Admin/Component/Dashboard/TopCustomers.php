<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component\Dashboard;

use Forumify\Ecommerce\Revenue\SalesPeriod;
use Forumify\Ecommerce\Revenue\TopCustomer;
use Forumify\Ecommerce\Revenue\TopCustomerService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    'Ecommerce\\Dashboard\\TopCustomers',
    '@ForumifyEcommercePlugin/admin/dashboard/components/top_customers.html.twig',
)]
#[IsGranted('ecommerce.admin.dashboard')]
class TopCustomers
{
    use DefaultActionTrait;

    public const LIMIT = 10;

    #[LiveProp(writable: true)]
    public string $period = SalesPeriod::AllTime->value;

    /** @var list<TopCustomer>|null */
    private ?array $customers = null;

    public function __construct(private readonly TopCustomerService $topCustomerService)
    {
    }

    /**
     * @return list<SalesPeriod>
     */
    public function getPeriods(): array
    {
        return SalesPeriod::cases();
    }

    /**
     * @return list<TopCustomer>
     */
    public function getCustomers(): array
    {
        return $this->customers ??= $this->topCustomerService->getTopCustomers(
            SalesPeriod::tryFrom($this->period) ?? SalesPeriod::AllTime,
            self::LIMIT,
        );
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Money\PriceFormatter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('Ecommerce\\SubscriptionTable', '@ForumifyEcommercePlugin/admin/components/subscription_table.html.twig')]
#[IsGranted('ecommerce.admin.subscriptions.view')]
class SubscriptionTable extends AbstractDoctrineTable
{
    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $statusFilter = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PriceFormatter $priceFormatter,
        private readonly TimezoneResolver $timezoneResolver,
    ) {
        $this->sort = ['createdAt' => self::SORT_DESC];
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    /**
     * @return array<string, string>
     */
    public function getStatusChoices(): array
    {
        $choices = [];
        foreach (SubscriptionStatus::cases() as $status) {
            $choices[$status->value] = $this->translator->trans('ecommerce.subscription_status.' . $status->value);
        }

        return $choices;
    }

    protected function getEntityClass(): string
    {
        return Subscription::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('product', [
                'field' => 'initialOrder.productTitle',
                'label' => $this->translator->trans('ecommerce.admin.order.product'),
                'renderer' => static fn (string $title, Subscription $subscription) => htmlspecialchars("$title - {$subscription->initialOrder->tierLabel}"),
            ])
            ->addColumn('customer', [
                'field' => 'user?.username',
                'label' => $this->translator->trans('ecommerce.admin.order.customer'),
                'renderer' => static fn (?string $username) => htmlspecialchars($username ?? '-'),
            ])
            ->addColumn('price', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.order.price'),
                'renderer' => fn (mixed $_, Subscription $subscription) => htmlspecialchars($this->priceFormatter->format($subscription->initialOrder)),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addColumn('status', [
                'field' => 'status',
                'label' => $this->translator->trans('ecommerce.admin.order.status'),
                'renderer' => fn (SubscriptionStatus $status) => $this->translator->trans('ecommerce.subscription_status.' . $status->value),
                'searchable' => false,
            ])
            ->addColumn('createdAt', [
                'field' => 'createdAt',
                'label' => $this->translator->trans('ecommerce.admin.subscription.started'),
                'renderer' => fn (DateTime $date) => DateTimeImmutable::createFromInterface($date)->setTimezone($this->timezoneResolver->getTimezone())->format('Y-m-d'),
                'searchable' => false,
            ])
            ->addColumn('currentPeriodEnd', [
                'field' => 'currentPeriodEnd',
                'label' => $this->translator->trans('ecommerce.admin.subscription.period_end'),
                'renderer' => fn (DateTimeImmutable $date) => $date->setTimezone($this->timezoneResolver->getTimezone())->format('Y-m-d'),
                'searchable' => false,
            ])
            ->addActionColumn(fn (int $id) => $this->renderAction('ecommerce_admin_subscriptions_view', ['id' => $id], 'eye'))
        ;
    }

    protected function getQuery(array $search): QueryBuilder
    {
        $qb = parent::getQuery($search);

        $status = SubscriptionStatus::tryFrom($this->statusFilter);
        if ($status !== null) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }
}

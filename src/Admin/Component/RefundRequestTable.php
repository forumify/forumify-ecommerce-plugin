<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\RefundRequestStatus;
use Forumify\Ecommerce\Money\PriceFormatter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent(
    'Ecommerce\\RefundRequestTable',
    '@ForumifyEcommercePlugin/admin/components/refund_request_table.html.twig',
)]
#[IsGranted('ecommerce.admin.orders.refund')]
class RefundRequestTable extends AbstractDoctrineTable
{
    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $statusFilter = RefundRequestStatus::Pending->value;

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
        foreach (RefundRequestStatus::cases() as $status) {
            $choices[$status->value] = $this->translator->trans('ecommerce.refund_status.' . $status->value);
        }

        return $choices;
    }

    protected function getEntityClass(): string
    {
        return RefundRequest::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('order', [
                'field' => 'order.reference',
                'label' => $this->translator->trans('ecommerce.admin.order.reference'),
                'renderer' => static fn(string $reference) => '<code>' . htmlspecialchars($reference) . '</code>',
            ])
            ->addColumn('product', [
                'field' => 'order.productTitle',
                'label' => $this->translator->trans('ecommerce.admin.order.product'),
                'renderer' => static fn(mixed $_, RefundRequest $request) => htmlspecialchars(
                    $request->order->productTitle . ' - ' . $request->order->tierLabel,
                ),
            ])
            ->addColumn('customer', [
                'field' => 'user?.username',
                'label' => $this->translator->trans('ecommerce.admin.order.customer'),
                'renderer' => static fn(?string $username) => htmlspecialchars($username ?? '-'),
            ])
            ->addColumn('price', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.order.price'),
                'renderer' => fn(mixed $_, RefundRequest $request) => htmlspecialchars(
                    $this->priceFormatter->format($request->order),
                ),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addColumn('status', [
                'field' => 'status',
                'label' => $this->translator->trans('ecommerce.admin.order.status'),
                'renderer' => fn(RefundRequestStatus $status) =>
                    $this->translator->trans('ecommerce.refund_status.' . $status->value),
                'searchable' => false,
            ])
            ->addColumn('createdAt', [
                'field' => 'createdAt',
                'label' => $this->translator->trans('ecommerce.admin.refund.requested_at'),
                'renderer' => fn(DateTime $date) => DateTimeImmutable::createFromInterface($date)
                    ->setTimezone($this->timezoneResolver->getTimezone())
                    ->format('Y-m-d H:i'),
                'searchable' => false,
            ])
            ->addActionColumn(fn(mixed $_, RefundRequest $request) => $this->renderAction(
                'ecommerce_admin_orders_view',
                ['id' => $request->order->getId()],
                'eye',
            ))
        ;
    }

    protected function getQuery(array $search): QueryBuilder
    {
        $qb = parent::getQuery($search);

        $status = RefundRequestStatus::tryFrom($this->statusFilter);
        if ($status !== null) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }
}

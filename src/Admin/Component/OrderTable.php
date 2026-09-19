<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Money\PriceFormatter;
use Forumify\Ecommerce\Repository\ProductRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('Ecommerce\\OrderTable', '@ForumifyEcommercePlugin/admin/components/order_table.html.twig')]
#[IsGranted('ecommerce.admin.orders.view')]
class OrderTable extends AbstractDoctrineTable
{
    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $statusFilter = '';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $productFilter = '';

    #[LiveProp]
    public ?int $subscription = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PriceFormatter $priceFormatter,
        private readonly ProductRepository $productRepository,
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
        foreach (OrderStatus::cases() as $status) {
            $choices[$status->value] = $this->translator->trans('ecommerce.order_status.' . $status->value);
        }

        return $choices;
    }

    /**
     * @return array<int, string>
     */
    public function getProductChoices(): array
    {
        $choices = [];
        foreach ($this->productRepository->findBy([], ['title' => 'ASC']) as $product) {
            $choices[$product->getId()] = $product->title;
        }

        return $choices;
    }

    protected function getEntityClass(): string
    {
        return Order::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('reference', [
                'field' => 'reference',
                'label' => $this->translator->trans('ecommerce.admin.order.reference'),
                'renderer' => static fn (string $reference) => '<code>' . htmlspecialchars($reference) . '</code>',
            ])
            ->addColumn('product', [
                'field' => 'productTitle',
                'label' => $this->translator->trans('ecommerce.admin.order.product'),
                'renderer' => fn (string $title, Order $order) => htmlspecialchars("$title - {$order->tierLabel}")
                    . ($order->isRenewal() ? ' <span class="tag">' . htmlspecialchars($this->translator->trans('ecommerce.orders.renewal')) . '</span>' : ''),
            ])
            ->addColumn('customer', [
                'field' => 'user?.username',
                'label' => $this->translator->trans('ecommerce.admin.order.customer'),
                'renderer' => static fn (?string $username) => htmlspecialchars($username ?? '-'),
            ])
            ->addColumn('price', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.order.price'),
                'renderer' => fn (mixed $_, Order $order) => htmlspecialchars($this->priceFormatter->format($order)),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addColumn('status', [
                'field' => 'status',
                'label' => $this->translator->trans('ecommerce.admin.order.status'),
                'renderer' => fn (OrderStatus $status) => $this->translator->trans('ecommerce.order_status.' . $status->value),
                'searchable' => false,
            ])
            ->addColumn('createdAt', [
                'field' => 'createdAt',
                'label' => $this->translator->trans('ecommerce.admin.order.date'),
                'renderer' => fn (DateTime $date) => DateTimeImmutable::createFromInterface($date)->setTimezone($this->timezoneResolver->getTimezone())->format('Y-m-d H:i'),
                'searchable' => false,
            ])
            ->addActionColumn(fn (int $id) => $this->renderAction('ecommerce_admin_orders_view', ['id' => $id], 'eye'))
        ;
    }

    protected function getQuery(array $search): QueryBuilder
    {
        $qb = parent::getQuery($search);

        $status = OrderStatus::tryFrom($this->statusFilter);
        if ($status !== null) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        if ($this->subscription !== null) {
            $qb
                ->andWhere(sprintf('IDENTITY(e.subscription) = :subscription OR e.id IN (SELECT IDENTITY(s.initialOrder) FROM %s s WHERE s.id = :subscription)', Subscription::class))
                ->setParameter('subscription', $this->subscription);
        }

        if ($this->productFilter !== '') {
            $qb->andWhere('e.product = :product')->setParameter('product', (int)$this->productFilter);
        }

        return $qb;
    }
}

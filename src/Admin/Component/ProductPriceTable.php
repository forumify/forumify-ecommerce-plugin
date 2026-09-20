<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Ecommerce\Money\PriceFormatter;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Service\PriceChangeService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('Ecommerce\\ProductPriceTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('ecommerce.admin.products.manage')]
class ProductPriceTable extends AbstractDoctrineTable
{
    #[LiveProp]
    public int $tierId;

    protected ?string $permissionReorder = 'ecommerce.admin.products.manage';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PriceFormatter $priceFormatter,
        private readonly PriceChangeService $priceChangeService,
    ) {
    }

    protected function getEntityClass(): string
    {
        return ProductPrice::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('price', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.price.single'),
                'renderer' => fn(mixed $_, ProductPrice $price) =>
                    htmlspecialchars($this->priceFormatter->format($price)),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addColumn('status', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.price.status'),
                'renderer' => $this->renderStatus(...),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addActionColumn($this->renderActions(...))
        ;
    }

    protected function getQuery(array $search): QueryBuilder
    {
        return parent::getQuery($search)
            ->andWhere('e.tier = :tier')
            ->setParameter('tier', $this->tierId)
        ;
    }

    protected function reorderItem(SortableEntityInterface $entity, string $direction): void
    {
        $this->repository->reorder($entity, $direction, fn(QueryBuilder $qb) => $qb
            ->andWhere('e.tier = :tier')
            ->setParameter('tier', $this->tierId));
    }

    private function renderStatus(mixed $_, ProductPrice $price): string
    {
        $statuses = [$price->active ? 'active' : 'inactive'];
        if ($price->locked) {
            $statuses[] = 'locked';
        }
        if ($price->replacedBy !== null) {
            $statuses[] = 'replaced';
        }

        return implode(', ', array_map(
            fn(string $status) => $this->translator->trans('ecommerce.admin.price.status_' . $status),
            $statuses,
        ));
    }

    private function renderActions(int $id, ProductPrice $price): string
    {
        $actions = $this->renderAction('ecommerce_admin_products_price_edit', ['id' => $id], 'pencil-simple-line');
        if ($this->priceChangeService->canChange($price)) {
            $actions .= $this->renderAction('ecommerce_admin_products_price_change', ['id' => $id], 'swap');
        }
        if (!$price->locked && $price->tier->prices->count() > 1) {
            $actions .= $this->renderAction('ecommerce_admin_products_price_delete', ['id' => $id], 'x');
        }

        return $actions;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Ecommerce\Money\PriceFormatter;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('Ecommerce\\ProductTierTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('ecommerce.admin.products.manage')]
class ProductTierTable extends AbstractDoctrineTable
{
    #[LiveProp]
    public int $productId;

    protected ?string $permissionReorder = 'ecommerce.admin.products.manage';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PriceFormatter $priceFormatter,
    ) {
    }

    protected function getEntityClass(): string
    {
        return ProductTier::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('label', [
                'field' => 'label',
                'label' => $this->translator->trans('ecommerce.admin.tier.label'),
                'renderer' => static fn(string $label) => htmlspecialchars($label),
                'searchable' => false,
            ])
            ->addColumn('key', [
                'field' => 'key',
                'label' => $this->translator->trans('ecommerce.admin.tier.key'),
                'renderer' => static fn(string $key) => '<code>' . htmlspecialchars($key) . '</code>',
                'searchable' => false,
            ])
            ->addColumn('prices', [
                'field' => null,
                'label' => $this->translator->trans('ecommerce.admin.price.plural'),
                'renderer' => $this->renderPrices(...),
                'searchable' => false,
                'sortable' => false,
            ])
            ->addActionColumn($this->renderActions(...))
        ;
    }

    protected function getQuery(array $search): QueryBuilder
    {
        return parent::getQuery($search)
            ->andWhere('e.product = :product')
            ->setParameter('product', $this->productId)
        ;
    }

    protected function reorderItem(SortableEntityInterface $entity, string $direction): void
    {
        $this->repository->reorder($entity, $direction, fn(QueryBuilder $qb) => $qb
            ->andWhere('e.product = :product')
            ->setParameter('product', $this->productId));
    }

    private function renderPrices(mixed $_, ProductTier $tier): string
    {
        $prices = $tier->prices
            ->filter(static fn(ProductPrice $price) => $price->active)
            ->map($this->priceFormatter->format(...))
            ->toArray();

        return htmlspecialchars(implode(', ', $prices));
    }

    private function renderActions(int $id, ProductTier $tier): string
    {
        $actions = $this->renderAction('ecommerce_admin_products_tier_edit', ['id' => $id], 'pencil-simple-line');
        if (!$tier->hasLockedPrices()) {
            $actions .= $this->renderAction('ecommerce_admin_products_tier_delete', ['id' => $id], 'x');
        }

        return $actions;
    }
}

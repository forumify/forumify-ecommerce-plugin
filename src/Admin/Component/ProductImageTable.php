<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\SortableEntityInterface;
use Forumify\Ecommerce\Entity\ProductImage;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent('Ecommerce\\ProductImageTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('ecommerce.admin.products.manage')]
class ProductImageTable extends AbstractDoctrineTable
{
    #[LiveProp]
    public int $productId;

    protected ?string $permissionReorder = 'ecommerce.admin.products.manage';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Packages $packages,
    ) {
    }

    protected function getEntityClass(): string
    {
        return ProductImage::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('image', [
                'field' => 'image',
                'label' => $this->translator->trans('ecommerce.admin.gallery.image'),
                'renderer' => $this->renderImage(...),
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

    private function renderImage(string $image): string
    {
        $url = htmlspecialchars($this->packages->getUrl($image, 'ecommerce.asset'));

        return "<img src=\"$url\" alt=\"\" style=\"height: 64px; width: auto; border-radius: var(--border-radius);\">";
    }

    private function renderActions(int $id): string
    {
        return $this->renderAction('ecommerce_admin_products_image_delete', ['id' => $id], 'x');
    }
}

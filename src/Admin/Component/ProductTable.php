<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductStatus;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('Ecommerce\\ProductTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('ecommerce.admin.products.view')]
class ProductTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'ecommerce.admin.products.manage';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function getEntityClass(): string
    {
        return Product::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('title', [
                'field' => 'title',
                'label' => $this->translator->trans('ecommerce.admin.product.title'),
                'renderer' => static fn(string $title) => htmlspecialchars($title),
            ])
            ->addColumn('key', [
                'field' => 'key',
                'label' => $this->translator->trans('ecommerce.admin.product.key'),
                'renderer' => static fn(string $key) => '<code>' . htmlspecialchars($key) . '</code>',
            ])
            ->addColumn('status', [
                'field' => 'status',
                'label' => $this->translator->trans('ecommerce.admin.product.status'),
                'renderer' => fn(ProductStatus $status) =>
                    $this->translator->trans('ecommerce.product_status.' . $status->value),
                'searchable' => false,
            ])
            ->addActionColumn($this->renderActions(...))
        ;
    }

    private function renderActions(int $id): string
    {
        if (!$this->isGranted('ecommerce.admin.products.manage')) {
            return '';
        }

        return $this->renderAction('ecommerce_admin_products_edit', ['identifier' => $id], 'pencil-simple-line')
            . $this->renderAction('ecommerce_admin_products_delete', ['identifier' => $id], 'x');
    }
}

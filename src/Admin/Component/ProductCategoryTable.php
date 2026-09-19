<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Component;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Ecommerce\Entity\ProductCategory;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('Ecommerce\\ProductCategoryTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('ecommerce.admin.categories.view')]
class ProductCategoryTable extends AbstractDoctrineTable
{
    protected ?string $permissionReorder = 'ecommerce.admin.categories.manage';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function getEntityClass(): string
    {
        return ProductCategory::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addPositionColumn()
            ->addColumn('title', [
                'field' => 'title',
                'label' => $this->translator->trans('ecommerce.admin.category.title'),
                'renderer' => static fn (string $title) => htmlspecialchars($title),
            ])
            ->addColumn('slug', [
                'field' => 'slug',
                'label' => $this->translator->trans('ecommerce.admin.category.slug'),
            ])
            ->addActionColumn($this->renderActions(...))
        ;
    }

    private function renderActions(int $id): string
    {
        if (!$this->isGranted('ecommerce.admin.categories.manage')) {
            return '';
        }

        return $this->renderAction('ecommerce_admin_categories_edit', ['identifier' => $id], 'pencil-simple-line')
            . $this->renderAction('ecommerce_admin_categories_delete', ['identifier' => $id], 'x');
    }
}

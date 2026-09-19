<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Ecommerce\Admin\Form\ProductCategoryType;
use Forumify\Ecommerce\Entity\ProductCategory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ProductCategory>
 */
#[Route('/categories', 'categories')]
#[IsGranted('ecommerce.admin.categories.view')]
class ProductCategoryController extends AbstractCrudController
{
    protected ?string $permissionView = 'ecommerce.admin.categories.view';
    protected ?string $permissionCreate = 'ecommerce.admin.categories.manage';
    protected ?string $permissionEdit = 'ecommerce.admin.categories.manage';
    protected ?string $permissionDelete = 'ecommerce.admin.categories.manage';

    protected function getTranslationPrefix(): string
    {
        return 'ecommerce.' . parent::getTranslationPrefix();
    }

    protected function getEntityClass(): string
    {
        return ProductCategory::class;
    }

    protected function getTableName(): string
    {
        return 'Ecommerce\\ProductCategoryTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(ProductCategoryType::class, $data);
    }
}

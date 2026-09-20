<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

class CatalogSearchWidget extends AbstractEcommerceWidget
{
    public function getName(): string
    {
        return 'ecommerce.catalog_search';
    }

    public function getPreview(): string
    {
        return '<div class="flex items-center gap-2">
            <input type="search" placeholder="Search products" disabled>
            <button type="button" class="btn-primary">Search</button>
        </div>';
    }

    public function getTemplate(): string
    {
        return '@ForumifyEcommercePlugin/frontend/cms/widgets/catalog_search.html.twig';
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        return $this->createForm($data)
            ->add('title', TextType::class, [
                'required' => false,
                'help' => 'ecommerce.cms.widget.title_help',
            ])
            ->add('placeholder', TextType::class, [
                'required' => false,
                'help' => 'ecommerce.cms.widget.placeholder_help',
            ])
            ->add('hideBrowseAll', CheckboxType::class, ['required' => false])
            ->getForm()
        ;
    }
}

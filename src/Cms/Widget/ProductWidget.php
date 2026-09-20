<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;

class ProductWidget extends AbstractProductWidget
{
    public function getName(): string
    {
        return 'ecommerce.product';
    }

    public function getPreview(): string
    {
        return '<div class="card">
            <div class="card-body flex flex-col gap-1">
                <strong>Product</strong>
                <span>A short description of the product.</span>
                <strong>€9.99</strong>
            </div>
        </div>';
    }

    public function getTemplate(): string
    {
        return '@ForumifyEcommercePlugin/frontend/cms/widgets/product.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        return ['product' => $this->findPublishedProduct($settings)];
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        return $this->createForm($data)
            ->add('product', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $this->getProductChoices(),
                'placeholder' => 'ecommerce.cms.widget.product_placeholder',
                'help' => 'ecommerce.cms.widget.product_help',
            ])
            ->add('hideImage', CheckboxType::class, ['required' => false])
            ->add('hideDescription', CheckboxType::class, ['required' => false])
            ->add('hidePrice', CheckboxType::class, ['required' => false])
            ->getForm()
        ;
    }
}

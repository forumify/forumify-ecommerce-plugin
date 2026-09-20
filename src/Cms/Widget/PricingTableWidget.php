<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

class PricingTableWidget extends AbstractProductWidget
{
    public function getName(): string
    {
        return 'ecommerce.pricing_table';
    }

    public function getPreview(): string
    {
        return '<div class="flex gap-2">
            <div class="card flex-grow">
                <div class="card-body flex flex-col gap-1">
                    <strong>Free</strong>
                    <span>€0</span>
                </div>
            </div>
            <div class="card flex-grow">
                <div class="card-body flex flex-col gap-1">
                    <strong>Premium</strong>
                    <span>€9.99</span>
                </div>
            </div>
        </div>';
    }

    public function getTemplate(): string
    {
        return '@ForumifyEcommercePlugin/frontend/cms/widgets/pricing_table.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        $product = $this->findPublishedProduct($settings);

        return [
            'product' => $product,
            'tiers' => $product?->getPurchasableTiers() ?? [],
        ];
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
            ->add('title', TextType::class, [
                'required' => false,
                'help' => 'ecommerce.cms.widget.pricing_table_title_help',
            ])
            ->getForm()
        ;
    }
}

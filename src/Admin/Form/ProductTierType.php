<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Core\Form\RichTextEditorType;
use Forumify\Ecommerce\Entity\ProductTier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProductTier>
 */
class ProductTierType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProductTier::class]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ProductTier|null $tier */
        $tier = $options['data'] ?? null;

        $builder
            ->add('label', TextType::class, [
                'label' => 'ecommerce.admin.tier.label',
            ])
            ->add('key', TextType::class, [
                'disabled' => $tier?->hasLockedPrices() ?? false,
                'help' => 'ecommerce.admin.tier.key_help',
                'label' => 'ecommerce.admin.tier.key',
            ])
            ->add('description', RichTextEditorType::class, [
                'empty_data' => '',
                'help' => 'ecommerce.admin.tier.description_help',
                'label' => 'ecommerce.admin.tier.description',
                'required' => false,
            ])
        ;
    }
}

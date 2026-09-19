<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Core\Form\EntityType;
use Forumify\Core\Form\RichTextEditorType;
use Forumify\Core\Form\UploadType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductState;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Product>
 */
class ProductType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Product|null $product */
        $product = $options['data'] ?? null;

        $builder
            ->add('title', TextType::class, [
                'label' => 'ecommerce.admin.product.title',
            ])
            ->add('key', TextType::class, [
                'disabled' => $product?->hasLockedPrices() ?? false,
                'help' => 'ecommerce.admin.product.key_help',
                'label' => 'ecommerce.admin.product.key',
            ])
            ->add('state', EnumType::class, [
                'class' => ProductState::class,
                'choice_label' => static fn (ProductState $state) => 'ecommerce.product_state.' . $state->value,
                'help' => 'ecommerce.admin.product.state_help',
                'label' => 'ecommerce.admin.product.state',
            ])
            ->add('categories', EntityType::class, [
                'autocomplete' => true,
                'choice_label' => 'title',
                'class' => ProductCategory::class,
                'label' => 'ecommerce.admin.product.categories',
                'multiple' => true,
                'required' => false,
            ])
            ->add('shortDescription', TextType::class, [
                'empty_data' => '',
                'help' => 'ecommerce.admin.product.short_description_help',
                'label' => 'ecommerce.admin.product.short_description',
                'required' => false,
            ])
            ->add('description', RichTextEditorType::class, [
                'empty_data' => '',
                'label' => 'ecommerce.admin.product.description',
                'required' => false,
            ])
            ->add('image', UploadType::class, [
                'accept' => 'image/*',
                'asset_package' => 'ecommerce.asset',
                'file_constraints' => [new Assert\Image(maxSize: '2M')],
                'filesystem' => 'ecommerce_asset.storage',
                'label' => 'ecommerce.admin.product.image',
                'required' => false,
            ])
        ;
    }
}

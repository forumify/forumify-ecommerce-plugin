<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Forumify\Core\Entity\Role;
use Forumify\Core\Form\EntityType;
use Forumify\Core\Form\RichTextEditorType;
use Forumify\Core\Form\UploadType;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Forum\Entity\Badge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Product>
 */
class ProductType extends AbstractType
{
    public function __construct(
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
        private readonly TranslatorInterface $translator,
    ) {
    }

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
            ->add('status', EnumType::class, [
                'class' => ProductStatus::class,
                'choice_label' => static fn(ProductStatus $status) => 'ecommerce.product_status.' . $status->value,
                'help' => 'ecommerce.admin.product.status_help',
                'label' => 'ecommerce.admin.product.status',
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
                'help' => 'ecommerce.admin.product.image_help',
                'label' => 'ecommerce.admin.product.image',
                'required' => false,
            ])
        ;

        $this->addFulfillmentFields($builder, $product);
        $this->addGrantFields($builder);
    }

    /**
     * @param FormBuilderInterface<Product|null> $builder
     */
    private function addFulfillmentFields(FormBuilderInterface $builder, ?Product $product): void
    {
        $choices = [];
        $manualTypes = [];
        foreach ($this->fulfillmentTypes->all() as $key => $type) {
            $choices[$type->getLabel()] = $key;
            if ($type->requiresManualFulfillment()) {
                $manualTypes[] = $key;
            }
        }

        $currentType = $product->fulfillmentType ?? null;
        if ($currentType !== null && !in_array($currentType, $choices, true)) {
            $choices[$currentType] = $currentType;
            $manualTypes[] = $currentType;
        }

        $builder
            ->add('fulfillmentType', ChoiceType::class, [
                'attr' => [
                    'data-fulfillment-type-select' => '',
                    'data-fulfillment-manual-types' => json_encode($manualTypes),
                ],
                'choices' => $choices,
                'help' => 'ecommerce.admin.product.fulfillment_type_help',
                'label' => 'ecommerce.admin.product.fulfillment_type',
            ])
            ->add('fulfillmentSettings', FormType::class, [
                'label' => false,
                'mapped' => false,
            ])
            ->add('fulfillmentRole', EntityType::class, [
                'choice_label' => 'title',
                'class' => Role::class,
                'help' => 'ecommerce.admin.product.fulfillment_role_help',
                'label' => 'ecommerce.admin.product.fulfillment_role',
                'placeholder' => 'ecommerce.admin.product.fulfillment_role_placeholder',
                'required' => true,
                'row_attr' => ['data-fulfillment-role' => ''],
            ])
        ;

        $settings = $builder->get('fulfillmentSettings');
        foreach ($this->fulfillmentTypes->all() as $key => $type) {
            $formType = $type->getProductSettingsFormType();
            if ($formType === null) {
                continue;
            }

            $settings->add($key, $formType, [
                'data' => $currentType === $key ? $product?->fulfillmentSettings : null,
                'label' => false,
                'row_attr' => ['data-fulfillment-settings-for' => $key],
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $selected = is_array($data) ? ($data['fulfillmentType'] ?? null) : null;
            $settings = $event->getForm()->get('fulfillmentSettings');
            foreach (array_keys($settings->all()) as $key) {
                if ($key !== $selected) {
                    $settings->remove((string)$key);
                }
            }
        });
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->applyFulfillmentSettings(...));
    }

    private function applyFulfillmentSettings(FormEvent $event): void
    {
        $form = $event->getForm();
        $product = $form->getData();
        if (!$product instanceof Product) {
            return;
        }

        $settings = $form->get('fulfillmentSettings');
        $product->fulfillmentSettings = $settings->has($product->fulfillmentType)
            ? array_filter(
                (array)$settings->get($product->fulfillmentType)->getData(),
                static fn(mixed $value) => $value !== null,
            )
            : [];

        if (!$this->fulfillmentTypes->requiresManualFulfillment($product->fulfillmentType)) {
            $product->fulfillmentRole = null;
            return;
        }

        if ($product->fulfillmentRole !== null) {
            return;
        }

        $message = 'ecommerce.admin.product.fulfillment_role_required';
        $form->get('fulfillmentRole')->addError(new FormError($this->translator->trans($message), $message));
    }

    /**
     * @param FormBuilderInterface<Product|null> $builder
     */
    private function addGrantFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('grantedRoles', EntityType::class, [
                'autocomplete' => true,
                'choice_label' => 'title',
                'class' => Role::class,
                'help' => 'ecommerce.admin.product.granted_roles_help',
                'label' => 'ecommerce.admin.product.granted_roles',
                'multiple' => true,
                'query_builder' => static fn(EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('r')
                    ->where('r.system = false'),
                'required' => false,
            ])
            ->add('grantedBadges', EntityType::class, [
                'autocomplete' => true,
                'choice_label' => 'name',
                'class' => Badge::class,
                'help' => 'ecommerce.admin.product.granted_badges_help',
                'label' => 'ecommerce.admin.product.granted_badges',
                'multiple' => true,
                'required' => false,
            ])
        ;
    }
}

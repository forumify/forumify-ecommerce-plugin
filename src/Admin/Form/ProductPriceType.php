<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Ecommerce\Entity\BillingInterval;
use Forumify\Ecommerce\Entity\PriceType;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Form\MoneyAmountType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProductPrice>
 */
class ProductPriceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductPrice::class,
            'show_active' => true,
        ]);
        $resolver->setAllowedTypes('show_active', 'bool');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ProductPrice $price */
        $price = $options['data'];
        $locked = $price->locked;

        $builder
            ->add('amountMinor', MoneyAmountType::class, [
                'money_currency' => $price->currency,
                'disabled' => $locked,
                'help' => 'ecommerce.admin.price.amount_help',
                'label' => 'ecommerce.admin.price.amount',
            ])
            ->add('type', EnumType::class, [
                'attr' => ['data-price-type' => ''],
                'class' => PriceType::class,
                'choice_label' => static fn (PriceType $type) => 'ecommerce.price_type.' . $type->value,
                'disabled' => $locked,
                'label' => 'ecommerce.admin.price.type',
            ])
            ->add('intervalCount', IntegerType::class, [
                'attr' => ['min' => 1],
                'disabled' => $locked,
                'label' => 'ecommerce.admin.price.interval_count',
            ])
            ->add('billingInterval', EnumType::class, [
                'class' => BillingInterval::class,
                'choice_label' => static fn (BillingInterval $interval) => 'ecommerce.billing_interval.' . $interval->value,
                'disabled' => $locked,
                'label' => 'ecommerce.admin.price.billing_interval',
                'placeholder' => 'ecommerce.admin.price.choose_interval',
            ])
        ;

        if ($options['show_active']) {
            $builder->add('active', CheckboxType::class, [
                'help' => 'ecommerce.admin.price.active_help',
                'label' => 'ecommerce.admin.price.active',
                'required' => false,
            ]);
        }
    }
}

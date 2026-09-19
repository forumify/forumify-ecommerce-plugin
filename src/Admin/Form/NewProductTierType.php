<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductTier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{tier: ProductTier, price: ProductPrice}>
 */
class NewProductTierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array{tier: ProductTier, price: ProductPrice} $data */
        $data = $options['data'];

        $builder
            ->add('tier', ProductTierType::class, [
                'constraints' => [new Assert\Valid()],
                'label' => false,
            ])
            ->add('price', ProductPriceType::class, [
                'constraints' => [new Assert\Valid()],
                'data' => $data['price'],
                'label' => false,
                'show_active' => false,
            ])
        ;
    }
}

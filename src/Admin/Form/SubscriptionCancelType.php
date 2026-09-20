<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array{atPeriodEnd: bool, refundLastOrder?: bool}>
 */
class SubscriptionCancelType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['allow_period_end' => true, 'allow_refund' => false]);
        $resolver->setAllowedTypes('allow_period_end', 'bool');
        $resolver->setAllowedTypes('allow_refund', 'bool');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = ['ecommerce.admin.subscription.cancel_now' => false];
        if ($options['allow_period_end']) {
            $choices = ['ecommerce.admin.subscription.cancel_at_period_end' => true] + $choices;
        }

        $builder->add('atPeriodEnd', ChoiceType::class, [
            'choices' => $choices,
            'expanded' => true,
            'help' => 'ecommerce.admin.subscription.cancel_help',
            'label' => 'ecommerce.admin.subscription.cancel_when',
        ]);

        if ($options['allow_refund']) {
            $builder->add('refundLastOrder', CheckboxType::class, [
                'help' => 'ecommerce.admin.subscription.refund_last_help',
                'label' => 'ecommerce.admin.subscription.refund_last',
                'required' => false,
            ]);
        }
    }
}

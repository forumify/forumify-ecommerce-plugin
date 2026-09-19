<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array{atPeriodEnd: bool}>
 */
class SubscriptionCancelType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['allow_period_end' => true]);
        $resolver->setAllowedTypes('allow_period_end', 'bool');
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
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Ecommerce\Entity\PaymentProviderConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PaymentProviderConfig>
 */
class PaymentProviderConfigType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PaymentProviderConfig::class]);
        $resolver->setRequired('settings_type');
        $resolver->setAllowedTypes('settings_type', 'string');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'help' => 'ecommerce.admin.payment_provider.enabled_help',
                'label' => 'ecommerce.admin.payment_provider.enabled',
                'required' => false,
            ])
            ->add('settings', $options['settings_type'], [
                'label' => false,
            ])
        ;
    }
}

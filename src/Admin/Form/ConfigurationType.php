<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Ecommerce\Service\StoreSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('ecommerce__currency', CurrencyType::class, [
            'autocomplete' => true,
            'choice_filter' => static fn(?string $currency) =>
                in_array($currency, StoreSettings::SUPPORTED_CURRENCIES, true),
            'constraints' => [new Assert\NotBlank()],
            'help' => 'ecommerce.admin.configuration.currency_help',
            'label' => 'ecommerce.admin.configuration.currency',
        ]);
    }
}

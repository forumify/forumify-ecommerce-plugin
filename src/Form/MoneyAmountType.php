<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Form;

use Brick\Money\Currency;
use Forumify\Ecommerce\Form\DataTransformer\MinorUnitsTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<int>
 */
class MoneyAmountType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('money_currency');
        $resolver->setAllowedTypes('money_currency', 'string');
        $resolver->setDefaults([
            'currency' => false,
            'input' => 'string',
            'label_translation_parameters' => static fn(Options $options) =>
                ['currency' => $options['money_currency']],
            'scale' => static fn(Options $options) =>
                Currency::of($options['money_currency'])->getDefaultFractionDigits(),
            'invalid_message' => 'ecommerce.price.amount_invalid',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new MinorUnitsTransformer($options['scale']));
    }

    public function getParent(): string
    {
        return MoneyType::class;
    }
}

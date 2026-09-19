<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ManualSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('inputLabel', TextType::class, [
            'constraints' => [new Assert\Length(max: 255)],
            'help' => 'ecommerce.fulfillment.manual.input_label_help',
            'label' => 'ecommerce.fulfillment.manual.input_label',
            'required' => false,
        ]);
    }
}

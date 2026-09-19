<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{note: ?string, confirm?: bool}>
 */
class OrderCompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['manual' => true]);
        $resolver->setAllowedTypes('manual', 'bool');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('note', TextareaType::class, [
            'constraints' => [new Assert\Length(max: 5000)],
            'help' => 'ecommerce.admin.order.complete_note_help',
            'label' => 'ecommerce.admin.order.complete_note',
            'required' => false,
        ]);

        if (!$options['manual']) {
            $builder->add('confirm', CheckboxType::class, [
                'constraints' => [new Assert\IsTrue(message: 'ecommerce.admin.order.complete_confirm_required')],
                'label' => 'ecommerce.admin.order.complete_confirm',
            ]);
        }
    }
}

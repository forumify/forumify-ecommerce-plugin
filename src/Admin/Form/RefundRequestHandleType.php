<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{note: ?string}>
 */
class RefundRequestHandleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', TextareaType::class, [
                'constraints' => [new Assert\Length(max: 5000)],
                'help' => 'ecommerce.admin.refund.note_help',
                'label' => 'ecommerce.admin.refund.note',
                'required' => false,
            ])
            ->add('approve', SubmitType::class, [
                'attr' => ['class' => 'btn-primary'],
                'label' => 'ecommerce.admin.refund.approve',
            ])
            ->add('reject', SubmitType::class, [
                'attr' => ['class' => 'btn-outlined'],
                'label' => 'ecommerce.admin.refund.reject',
            ])
        ;
    }
}

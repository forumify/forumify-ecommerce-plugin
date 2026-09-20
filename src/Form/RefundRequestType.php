<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{reason: string}>
 */
class RefundRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'constraints' => [
                new Assert\NotBlank(message: 'ecommerce.refund.reason_required'),
                new Assert\Length(max: 5000),
            ],
            'help' => 'ecommerce.refund.reason_help',
            'label' => 'ecommerce.refund.reason',
        ]);
    }
}

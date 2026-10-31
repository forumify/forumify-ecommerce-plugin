<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Form;

use Forumify\Ecommerce\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ManualCheckoutType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['product', 'user']);
        $resolver->setDefined(['price']);
        $resolver->setAllowedTypes('product', Product::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Product $product */
        $product = $options['product'];
        $label = $product->fulfillmentSettings['inputLabel'] ?? null;
        if (!is_string($label) || trim($label) === '') {
            return;
        }

        $builder
            ->add('value', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'label' => $label,
                'translation_domain' => false,
            ])
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($label): void {
                $event->setData([...($event->getData() ?? []), 'label' => $label]);
            })
        ;
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Stripe;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class StripeSettingsType extends AbstractType
{
    private const FIELDS = [
        StripeSettings::SECRET_KEY => '/^(sk|rk)_(test|live)_\w+$/',
        StripeSettings::WEBHOOK_SECRET => '/^whsec_\w+$/',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $settings = $event->getData();
            foreach (self::FIELDS as $field => $pattern) {
                $current = is_array($settings) && is_string($settings[$field] ?? null) ? $settings[$field] : '';
                self::addSecretField($event->getForm(), $field, $pattern, $current);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $submitted = $event->getData();
            $current = $event->getForm()->getData();
            if (!is_array($submitted)) {
                return;
            }

            foreach (array_keys(self::FIELDS) as $field) {
                $value = trim(is_string($submitted[$field] ?? null) ? $submitted[$field] : '');
                $submitted[$field] = $value === '' && is_array($current) ? ($current[$field] ?? '') : $value;
            }
            $event->setData($submitted);
        });
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private static function addSecretField(FormInterface $form, string $field, string $pattern, string $current): void
    {
        $form->add($field, PasswordType::class, [
            'label' => "ecommerce.stripe.{$field}",
            'help' => "ecommerce.stripe.{$field}_help",
            'help_html' => true,
            'required' => $current === '',
            'attr' => [
                'autocomplete' => 'off',
                'placeholder' => $current === '' ? '' : substr($current, 0, 8) . '…' . substr($current, -4),
            ],
            'constraints' => [
                new Assert\NotBlank(message: 'ecommerce.stripe.required'),
                new Assert\Regex($pattern, message: "ecommerce.stripe.{$field}_invalid"),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Fulfillment\Form;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Fulfillment\Type\PhysicalFulfillmentType;
use Forumify\Ecommerce\Repository\OrderRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ShippingAddressType extends AbstractType
{
    public const FIELDS = ['name', 'line1', 'line2', 'postalCode', 'city', 'region', 'country'];
    private const OPTIONAL = ['line2', 'region'];

    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['product' => null, 'price' => null, 'user' => null]);
        $resolver->setAllowedTypes('user', ['null', User::class]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::FIELDS as $field) {
            $required = !in_array($field, self::OPTIONAL, true);
            $builder->add($field, $field === 'country' ? CountryType::class : TextType::class, [
                'constraints' => [
                    ...($required ? [new Assert\NotBlank()] : []),
                    new Assert\Length(max: 255),
                ],
                'label' => 'ecommerce.fulfillment.physical.' . $field,
                'required' => $required,
                ...($field === 'country' ? ['placeholder' => 'ecommerce.fulfillment.physical.choose_country'] : []),
            ]);
        }

        $user = $options['user'];
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user): void {
            if ($event->getData() !== null || !$user instanceof User) {
                return;
            }

            $event->setData($this->orderRepository->findLatestFulfillmentData($user, PhysicalFulfillmentType::KEY));
        });
    }
}

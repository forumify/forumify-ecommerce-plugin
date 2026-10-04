<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Form;

use Forumify\Core\Repository\RoleRepository;
use Forumify\Ecommerce\Service\StoreSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ConfigurationType extends AbstractType
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
    }

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

        $builder->add('ecommerce__store_role', ChoiceType::class, [
            'autocomplete' => true,
            'choices' => $this->getRoleChoices(),
            'help' => 'ecommerce.admin.configuration.store_role_help',
            'label' => 'ecommerce.admin.configuration.store_role',
            'placeholder' => 'ecommerce.admin.configuration.store_role_placeholder',
            'required' => false,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function getRoleChoices(): array
    {
        /** @var list<array{id: int, title: string}> $roles */
        $roles = $this->roleRepository
            ->createQueryBuilder('r')
            ->select('r.id', 'r.title')
            ->orderBy('r.position', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_combine(
            array_column($roles, 'title'),
            array_column($roles, 'id'),
        );
    }
}

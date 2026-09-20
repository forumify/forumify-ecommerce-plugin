<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

class BuyButtonWidget extends AbstractEcommerceWidget
{
    private const SEPARATOR = '::';

    public function __construct(private readonly ProductTierRepository $tierRepository)
    {
    }

    public function getName(): string
    {
        return 'ecommerce.buy_button';
    }

    public function getPreview(): string
    {
        return '<div>
            <a class="btn-cta" href="#">Get Premium</a>
        </div>';
    }

    public function getTemplate(): string
    {
        return '@ForumifyEcommercePlugin/frontend/cms/widgets/buy_button.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        $tier = $this->findTier($settings);

        return [
            'tier' => $tier,
            'price' => $tier?->getCheapestPrice(),
        ];
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        return $this->createForm($data)
            ->add('tier', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $this->getTierChoices(),
                'placeholder' => 'ecommerce.cms.widget.tier_placeholder',
                'help' => 'ecommerce.cms.widget.tier_help',
            ])
            ->add('label', TextType::class, [
                'required' => false,
                'help' => 'ecommerce.cms.widget.buy_button_label_help',
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Call To Action' => 'btn-cta',
                    'Primary' => 'btn-primary',
                    'Outlined' => 'btn-outlined',
                    'Link' => 'btn-link',
                ],
            ])
            ->add('showPrice', CheckboxType::class, ['required' => false])
            ->getForm()
        ;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function findTier(array $settings): ?ProductTier
    {
        $value = $settings['tier'] ?? null;
        if (!is_string($value) || !str_contains($value, self::SEPARATOR)) {
            return null;
        }

        [$productKey, $tierKey] = explode(self::SEPARATOR, $value, 2);

        return $this->tierRepository->findPublishedByKeys($productKey, $tierKey);
    }

    /**
     * @return array<string, string>
     */
    private function getTierChoices(): array
    {
        $choices = [];
        foreach ($this->tierRepository->findAllForSelection() as $tier) {
            $label = "{$tier->product->title} - {$tier->label}";
            $choices[$label] = $tier->product->key . self::SEPARATOR . $tier->key;
        }

        return $choices;
    }
}

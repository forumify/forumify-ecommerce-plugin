<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Repository\ProductCategoryRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;

class ProductCategoryWidget extends AbstractEcommerceWidget
{
    private const DEFAULT_LIMIT = 4;
    private const MAX_LIMIT = 48;

    public function __construct(
        private readonly ProductCategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function getName(): string
    {
        return 'ecommerce.product_category';
    }

    public function getPreview(): string
    {
        return '<div>
            <h3>Category</h3>
            <div class="card">
                <p class="card-body">Product 1</p>
                <hr/>
                <p class="card-body">Product 2</p>
            </div>
            <button type="button" class="btn-link mt-2">
                <i class="ph ph-arrow-right"></i>Show more
            </button>
        </div>';
    }

    public function getTemplate(): string
    {
        return '@ForumifyEcommercePlugin/frontend/cms/widgets/product_category.html.twig';
    }

    public function getTemplateContext(array $settings): array
    {
        $empty = ['category' => null, 'products' => [], 'hasMore' => false];

        $slug = $settings['category'] ?? null;
        if (!is_string($slug) || $slug === '') {
            return $empty;
        }

        /** @var ProductCategory|null $category */
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if ($category === null) {
            return $empty;
        }

        $limit = $this->getLimit($settings);
        $products = $this->productRepository->findPublishedInCategory($category, $limit + 1);

        return [
            'category' => $category,
            'products' => array_slice($products, 0, $limit),
            'hasMore' => count($products) > $limit,
        ];
    }

    public function getSettingsForm(array $data = []): ?FormInterface
    {
        $data['limit'] = $this->getLimit($data);

        return $this->createForm($data)
            ->add('category', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $this->getCategoryChoices(),
                'placeholder' => 'ecommerce.cms.widget.category_placeholder',
            ])
            ->add('limit', NumberType::class, [
                'help' => 'ecommerce.cms.widget.limit_help',
            ])
            ->add('hideTitle', CheckboxType::class, ['required' => false])
            ->getForm()
        ;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function getLimit(array $settings): int
    {
        $limit = (int)($settings['limit'] ?? self::DEFAULT_LIMIT);

        return max(1, min(self::MAX_LIMIT, $limit));
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryChoices(): array
    {
        $choices = [];
        /** @var ProductCategory $category */
        foreach ($this->categoryRepository->findBy([], ['position' => 'ASC']) as $category) {
            $choices[$category->title] = $category->getSlug();
        }

        return $choices;
    }
}

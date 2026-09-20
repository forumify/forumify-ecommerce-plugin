<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Cms\Widget;

use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Repository\ProductRepository;

abstract class AbstractProductWidget extends AbstractEcommerceWidget
{
    public function __construct(protected readonly ProductRepository $productRepository)
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function findPublishedProduct(array $settings): ?Product
    {
        $key = $settings['product'] ?? null;
        if (!is_string($key) || $key === '') {
            return null;
        }

        return $this->productRepository->findOneBy(['key' => $key, 'status' => ProductStatus::Published]);
    }

    /**
     * Drafts are offered too, so a page can be built before the product goes live.
     *
     * @return array<string, string>
     */
    protected function getProductChoices(): array
    {
        $choices = [];
        /** @var Product $product */
        foreach ($this->productRepository->findBy([], ['title' => 'ASC']) as $product) {
            $choices[$product->title] = $product->key;
        }

        return $choices;
    }
}

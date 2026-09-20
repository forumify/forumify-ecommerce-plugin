<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Cms\Widget;

use Forumify\Ecommerce\Cms\Widget\ProductCategoryWidget;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductCategory;
use Forumify\Ecommerce\Repository\ProductCategoryRepository;
use Forumify\Ecommerce\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;

class ProductCategoryWidgetTest extends TestCase
{
    public function testUnknownCategoryRendersNothing(): void
    {
        $categoryRepository = $this->createMock(ProductCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn(null);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('findPublishedInCategory');

        $widget = new ProductCategoryWidget($categoryRepository, $productRepository);

        self::assertSame(
            ['category' => null, 'products' => [], 'hasMore' => false],
            $widget->getTemplateContext(['category' => 'gone']),
        );
        self::assertSame(
            ['category' => null, 'products' => [], 'hasMore' => false],
            $widget->getTemplateContext([]),
        );
    }

    /**
     * One product over the limit is fetched to detect whether the category holds more, and is
     * trimmed off again before it reaches the template.
     */
    public function testExtraProductOnlySignalsThereIsMore(): void
    {
        $category = new ProductCategory();
        $categoryRepository = $this->createMock(ProductCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $products = [new Product(), new Product(), new Product()];
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository
            ->expects(self::once())
            ->method('findPublishedInCategory')
            ->with($category, 3)
            ->willReturn($products);

        $widget = new ProductCategoryWidget($categoryRepository, $productRepository);
        $context = $widget->getTemplateContext(['category' => 'plugins', 'limit' => 2]);

        self::assertSame([$products[0], $products[1]], $context['products']);
        self::assertTrue($context['hasMore']);
    }

    public function testLimitIsClamped(): void
    {
        $category = new ProductCategory();
        $categoryRepository = $this->createMock(ProductCategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn($category);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository
            ->expects(self::exactly(2))
            ->method('findPublishedInCategory')
            ->willReturnCallback(function (ProductCategory $_, int $limit): array {
                self::assertContains($limit, [2, 49]);
                return [];
            });

        $widget = new ProductCategoryWidget($categoryRepository, $productRepository);
        $widget->getTemplateContext(['category' => 'plugins', 'limit' => 0]);
        $widget->getTemplateContext(['category' => 'plugins', 'limit' => 1000]);
    }
}

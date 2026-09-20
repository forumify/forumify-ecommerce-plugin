<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Cms\Widget;

use Forumify\Ecommerce\Cms\Widget\ProductWidget;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;

class ProductWidgetTest extends TestCase
{
    public function testOnlyPublishedProductsAreLookedUp(): void
    {
        $product = new Product();
        $repository = $this->createMock(ProductRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['key' => 'milsim', 'status' => ProductStatus::Published])
            ->willReturn($product);

        $widget = new ProductWidget($repository);

        self::assertSame(['product' => $product], $widget->getTemplateContext(['product' => 'milsim']));
    }

    public function testWithoutASelectionNothingIsLookedUp(): void
    {
        $repository = $this->createMock(ProductRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $widget = new ProductWidget($repository);

        self::assertSame(['product' => null], $widget->getTemplateContext([]));
        self::assertSame(['product' => null], $widget->getTemplateContext(['product' => '']));
    }
}

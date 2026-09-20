<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Cms\Widget;

use Forumify\Ecommerce\Cms\Widget\BuyButtonWidget;
use Forumify\Ecommerce\Entity\Product;
use Forumify\Ecommerce\Entity\ProductTier;
use Forumify\Ecommerce\Repository\ProductTierRepository;
use PHPUnit\Framework\TestCase;

class BuyButtonWidgetTest extends TestCase
{
    /**
     * A product key may contain slashes and dots, so only the first separator splits the setting.
     */
    public function testTheSettingSplitsIntoAProductAndATierKey(): void
    {
        $tier = new ProductTier(new Product());
        $repository = $this->createMock(ProductTierRepository::class);
        $repository
            ->expects(self::once())
            ->method('findPublishedByKeys')
            ->with('forumify/forumify-milhq-plugin', 'premium')
            ->willReturn($tier);

        $widget = new BuyButtonWidget($repository);
        $context = $widget->getTemplateContext(['tier' => 'forumify/forumify-milhq-plugin::premium']);

        self::assertSame($tier, $context['tier']);
        self::assertNull($context['price']);
    }

    public function testAMalformedOrMissingSettingIsNotLookedUp(): void
    {
        $repository = $this->createMock(ProductTierRepository::class);
        $repository->expects(self::never())->method('findPublishedByKeys');

        $widget = new BuyButtonWidget($repository);

        foreach ([[], ['tier' => ''], ['tier' => 'no-separator'], ['tier' => null]] as $settings) {
            self::assertSame(['tier' => null, 'price' => null], $widget->getTemplateContext($settings));
        }
    }
}

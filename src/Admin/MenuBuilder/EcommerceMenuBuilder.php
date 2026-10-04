<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\MenuBuilder;

use Forumify\Admin\MenuBuilder\AdminMenuBuilderInterface;
use Forumify\Core\MenuBuilder\Menu;
use Forumify\Core\MenuBuilder\MenuItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EcommerceMenuBuilder implements AdminMenuBuilderInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(Menu $menu): void
    {
        $u = $this->urlGenerator->generate(...);

        $menu->addItem(new Menu('Ecommerce', ['icon' => 'ph ph-storefront', 'permission' => 'ecommerce.admin.view'], [
            new MenuItem('Dashboard', $u('ecommerce_admin_dashboard'), [
                'icon' => 'ph ph-squares-four',
                'permission' => 'ecommerce.admin.dashboard',
            ]),
            new MenuItem('Products', $u('ecommerce_admin_products_list'), [
                'icon' => 'ph ph-package',
                'permission' => 'ecommerce.admin.products.view',
            ]),
            new MenuItem('Categories', $u('ecommerce_admin_categories_list'), [
                'icon' => 'ph ph-folders',
                'permission' => 'ecommerce.admin.categories.view',
            ]),
            new MenuItem('Orders', $u('ecommerce_admin_orders_list'), [
                'icon' => 'ph ph-receipt',
                'permission' => 'ecommerce.admin.orders.view',
            ]),
            new MenuItem('Subscriptions', $u('ecommerce_admin_subscriptions_list'), [
                'icon' => 'ph ph-arrows-clockwise',
                'permission' => 'ecommerce.admin.subscriptions.view',
            ]),
            new MenuItem('Refunds', $u('ecommerce_admin_refunds_list'), [
                'icon' => 'ph ph-arrow-u-up-left',
                'permission' => 'ecommerce.admin.orders.refund',
            ]),
            new MenuItem('Payment providers', $u('ecommerce_admin_payment_providers_list'), [
                'icon' => 'ph ph-credit-card',
                'permission' => 'ecommerce.admin.payment_providers.manage',
            ]),
            new MenuItem('Configuration', $u('ecommerce_admin_configuration'), [
                'icon' => 'ph ph-wrench',
                'permission' => 'ecommerce.admin.configuration.manage',
            ]),
        ]));
    }
}

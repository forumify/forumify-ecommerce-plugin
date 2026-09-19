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
            new MenuItem('Configuration', $u('ecommerce_admin_configuration'), [
                'icon' => 'ph ph-wrench',
                'permission' => 'ecommerce.admin.configuration.manage',
            ]),
        ]));
    }
}

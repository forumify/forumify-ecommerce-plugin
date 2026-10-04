<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('', 'dashboard')]
#[IsGranted('ecommerce.admin.dashboard')]
class DashboardController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/dashboard/dashboard.html.twig');
    }
}

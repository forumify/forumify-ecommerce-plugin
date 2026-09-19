<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('/orders', 'orders', priority: 10)]
    public function orders(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/orders.html.twig');
    }
}

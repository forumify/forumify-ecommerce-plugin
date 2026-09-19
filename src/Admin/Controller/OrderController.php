<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Ecommerce\Entity\Order;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders', 'orders')]
#[IsGranted('ecommerce.admin.orders.view')]
class OrderController extends AbstractController
{
    #[Route('', '_list')]
    public function list(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/orders/list.html.twig');
    }

    #[Route('/{id}', '_view', requirements: ['id' => '\d+'])]
    public function view(Order $order): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/orders/view.html.twig', [
            'order' => $order,
        ]);
    }
}

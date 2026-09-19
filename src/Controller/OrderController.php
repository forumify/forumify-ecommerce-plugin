<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(private readonly SubscriptionRepository $subscriptionRepository)
    {
    }

    #[Route('/orders', 'orders', priority: 10)]
    public function orders(): Response
    {
        $user = $this->getUser();

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/orders.html.twig', [
            'subscriptions' => $user instanceof User ? $this->subscriptionRepository->findCurrentForUser($user) : [],
        ]);
    }
}

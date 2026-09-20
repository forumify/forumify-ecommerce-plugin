<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('/orders', 'orders', priority: 10)]
    public function orders(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/orders.html.twig', [
            'subscriptions' => $this->subscriptionRepository->findCurrentForUser($user),
            'hasOrders' => $this->orderRepository->count(['user' => $user]) > 0,
        ]);
    }

    #[Route('/subscription/{id}', 'subscription', requirements: ['id' => '\d+'])]
    public function subscription(#[MapEntity(id: 'id')] Subscription $subscription): Response
    {
        if (!$subscription->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException();
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/subscription.html.twig', [
            'subscription' => $subscription,
        ]);
    }
}

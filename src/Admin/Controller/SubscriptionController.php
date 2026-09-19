<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Ecommerce\Admin\Form\SubscriptionCancelType;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\Service\SubscriptionCancellationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/subscriptions', 'subscriptions')]
#[IsGranted('ecommerce.admin.subscriptions.view')]
class SubscriptionController extends AbstractController
{
    public function __construct(private readonly SubscriptionCancellationService $cancellationService)
    {
    }

    #[Route('', '_list')]
    public function list(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/subscriptions/list.html.twig');
    }

    #[Route('/{id}', '_view', requirements: ['id' => '\d+'])]
    public function view(Subscription $subscription): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/subscriptions/view.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/{id}/cancel', '_cancel', requirements: ['id' => '\d+'])]
    #[IsGranted('ecommerce.admin.subscriptions.manage')]
    public function cancel(Request $request, Subscription $subscription): Response
    {
        $viewPath = $this->generateUrl('ecommerce_admin_subscriptions_view', ['id' => $subscription->getId()]);
        if ($subscription->status === SubscriptionStatus::Ended) {
            $this->addFlash('error', 'ecommerce.admin.subscription.error.already_ended');
            return $this->redirect($viewPath);
        }

        $allowPeriodEnd = $subscription->status !== SubscriptionStatus::Cancelled;
        $form = $this->createForm(SubscriptionCancelType::class, ['atPeriodEnd' => $allowPeriodEnd], [
            'allow_period_end' => $allowPeriodEnd,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $atPeriodEnd = (bool)$form->get('atPeriodEnd')->getData();
            try {
                $this->cancellationService->cancel($subscription, $atPeriodEnd);
            } catch (SubscriptionException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirect($viewPath);
            }

            $this->addFlash('success', $atPeriodEnd ? 'ecommerce.admin.subscription.cancelled_at_period_end' : 'ecommerce.admin.subscription.cancelled_now');
            return $this->redirect($viewPath);
        }

        return $this->render('@ForumifyEcommercePlugin/admin/subscriptions/cancel.html.twig', [
            'subscription' => $subscription,
            'form' => $form->createView(),
            'cancelPath' => $viewPath,
        ]);
    }
}

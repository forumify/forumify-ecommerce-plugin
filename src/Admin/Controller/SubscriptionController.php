<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Admin\Form\SubscriptionCancelType;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Exception\RefundException;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Service\CancellationRequestService;
use Forumify\Ecommerce\Service\RefundService;
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
    public function __construct(
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly CancellationRequestService $cancellationRequests,
        private readonly RefundService $refundService,
        private readonly OrderRepository $orderRepository,
    ) {
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
        $lastOrder = $this->orderRepository->findLatestPaidForSubscription($subscription);
        $refundable = $lastOrder !== null && $this->refundService->canRefund($lastOrder);

        $form = $this->createForm(SubscriptionCancelType::class, ['atPeriodEnd' => $allowPeriodEnd], [
            'allow_period_end' => $allowPeriodEnd,
            'allow_refund' => $refundable,
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

            $message = $atPeriodEnd
                ? 'ecommerce.admin.subscription.cancelled_at_period_end'
                : 'ecommerce.admin.subscription.cancelled_now';
            $this->addFlash('success', $message);

            if ($refundable && $form->has('refundLastOrder')) {
                $this->refundLastOrder($lastOrder, (bool)$form->get('refundLastOrder')->getData());
            }

            return $this->redirect($viewPath);
        }

        return $this->render('@ForumifyEcommercePlugin/admin/subscriptions/cancel.html.twig', [
            'subscription' => $subscription,
            'form' => $form->createView(),
            'cancelPath' => $viewPath,
            'lastOrder' => $refundable ? $lastOrder : null,
        ]);
    }

    #[Route('/{id}/handle-cancellation', '_handle_cancellation', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ecommerce.admin.subscriptions.manage')]
    public function handleCancellation(Request $request, Subscription $subscription): Response
    {
        $viewPath = $this->generateUrl('ecommerce_admin_subscriptions_view', ['id' => $subscription->getId()]);
        $cancellation = $subscription->cancellationRequest;
        $token = $request->request->getString('_token');
        if ($cancellation === null || !$this->isCsrfTokenValid('ecommerce_handle_cancellation', $token)) {
            return $this->redirect($viewPath);
        }

        $admin = $this->getUser();
        if ($this->cancellationRequests->complete($cancellation, $admin instanceof User ? $admin : null)) {
            $this->addFlash('success', 'ecommerce.admin.subscription.cancellation_handled');
        }

        return $this->redirect($viewPath);
    }

    private function refundLastOrder(Order $order, bool $refund): void
    {
        if (!$refund) {
            return;
        }

        try {
            $this->refundService->refund($order);
            $this->addFlash('success', 'ecommerce.admin.subscription.refunded_last');
        } catch (RefundException $e) {
            $this->addFlash('error', $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\Subscription;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Exception\RefundException;
use Forumify\Ecommerce\Exception\SubscriptionException;
use Forumify\Ecommerce\Form\RefundRequestType;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderCapability;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\RefundService;
use Forumify\Ecommerce\Service\SubscriptionCancellationService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentProviderRegistry $registry,
        private readonly RefundService $refundService,
        private readonly SubscriptionCancellationService $cancellationService,
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
        $this->denyAccessUnlessOwner($subscription);

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/subscription.html.twig', [
            'subscription' => $subscription,
            'cancellable' => $this->isCancellable($subscription),
        ]);
    }

    #[Route('/subscription/{id}/cancel', 'subscription_cancel', requirements: ['id' => '\d+'])]
    public function cancelSubscription(Request $request, #[MapEntity(id: 'id')] Subscription $subscription): Response
    {
        $this->denyAccessUnlessOwner($subscription);
        if (!$this->isCancellable($subscription)) {
            throw $this->createNotFoundException();
        }

        $subscriptionPath = $this->generateUrl('ecommerce_subscription', ['id' => $subscription->getId()]);
        $token = $request->request->getString('_token');
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('ecommerce_cancel_subscription', $token)) {
            try {
                $this->cancellationService->cancel($subscription, atPeriodEnd: true);
                $this->addFlash('success', 'ecommerce.subscriptions.cancelled');
            } catch (SubscriptionException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirect($subscriptionPath);
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/cancel_subscription.html.twig', [
            'subscription' => $subscription,
            'subscriptionPath' => $subscriptionPath,
        ]);
    }

    #[Route('/order/{reference}/invoice', 'order_invoice')]
    public function invoice(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        $this->denyAccessUnlessOwner($order);

        $provider = $this->registry->findCapableFor($order, PaymentProviderCapability::Invoice);
        $invoice = $provider?->getInvoice($order);
        if ($invoice === null) {
            throw $this->createNotFoundException();
        }

        if ($invoice->url !== null) {
            return $this->redirect($invoice->url);
        }

        return new Response($invoice->getContents(), Response::HTTP_OK, [
            'Content-Type' => $invoice->contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $invoice->filename,
            ),
        ]);
    }

    #[Route('/order/{reference}/pay', 'order_pay')]
    public function pay(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        $this->denyAccessUnlessOwner($order);

        $provider = $this->registry->findCapableFor($order, PaymentProviderCapability::HostedPayment);
        $url = $provider?->getHostedPaymentUrl($order);
        if ($url === null) {
            throw $this->createNotFoundException();
        }

        return $this->redirect($url);
    }

    #[Route('/order/{reference}/refund', 'order_refund')]
    public function requestRefund(
        Request $request,
        #[MapEntity(mapping: ['reference' => 'reference'])] Order $order,
    ): Response {
        $this->denyAccessUnlessOwner($order);
        if (!$order->isRefundable() || $order->refundRequest !== null) {
            throw $this->createNotFoundException();
        }

        $orderPath = $this->generateUrl('ecommerce_order', ['reference' => $order->reference]);
        $form = $this->createForm(RefundRequestType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            try {
                $this->refundService->request(
                    $order,
                    $user instanceof User ? $user : null,
                    (string)$form->get('reason')->getData(),
                );
                $this->addFlash('success', 'ecommerce.refund.requested');
            } catch (RefundException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirect($orderPath);
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/refund.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'orderPath' => $orderPath,
            'endsSubscription' => $this->refundService->findSubscriptionToEnd($order) !== null,
        ]);
    }

    private function isCancellable(Subscription $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Active
            || $subscription->status === SubscriptionStatus::PastDue;
    }

    private function denyAccessUnlessOwner(Order|Subscription $entity): void
    {
        if (!$entity->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException();
        }
    }
}

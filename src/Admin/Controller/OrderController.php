<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Admin\Form\OrderCompleteType;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\FulfillmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders', 'orders')]
#[IsGranted('ecommerce.admin.orders.view')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
        private readonly FulfillmentService $fulfillmentService,
    ) {
    }

    #[Route('', '_list')]
    public function list(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/orders/list.html.twig');
    }

    #[Route('/{id}', '_view', requirements: ['id' => '\d+'])]
    public function view(Order $order): Response
    {
        $completeForm = $order->status === OrderStatus::Paid && $this->isGranted('ecommerce.admin.orders.fulfill')
            ? $this->createCompleteForm($order)->createView()
            : null;

        return $this->render('@ForumifyEcommercePlugin/admin/orders/view.html.twig', [
            'order' => $order,
            'subscription' => $order->subscription ?? $this->subscriptionRepository->findOneByInitialOrder($order),
            'completeForm' => $completeForm,
        ]);
    }

    #[Route('/{id}/complete', '_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ecommerce.admin.orders.fulfill')]
    public function complete(Request $request, Order $order): Response
    {
        $form = $this->createCompleteForm($order);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('ecommerce_admin_orders_view', ['id' => $order->getId()]);
        }

        $admin = $this->getUser();
        $completed = $this->fulfillmentService->complete(
            $order,
            $admin instanceof User ? $admin : null,
            $form->get('note')->getData(),
        );

        $this->addFlash(
            $completed ? 'success' : 'error',
            $completed ? 'ecommerce.admin.order.completed' : 'ecommerce.admin.order.not_completable',
        );
        return $this->redirectToRoute('ecommerce_admin_orders_view', ['id' => $order->getId()]);
    }

    /**
     * @return FormInterface<array{note: ?string, confirm?: bool}|null>
     */
    private function createCompleteForm(Order $order): FormInterface
    {
        return $this->createForm(OrderCompleteType::class, null, [
            'action' => $this->generateUrl('ecommerce_admin_orders_complete', ['id' => $order->getId()]),
            'manual' => $this->fulfillmentTypes->requiresManualFulfillment($order->fulfillmentType),
        ]);
    }
}

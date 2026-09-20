<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Admin\Form\RefundRequestHandleType;
use Forumify\Ecommerce\Entity\RefundRequest;
use Forumify\Ecommerce\Entity\RefundRequestStatus;
use Forumify\Ecommerce\Exception\RefundException;
use Forumify\Ecommerce\Service\RefundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/refunds', 'refunds')]
#[IsGranted('ecommerce.admin.orders.refund')]
class RefundRequestController extends AbstractController
{
    public function __construct(private readonly RefundService $refundService)
    {
    }

    #[Route('', '_list')]
    public function list(): Response
    {
        return $this->render('@ForumifyEcommercePlugin/admin/refunds/list.html.twig');
    }

    #[Route('/{id}/handle', '_handle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function handle(Request $request, RefundRequest $refundRequest): Response
    {
        $orderPath = $this->generateUrl('ecommerce_admin_orders_view', ['id' => $refundRequest->order->getId()]);
        if ($refundRequest->status !== RefundRequestStatus::Pending) {
            return $this->redirect($orderPath);
        }

        $form = $this->createHandleForm($refundRequest);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'ecommerce.admin.refund.invalid');
            return $this->redirect($orderPath);
        }

        $admin = $this->getUser();
        $admin = $admin instanceof User ? $admin : null;
        $note = $form->get('note')->getData();
        $note = is_string($note) ? $note : null;

        $approve = $form->get('approve');
        if ($approve instanceof ClickableInterface && $approve->isClicked()) {
            try {
                $this->refundService->approve($refundRequest, $admin, $note);
                $this->addFlash('success', 'ecommerce.admin.refund.approved');
            } catch (RefundException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirect($orderPath);
        }

        $this->refundService->reject($refundRequest, $admin, $note);
        $this->addFlash('success', 'ecommerce.admin.refund.rejected');

        return $this->redirect($orderPath);
    }

    /**
     * @return FormInterface<array{note: ?string}|null>
     */
    private function createHandleForm(RefundRequest $request): FormInterface
    {
        return $this->createForm(RefundRequestHandleType::class, null, [
            'action' => $this->generateUrl('ecommerce_admin_refunds_handle', ['id' => $request->getId()]),
        ]);
    }
}

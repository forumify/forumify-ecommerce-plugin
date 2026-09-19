<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Exception\CheckoutException;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\Service\CheckoutService;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentEventHandler $eventHandler,
    ) {
    }

    #[Route('/checkout', 'checkout_select', methods: ['GET'], priority: 10)]
    public function select(#[MapQueryParameter] int $price): Response
    {
        return $this->redirectToRoute('ecommerce_checkout', ['price' => $price]);
    }

    #[Route('/checkout/{price}', 'checkout', requirements: ['price' => '\d+'], methods: ['GET', 'POST'])]
    public function checkout(Request $request, #[MapEntity(id: 'price')] ProductPrice $price): Response
    {
        if (!$this->checkoutService->isPurchasable($price)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $redirect = $this->startCheckout($request, $price);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/checkout.html.twig', [
            'price' => $price,
            'providers' => $this->checkoutService->getProviderOptions($price),
        ]);
    }

    #[Route('/order/{reference}', 'order')]
    public function order(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        $this->denyAccessUnlessOwner($order);
        $this->checkoutService->sync($order);

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/order.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/order/{reference}/cancel', 'order_cancel')]
    public function cancel(#[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        $this->denyAccessUnlessOwner($order);
        $this->eventHandler->handle(new CheckoutCancelled($order->reference), $order->providerKey);

        return $this->redirectToRoute('ecommerce_order', ['reference' => $order->reference]);
    }

    private function startCheckout(Request $request, ProductPrice $price): ?Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('ecommerce_checkout', $request->request->getString('_token'))) {
            $this->addFlash('error', 'ecommerce.checkout.error.invalid_token');
            return null;
        }

        $providerKey = $request->request->getString('provider');

        try {
            return $this->redirect($this->checkoutService->checkout($user, $price, $providerKey !== '' ? $providerKey : null)->url);
        } catch (CheckoutException $e) {
            $this->addFlash('error', $e->getMessage());
            return null;
        }
    }

    private function denyAccessUnlessOwner(Order $order): void
    {
        if (!$order->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException();
        }
    }
}

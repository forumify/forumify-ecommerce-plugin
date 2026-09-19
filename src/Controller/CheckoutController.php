<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Exception\CheckoutException;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeInterface;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCancelled;
use Forumify\Ecommerce\Service\CheckoutService;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
        private readonly FulfillmentTypeRegistry $fulfillmentTypes,
        private readonly FormFactoryInterface $formFactory,
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

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $fulfillmentType = $this->fulfillmentTypes->get($price->tier->product->fulfillmentType);
        $fulfillmentForm = $this->createFulfillmentForm($fulfillmentType, $price, $user);
        $fulfillmentForm?->handleRequest($request);

        if ($request->isMethod('POST')) {
            $redirect = $this->startCheckout($request, $price, $user, $fulfillmentType, $fulfillmentForm);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/checkout/checkout.html.twig', [
            'price' => $price,
            'providers' => $this->checkoutService->getProviderOptions($price),
            'fulfillmentForm' => $fulfillmentForm?->createView(),
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

    /**
     * @return FormInterface<mixed>|null
     */
    private function createFulfillmentForm(?FulfillmentTypeInterface $type, ProductPrice $price, User $user): ?FormInterface
    {
        $formType = $type?->getCheckoutFormType();
        if ($formType === null) {
            return null;
        }

        $form = $this->formFactory->createNamed('fulfillment', $formType, null, [
            'csrf_protection' => false,
            'product' => $price->tier->product,
            'user' => $user,
        ]);

        return $form->count() > 0 ? $form : null;
    }

    /**
     * @param FormInterface<mixed>|null $fulfillmentForm
     */
    private function startCheckout(
        Request $request,
        ProductPrice $price,
        User $user,
        ?FulfillmentTypeInterface $fulfillmentType,
        ?FormInterface $fulfillmentForm,
    ): ?Response {
        if (!$this->isCsrfTokenValid('ecommerce_checkout', $request->request->getString('_token'))) {
            $this->addFlash('error', 'ecommerce.checkout.error.invalid_token');
            return null;
        }

        if ($fulfillmentForm !== null && (!$fulfillmentForm->isSubmitted() || !$fulfillmentForm->isValid())) {
            return null;
        }

        $fulfillmentData = $fulfillmentForm !== null && $fulfillmentType !== null
            ? $fulfillmentType->normalizeCheckoutData($fulfillmentForm->getData())
            : [];
        $providerKey = $request->request->getString('provider');

        try {
            $redirect = $this->checkoutService->checkout($user, $price, $providerKey !== '' ? $providerKey : null, $fulfillmentData);
        } catch (CheckoutException $e) {
            $this->addFlash('error', $e->getMessage());
            return null;
        }

        return $this->redirect($redirect->url);
    }

    private function denyAccessUnlessOwner(Order $order): void
    {
        if (!$order->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException();
        }
    }
}

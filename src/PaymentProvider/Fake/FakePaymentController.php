<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\PaymentProvider\Fake;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\Event\PaymentEvent;
use Forumify\Ecommerce\PaymentProvider\Event\SubscriptionRenewed;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fake-payment', 'fake_payment')]
#[IsGranted('ROLE_USER')]
#[WhenNot(env: 'prod')]
class FakePaymentController extends AbstractController
{
    public function __construct(private readonly PaymentEventHandler $eventHandler)
    {
    }

    #[Route('/{reference}', '_pay', methods: ['GET', 'POST'])]
    public function pay(Request $request, #[MapEntity(mapping: ['reference' => 'reference'])] Order $order): Response
    {
        if (!$order->isOwnedBy($this->getUser()) || $order->providerKey !== FakePaymentProvider::KEY) {
            throw $this->createNotFoundException();
        }

        if ($order->status !== OrderStatus::Pending) {
            return $this->redirectToRoute('ecommerce_order', ['reference' => $order->reference]);
        }

        $token = $request->request->getString('_token');
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('ecommerce_fake_payment', $token)) {
            return match ($request->request->getString('outcome')) {
                'pay' => $this->finish($order, self::paid($order)),
                'fail' => $this->finish($order, new CheckoutFailed($order->reference)),
                'cancel' => $this->redirectToRoute('ecommerce_order_cancel', ['reference' => $order->reference]),
                default => $this->redirectToRoute('ecommerce_fake_payment_pay', ['reference' => $order->reference]),
            };
        }

        return $this->render('@ForumifyEcommercePlugin/frontend/fake_payment/pay.html.twig', [
            'order' => $order,
        ]);
    }

    private function finish(Order $order, PaymentEvent $event): Response
    {
        $this->eventHandler->handle($event, FakePaymentProvider::KEY);

        return $this->redirectToRoute('ecommerce_order', ['reference' => $order->reference]);
    }

    /**
     * Paying a renewal settles its subscription's period, the way a provider's retry would.
     */
    private static function paid(Order $order): PaymentEvent
    {
        $subscription = $order->subscription;
        if ($subscription === null) {
            return FakePaymentProvider::completed($order->reference);
        }

        return new SubscriptionRenewed(
            $subscription->providerReference,
            (string)$order->providerReference,
            $order->periodEndFrom($subscription->currentPeriodEnd),
        );
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductState;
use Forumify\Ecommerce\Exception\CheckoutException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\OrderRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentEventHandler $eventHandler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isPurchasable(ProductPrice $price): bool
    {
        return $price->active && $price->tier->product->state === ProductState::Published;
    }

    /**
     * @return list<PaymentProviderInterface>
     */
    public function getProviderOptions(ProductPrice $price): array
    {
        return $price->isFree() ? [] : $this->registry->findAvailableFor($price);
    }

    /**
     * @throws CheckoutException
     */
    public function checkout(User $user, ProductPrice $price, ?string $providerKey = null): CheckoutRedirect
    {
        if (!$this->isPurchasable($price)) {
            throw CheckoutException::notPurchasable();
        }

        if ($price->isFree()) {
            $order = $this->placeOrder($user, $price, null);
            $this->eventHandler->handle(new CheckoutCompleted($order->reference), null);

            return new CheckoutRedirect($this->getOrderUrl($order));
        }

        $provider = $this->chooseProvider($price, $providerKey);

        return $this->startProviderCheckout($provider, $this->placeOrder($user, $price, $provider->getKey()));
    }

    public function sync(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending || $order->providerKey === null) {
            return;
        }

        $provider = $this->registry->findConfigured($order->providerKey);
        if ($provider === null) {
            return;
        }

        try {
            $events = $provider->syncOrder($order);
        } catch (Throwable $e) {
            $this->logger->error('Syncing an order with its payment provider failed.', [
                'order' => $order->reference,
                'provider' => $provider->getKey(),
                'exception' => $e,
            ]);
            return;
        }

        $this->eventHandler->handleAll($events, $provider->getKey());
    }

    private function placeOrder(User $user, ProductPrice $price, ?string $providerKey): Order
    {
        $order = new Order($user, $price, $providerKey);
        $price->lock();
        $this->orderRepository->save($order);

        return $order;
    }

    private function chooseProvider(ProductPrice $price, ?string $providerKey): PaymentProviderInterface
    {
        $options = $this->registry->findAvailableFor($price);
        if ($options === []) {
            throw CheckoutException::noProvider();
        }

        if ($providerKey === null && count($options) === 1) {
            return $options[0];
        }

        foreach ($options as $option) {
            if ($option->getKey() === $providerKey) {
                return $option;
            }
        }

        throw CheckoutException::invalidProvider();
    }

    private function startProviderCheckout(PaymentProviderInterface $provider, Order $order): CheckoutRedirect
    {
        $urls = new CheckoutUrls(
            $this->getOrderUrl($order),
            $this->urlGenerator->generate('ecommerce_order_cancel', ['reference' => $order->reference], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        try {
            $redirect = $provider->startCheckout($order, $urls);
        } catch (Throwable $e) {
            $this->logger->error('Starting a checkout with the payment provider failed.', [
                'order' => $order->reference,
                'provider' => $provider->getKey(),
                'exception' => $e,
            ]);
            $this->eventHandler->handle(new CheckoutFailed($order->reference), $provider->getKey());

            throw CheckoutException::providerFailed();
        }

        $order->providerReference = $redirect->providerReference;
        $this->orderRepository->save($order);

        return $redirect;
    }

    private function getOrderUrl(Order $order): string
    {
        return $this->urlGenerator->generate('ecommerce_order', ['reference' => $order->reference], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}

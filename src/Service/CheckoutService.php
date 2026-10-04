<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Forumify\Core\Entity\User;
use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Entity\OrderStatus;
use Forumify\Ecommerce\Entity\ProductPrice;
use Forumify\Ecommerce\Entity\ProductStatus;
use Forumify\Ecommerce\Event\OrderPlacedEvent;
use Forumify\Ecommerce\Exception\CheckoutException;
use Forumify\Ecommerce\PaymentProvider\CheckoutRedirect;
use Forumify\Ecommerce\PaymentProvider\CheckoutUrls;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutCompleted;
use Forumify\Ecommerce\PaymentProvider\Event\CheckoutFailed;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderInterface;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Revenue\RevenueCapNotifier;
use Forumify\Ecommerce\Revenue\RevenueCapService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentEventHandler $eventHandler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RevenueCapService $revenueCapService,
        private readonly RevenueCapNotifier $revenueCapNotifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isPurchasable(ProductPrice $price): bool
    {
        return $price->active && $price->tier->product->status === ProductStatus::Published;
    }

    /**
     * A store that would go over the revenue its plan allows cannot sell anything that costs money.
     * Free products stay available: they add nothing to the revenue that is capped.
     */
    public function isBlockedByRevenueCap(ProductPrice $price): bool
    {
        return !$price->isFree() && $this->revenueCapService->isBlocked($price->getAmount());
    }

    /**
     * @return list<PaymentProviderInterface>
     */
    public function getProviderOptions(ProductPrice $price): array
    {
        return $price->isFree() ? [] : $this->registry->findAvailableFor($price);
    }

    /**
     * @param array<string, mixed> $fulfillmentData
     * @throws CheckoutException
     */
    public function checkout(
        User $user,
        ProductPrice $price,
        ?string $providerKey = null,
        array $fulfillmentData = [],
    ): CheckoutRedirect {
        if (!$this->isPurchasable($price)) {
            throw CheckoutException::notPurchasable();
        }

        if ($this->isBlockedByRevenueCap($price)) {
            $this->revenueCapNotifier->notifyBlocked($this->revenueCapService->getUsage());
            throw CheckoutException::revenueCapReached();
        }

        if ($price->isFree()) {
            $order = $this->placeOrder($user, $price, null, $fulfillmentData);
            $this->eventHandler->handle(new CheckoutCompleted($order->reference), null);

            return new CheckoutRedirect($this->getOrderUrl($order));
        }

        $provider = $this->chooseProvider($price, $providerKey);

        $order = $this->placeOrder($user, $price, $provider->getKey(), $fulfillmentData);

        return $this->startProviderCheckout($provider, $order);
    }

    public function sync(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending || $order->providerKey === null || $order->isRenewal()) {
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

    /**
     * @param array<string, mixed> $fulfillmentData
     * @throws CheckoutException
     */
    private function placeOrder(User $user, ProductPrice $price, ?string $providerKey, array $fulfillmentData): Order
    {
        $order = new Order($user, $price, $providerKey, fulfillmentData: $fulfillmentData);
        $price->lock();
        $this->orderRepository->save($order);

        try {
            $this->eventDispatcher->dispatch(new OrderPlacedEvent($order));
        } catch (CheckoutException $e) {
            $order->cancel();
            $this->orderRepository->save($order);

            throw $e;
        }

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
            $this->absoluteUrl('ecommerce_order_cancel', $order),
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
        return $this->absoluteUrl('ecommerce_order', $order);
    }

    private function absoluteUrl(string $route, Order $order): string
    {
        return $this->urlGenerator->generate(
            $route,
            ['reference' => $order->reference],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}

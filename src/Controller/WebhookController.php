<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Controller;

use Forumify\Ecommerce\Exception\InvalidWebhookException;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Service\PaymentEventHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly PaymentEventHandler $eventHandler,
    ) {
    }

    #[Route('/webhook/{providerKey}', 'webhook', methods: ['POST'])]
    public function webhook(Request $request, string $providerKey): Response
    {
        $provider = $this->registry->findConfigured($providerKey);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        try {
            $events = $provider->handleWebhook($request);
        } catch (InvalidWebhookException) {
            return new Response('Invalid webhook.', Response::HTTP_BAD_REQUEST);
        }

        $this->eventHandler->handleAll($events, $provider->getKey());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}

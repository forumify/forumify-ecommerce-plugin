<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Ecommerce\Admin\Form\PaymentProviderConfigType;
use Forumify\Ecommerce\PaymentProvider\PaymentProviderRegistry;
use Forumify\Ecommerce\Repository\PaymentProviderConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment-providers', 'payment_providers')]
#[IsGranted('ecommerce.admin.payment_providers.manage')]
class PaymentProviderController extends AbstractController
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly PaymentProviderConfigRepository $configRepository,
    ) {
    }

    #[Route('', '_list')]
    public function list(): Response
    {
        $rows = [];
        foreach ($this->registry->all() as $provider) {
            $config = $this->registry->getConfig($provider);
            $rows[] = [
                'provider' => $provider,
                'enabled' => $config->enabled,
                'configured' => $provider->isConfigured($config),
            ];
        }

        return $this->render('@ForumifyEcommercePlugin/admin/payment_providers/list.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/{key}/edit', '_edit')]
    public function edit(Request $request, string $key): Response
    {
        $provider = $this->registry->get($key);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        $config = $this->registry->getConfig($provider);
        $form = $this->createForm(PaymentProviderConfigType::class, $config, [
            'settings_type' => $provider->getSettingsFormType(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->configRepository->save($config);

            $this->addFlash('success', 'ecommerce.admin.payment_provider.saved');
            return $this->redirectToRoute('ecommerce_admin_payment_providers_list');
        }

        $webhookUrl = $this->generateUrl(
            'ecommerce_webhook',
            ['providerKey' => $key],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->render('@ForumifyEcommercePlugin/admin/payment_providers/edit.html.twig', [
            'provider' => $provider,
            'form' => $form->createView(),
            'webhookUrl' => $webhookUrl,
        ]);
    }
}

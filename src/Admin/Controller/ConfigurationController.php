<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Admin\Controller;

use Forumify\Core\Repository\SettingRepository;
use Forumify\Ecommerce\Admin\Form\ConfigurationType;
use Forumify\Ecommerce\Service\StoreSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuration', 'configuration')]
#[IsGranted('ecommerce.admin.configuration.manage')]
class ConfigurationController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly StoreSettings $storeSettings,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $data = [
            'ecommerce__currency' => $this->storeSettings->getCurrency(),
            ...$this->settingRepository->toFormData('ecommerce.'),
        ];

        $form = $this->createForm(ConfigurationType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingRepository->handleFormData($form->getData());

            $this->addFlash('success', 'ecommerce.admin.configuration.saved');
            return $this->redirectToRoute('ecommerce_admin_configuration');
        }

        return $this->render('@Forumify/form/simple_form_page.html.twig', [
            'admin' => true,
            'form' => $form->createView(),
            'title' => 'ecommerce.admin.configuration.title',
        ]);
    }
}

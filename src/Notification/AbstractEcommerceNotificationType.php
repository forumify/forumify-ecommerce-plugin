<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\AbstractEmailNotificationType;
use Forumify\Ecommerce\Entity\Product;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractEcommerceNotificationType extends AbstractEmailNotificationType
{
    protected TranslatorInterface $translator;
    protected UrlGeneratorInterface $urlGenerator;
    protected Packages $packages;

    #[Required]
    public function setEcommerceServices(
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        Packages $packages,
    ): void {
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->packages = $packages;
    }

    abstract protected function getTranslationKey(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getTranslationParameters(Notification $notification): array;

    public function getTitle(Notification $notification): string
    {
        return $this->translator->trans(
            $this->getTranslationKey() . '.title',
            $this->getTranslationParameters($notification),
        );
    }

    public function getDescription(Notification $notification): string
    {
        return $this->translator->trans(
            $this->getTranslationKey() . '.description',
            $this->getTranslationParameters($notification),
        );
    }

    protected function getProductImage(?Product $product): string
    {
        return $product?->image === null
            ? $this->packages->getUrl('bundles/forumify/images/forumify.svg')
            : $this->packages->getUrl($product->image, 'ecommerce.asset');
    }
}

<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Twig;

use Forumify\Ecommerce\Entity\Order;
use Forumify\Ecommerce\Fulfillment\FulfillmentTypeRegistry;
use Symfony\Component\Intl\Countries;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FulfillmentExtension extends AbstractExtension
{
    public function __construct(private readonly FulfillmentTypeRegistry $registry)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ecommerce_fulfillment_label', $this->registry->getLabel(...)),
            new TwigFunction('ecommerce_fulfillment_is_manual', $this->registry->requiresManualFulfillment(...)),
            new TwigFunction('ecommerce_fulfillment_details_template', $this->getDetailsTemplate(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ecommerce_country_name', static fn(string $code) =>
                Countries::exists($code) ? Countries::getName($code) : $code),
        ];
    }

    private function getDetailsTemplate(Order $order): ?string
    {
        return $this->registry->get($order->fulfillmentType)?->getDetailsTemplate();
    }
}

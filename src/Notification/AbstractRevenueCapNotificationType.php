<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Notification;

use Brick\Money\Money;
use Forumify\Core\Entity\Notification;
use Forumify\Ecommerce\Money\MoneyFormatter;
use Forumify\Ecommerce\Service\StoreSettings;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractRevenueCapNotificationType extends AbstractStoreNotificationType
{
    protected MoneyFormatter $moneyFormatter;

    #[Required]
    public function setRevenueServices(MoneyFormatter $moneyFormatter): void
    {
        $this->moneyFormatter = $moneyFormatter;
    }

    public function getUrl(Notification $notification): string
    {
        return $this->urlGenerator->generate('ecommerce_admin_dashboard');
    }

    public function getEmailActionLabel(): string
    {
        return 'ecommerce.notification.view_revenue';
    }

    protected function getTranslationParameters(Notification $notification): array
    {
        /** @var array{currency?: string, averageMinor?: int, capMinor?: int, percentage?: int} $context */
        $context = $notification->getDeserializedContext() ?? [];
        $currency = $context['currency'] ?? StoreSettings::DEFAULT_CURRENCY;
        $cap = $context['capMinor'] ?? null;

        return [
            'average' => $this->format($context['averageMinor'] ?? 0, $currency),
            'cap' => $cap !== null ? $this->format($cap, $currency) : '',
            'percentage' => $context['percentage'] ?? 0,
        ];
    }

    private function format(int $minor, string $currency): string
    {
        return $this->moneyFormatter->format(Money::ofMinor($minor, $currency));
    }
}

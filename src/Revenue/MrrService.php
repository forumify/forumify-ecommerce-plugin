<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Forumify\Ecommerce\Entity\SubscriptionStatus;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\StoreSettings;

class MrrService
{
    private ?Mrr $mrr = null;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CurrencyConverter $currencyConverter,
        private readonly StoreSettings $storeSettings,
    ) {
    }

    public function getMrr(): Mrr
    {
        if ($this->mrr !== null) {
            return $this->mrr;
        }

        $currency = $this->storeSettings->getCurrency();
        $total = RationalMoney::zero($currency);
        $atRisk = RationalMoney::zero($currency);
        $subscriptions = 0;
        $pastDue = 0;

        foreach ($this->subscriptionRepository->sumOpenByInterval() as $row) {
            $monthly = $this->currencyConverter
                ->convertRational(Money::ofMinor($row['total'], $row['currency']), $currency)
                ->multipliedBy($row['billingInterval']->getTimesPerMonth($row['intervalCount']));

            $total = $total->plus($monthly);
            $subscriptions += $row['count'];
            if ($row['status'] !== SubscriptionStatus::PastDue) {
                continue;
            }

            $atRisk = $atRisk->plus($monthly);
            $pastDue += $row['count'];
        }

        return $this->mrr = new Mrr(
            $total->toContext(new DefaultContext(), RoundingMode::HalfUp),
            $atRisk->toContext(new DefaultContext(), RoundingMode::HalfUp),
            $subscriptions,
            $pastDue,
        );
    }
}

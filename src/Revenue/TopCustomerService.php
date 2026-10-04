<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Revenue;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use DateTimeImmutable;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\Core\Service\TimezoneResolver;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\OrderRepository;
use Forumify\Ecommerce\Repository\SubscriptionRepository;
use Forumify\Ecommerce\Service\StoreSettings;

class TopCustomerService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserRepository $userRepository,
        private readonly CurrencyConverter $currencyConverter,
        private readonly StoreSettings $storeSettings,
        private readonly TimezoneResolver $timezoneResolver,
    ) {
    }

    /**
     * @return list<TopCustomer>
     */
    public function getTopCustomers(SalesPeriod $period, int $limit): array
    {
        $currency = $this->storeSettings->getCurrency();
        $now = new DateTimeImmutable('now', $this->timezoneResolver->getTimezone());

        [$from, $to] = $period->getRange($now) ?? [null, null];

        $customers = [];
        foreach ($this->orderRepository->findTopCustomers($from, $to, $limit) as $row) {
            $customer = $customers[$row['userId']] ?? [
                'orders' => 0,
                'revenue' => RationalMoney::zero($currency),
                'customerSince' => $row['customerSince'],
            ];
            $customer['orders'] += $row['count'];
            $customer['revenue'] = $customer['revenue']->plus($this->currencyConverter->convertRational(
                Money::ofMinor($row['total'], $row['currency']),
                $currency,
            ));
            $customers[$row['userId']] = $customer;
        }

        $userIds = array_keys($customers);
        $users = [];
        foreach ($this->userRepository->findBy(['id' => $userIds]) as $user) {
            $users[$user->getId()] = $user;
        }
        $subscriptions = $this->subscriptionRepository->countOpenByUser($userIds);

        $topCustomers = [];
        foreach ($customers as $userId => $customer) {
            $user = $users[$userId] ?? null;
            if (!$user instanceof User) {
                continue;
            }

            $topCustomers[] = new TopCustomer(
                $user,
                $customer['orders'],
                $customer['revenue']->toContext(new DefaultContext(), RoundingMode::HalfUp),
                $subscriptions[$userId] ?? 0,
                new DateTimeImmutable($customer['customerSince']),
            );
        }

        return $topCustomers;
    }
}

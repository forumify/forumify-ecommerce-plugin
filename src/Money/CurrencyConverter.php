<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Money;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Brick\Money\AbstractMoney;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Psr\Log\LoggerInterface;

/**
 * Converts between currencies with the daily rates in ecommerce_exchange_rate.
 */
class CurrencyConverter
{
    public const BASE_CURRENCY = 'USD';

    /** @var array<string, string>|null */
    private ?array $rates = null;

    public function __construct(
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function convert(Money $money, string $currency): Money
    {
        return $this
            ->convertRational($money, $currency)
            ->toContext(new DefaultContext(), RoundingMode::HalfUp);
    }

    public function convertRational(AbstractMoney $money, string $currency): RationalMoney
    {
        $from = $money->getCurrency()->getCurrencyCode();
        if ($from === $currency) {
            return $money->toRational();
        }

        return $money->toRational()->convertedTo($currency, $this->getRate($from, $currency));
    }

    /**
     * How much of $to one unit of $from buys.
     */
    public function getRate(string $from, string $to): BigRational
    {
        if ($from === $to) {
            return BigRational::one();
        }

        return $this->getRateAgainstBase($to)->dividedBy($this->getRateAgainstBase($from));
    }

    private function getRateAgainstBase(string $currency): BigRational
    {
        if ($currency === self::BASE_CURRENCY) {
            return BigRational::one();
        }

        $this->rates ??= $this->exchangeRateRepository->findAllRates();
        $rate = $this->rates[$currency] ?? null;
        if ($rate === null) {
            $this->logger->warning('No exchange rate is known, falling back to face value.', [
                'currency' => $currency,
            ]);
            return BigRational::one();
        }

        return BigRational::of($rate);
    }
}

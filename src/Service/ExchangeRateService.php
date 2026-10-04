<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Service;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Forumify\Ecommerce\Entity\ExchangeRate;
use Forumify\Ecommerce\Money\CurrencyConverter;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Refreshes the exchange rates from frankfurter.dev (ECB reference rates). A failed refresh leaves the
 * rates that are already stored in place: yesterday's rates are close enough for a monthly revenue limit.
 */
class ExchangeRateService
{
    public const ENDPOINT = 'https://api.frankfurter.dev/v1/latest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return int the number of currencies that were refreshed
     */
    public function refresh(): int
    {
        $response = $this->fetch();
        if ($response === null) {
            return 0;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $response['date']);
        if ($date === false) {
            $this->logger->error('The exchange rate service returned a date we cannot read.', $response);
            return 0;
        }

        $stored = $this->exchangeRateRepository->findAll();
        $known = [];
        foreach ($stored as $rate) {
            $known[$rate->currency] = $rate;
        }

        $updated = [];
        foreach ($response['rates'] as $currency => $rate) {
            if (!in_array($currency, StoreSettings::SUPPORTED_CURRENCIES, true)) {
                continue;
            }

            $updated[] = $this->apply($known[$currency] ?? null, $currency, BigDecimal::of((string)$rate), $date);
        }

        $base = CurrencyConverter::BASE_CURRENCY;
        $updated[] = $this->apply($known[$base] ?? null, $base, BigDecimal::one(), $date);
        $this->exchangeRateRepository->saveAll($updated);

        return count($updated);
    }

    private function apply(
        ?ExchangeRate $rate,
        string $currency,
        BigDecimal $value,
        DateTimeImmutable $date,
    ): ExchangeRate {
        if ($rate === null) {
            return new ExchangeRate($currency, $value, $date);
        }

        $rate->rate = (string)$value;
        $rate->date = $date;

        return $rate;
    }

    /**
     * @return array{date: string, rates: array<string, float|int>}|null
     */
    private function fetch(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => ['base' => CurrencyConverter::BASE_CURRENCY],
                'timeout' => 10,
            ]);

            /** @var array{date?: string, rates?: array<string, float|int>} $data */
            $data = $response->toArray();
        } catch (Throwable $e) {
            $this->logger->warning('Refreshing the exchange rates failed, keeping the rates we have.', [
                'exception' => $e,
            ]);
            return null;
        }

        if (!isset($data['date'], $data['rates'])) {
            $this->logger->error('The exchange rate service returned something we cannot read.', $data);
            return null;
        }

        return ['date' => $data['date'], 'rates' => $data['rates']];
    }
}

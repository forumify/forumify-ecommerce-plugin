<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use Forumify\Ecommerce\Entity\ExchangeRate;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;
use Forumify\Ecommerce\Service\ExchangeRateService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExchangeRateServiceTest extends TestCase
{
    public function testRatesWeSupportAreStoredWithTheirDate(): void
    {
        $repository = $this->createMock(ExchangeRateRepository::class);
        $repository->method('findAll')->willReturn([]);

        $saved = [];
        $repository
            ->expects(self::once())
            ->method('saveAll')
            ->willReturnCallback(static function (array $rates) use (&$saved): void {
                $saved = $rates;
            });

        $client = new MockHttpClient(new MockResponse(json_encode([
            'amount' => 1.0,
            'base' => 'USD',
            'date' => '2026-09-18',
            'rates' => ['EUR' => 0.8726, 'JPY' => 157.89, 'XYZ' => 3.0],
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(3, new ExchangeRateService($client, $repository, new NullLogger())->refresh());

        $rates = [];
        foreach ($saved as $rate) {
            self::assertInstanceOf(ExchangeRate::class, $rate);
            self::assertSame('2026-09-18', $rate->date->format('Y-m-d'));
            $rates[$rate->currency] = $rate->rate;
        }

        self::assertSame(['EUR' => '0.8726', 'JPY' => '157.89', 'USD' => '1'], $rates);
    }

    public function testAFailedRefreshKeepsTheRatesWeHave(): void
    {
        $repository = $this->createMock(ExchangeRateRepository::class);
        $repository->expects(self::never())->method('saveAll');

        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        self::assertSame(0, new ExchangeRateService($client, $repository, new NullLogger())->refresh());
    }
}

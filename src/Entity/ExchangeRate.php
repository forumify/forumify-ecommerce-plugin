<?php

declare(strict_types=1);

namespace Forumify\Ecommerce\Entity;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Ecommerce\Repository\ExchangeRateRepository;

/**
 * How much of a currency one US dollar buys. The plugin's revenue limits are in dollars, stores sell in
 * their own currency, so the two only meet through this table.
 */
#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table('ecommerce_exchange_rate')]
class ExchangeRate
{
    use TimestampableEntityTrait;

    #[ORM\Id]
    #[ORM\Column(length: 3)]
    public private(set) string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 24, scale: 12)]
    public string $rate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    public DateTimeImmutable $date;

    public function __construct(string $currency, BigDecimal|string $rate, DateTimeImmutable $date)
    {
        $this->currency = $currency;
        $this->rate = (string)$rate;
        $this->date = $date;
    }

    public function getRate(): BigDecimal
    {
        return BigDecimal::of($this->rate);
    }
}

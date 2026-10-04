<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920194753 extends AbstractMigration
{
    /**
     * ECB reference rates of 2026-09-18, in units per US dollar. The daily refresh replaces them, this
     * is only here so a fresh install can convert before it ever reached the rate service.
     */
    private const RATES = [
        'AUD' => '1.404500000000',
        'BRL' => '5.135900000000',
        'CAD' => '1.401000000000',
        'CHF' => '0.825650000000',
        'CNY' => '6.697600000000',
        'CZK' => '21.238000000000',
        'DKK' => '6.523000000000',
        'EUR' => '0.872600000000',
        'GBP' => '0.749390000000',
        'HKD' => '7.844900000000',
        'INR' => '95.880000000000',
        'JPY' => '157.890000000000',
        'KRW' => '1388.100000000000',
        'MXN' => '17.177600000000',
        'NOK' => '9.432400000000',
        'NZD' => '1.751100000000',
        'PLN' => '3.807600000000',
        'SEK' => '9.853000000000',
        'SGD' => '1.278400000000',
        'USD' => '1.000000000000',
        'ZAR' => '16.272400000000',
    ];

    public function getDescription(): string
    {
        return 'Add the exchange rate table, seeded with the rates of 2026-09-18, and index orders by revenue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_exchange_rate (currency VARCHAR(3) NOT NULL, rate NUMERIC(24, 12) NOT NULL, date DATE NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_3AF76E078B8E8428 (created_at), INDEX IDX_3AF76E0743625D9F (updated_at), PRIMARY KEY (currency)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE INDEX idx_ecommerce_order_revenue ON ecommerce_order (status, paid_at)');

        foreach (self::RATES as $currency => $rate) {
            $this->addSql(
                'INSERT INTO ecommerce_exchange_rate (currency, rate, date, created_at)'
                    . ' VALUES (:currency, :rate, :date, NOW())',
                ['currency' => $currency, 'rate' => $rate, 'date' => '2026-09-18'],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ecommerce_order_revenue ON ecommerce_order');
        $this->addSql('DROP TABLE ecommerce_exchange_rate');
    }
}

<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metadata to orders, for integrations to attach values like a referral code at checkout.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_order ADD metadata JSON DEFAULT NULL');
        $this->addSql('UPDATE ecommerce_order SET metadata = JSON_ARRAY()');
        $this->addSql('ALTER TABLE ecommerce_order MODIFY metadata JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_order DROP metadata');
    }
}

<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add orders and payment provider configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_order (reference VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, provider_key VARCHAR(32) DEFAULT NULL, provider_reference VARCHAR(255) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, product_key VARCHAR(128) NOT NULL, product_title VARCHAR(255) NOT NULL, tier_key VARCHAR(64) NOT NULL, tier_label VARCHAR(255) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, price_type VARCHAR(16) NOT NULL, billing_interval VARCHAR(16) DEFAULT NULL, interval_count INT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, product_id INT DEFAULT NULL, price_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F24AD515AEA34913 (reference), INDEX IDX_F24AD5158B8E8428 (created_at), INDEX IDX_F24AD51543625D9F (updated_at), INDEX IDX_F24AD515A76ED395 (user_id), INDEX IDX_F24AD5154584665A (product_id), INDEX IDX_F24AD515D614C7E7 (price_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_payment_provider_config (provider_key VARCHAR(32) NOT NULL, enabled TINYINT NOT NULL, settings JSON NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_A809EDB6D74DDA64 (provider_key), INDEX IDX_A809EDB68B8E8428 (created_at), INDEX IDX_A809EDB643625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_order ADD CONSTRAINT FK_F24AD515A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD CONSTRAINT FK_F24AD5154584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD CONSTRAINT FK_F24AD515D614C7E7 FOREIGN KEY (price_id) REFERENCES ecommerce_product_price (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_order DROP FOREIGN KEY FK_F24AD515A76ED395');
        $this->addSql('ALTER TABLE ecommerce_order DROP FOREIGN KEY FK_F24AD5154584665A');
        $this->addSql('ALTER TABLE ecommerce_order DROP FOREIGN KEY FK_F24AD515D614C7E7');
        $this->addSql('DROP TABLE ecommerce_order');
        $this->addSql('DROP TABLE ecommerce_payment_provider_config');
    }
}

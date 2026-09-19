<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscriptions and renewal orders, and rename the product state column to status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_subscription (provider_key VARCHAR(32) NOT NULL, provider_reference VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, current_period_end DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, initial_order_id INT NOT NULL, price_id INT DEFAULT NULL, INDEX IDX_D04333B48B8E8428 (created_at), INDEX IDX_D04333B443625D9F (updated_at), UNIQUE INDEX UNIQ_D04333B455263488 (initial_order_id), UNIQUE INDEX UNIQ_D04333B4D74DDA64773D51A1 (provider_key, provider_reference), INDEX IDX_D04333B4A76ED395 (user_id), INDEX IDX_D04333B4D614C7E7 (price_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_subscription ADD CONSTRAINT FK_D04333B4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_subscription ADD CONSTRAINT FK_D04333B455263488 FOREIGN KEY (initial_order_id) REFERENCES ecommerce_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_subscription ADD CONSTRAINT FK_D04333B4D614C7E7 FOREIGN KEY (price_id) REFERENCES ecommerce_product_price (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD subscription_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD CONSTRAINT FK_F24AD5159A1887DC FOREIGN KEY (subscription_id) REFERENCES ecommerce_subscription (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F24AD5159A1887DC ON ecommerce_order (subscription_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F24AD515D74DDA64773D51A1 ON ecommerce_order (provider_key, provider_reference)');
        $this->addSql('ALTER TABLE ecommerce_product CHANGE state status VARCHAR(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_product CHANGE status state VARCHAR(16) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_F24AD515D74DDA64773D51A1 ON ecommerce_order');
        $this->addSql('ALTER TABLE ecommerce_order DROP FOREIGN KEY FK_F24AD5159A1887DC');
        $this->addSql('DROP INDEX IDX_F24AD5159A1887DC ON ecommerce_order');
        $this->addSql('ALTER TABLE ecommerce_order DROP subscription_id');
        $this->addSql('ALTER TABLE ecommerce_subscription DROP FOREIGN KEY FK_D04333B4A76ED395');
        $this->addSql('ALTER TABLE ecommerce_subscription DROP FOREIGN KEY FK_D04333B455263488');
        $this->addSql('ALTER TABLE ecommerce_subscription DROP FOREIGN KEY FK_D04333B4D614C7E7');
        $this->addSql('DROP TABLE ecommerce_subscription');
    }
}

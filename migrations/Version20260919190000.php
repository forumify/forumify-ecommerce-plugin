<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment provider customers, and payment provider references on product tiers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_provider_customer (provider_key VARCHAR(32) NOT NULL, external_id VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_575F8FF88B8E8428 (created_at), INDEX IDX_575F8FF843625D9F (updated_at), UNIQUE INDEX UNIQ_575F8FF8A76ED395D74DDA64 (user_id, provider_key), INDEX IDX_575F8FF8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_provider_customer ADD CONSTRAINT FK_575F8FF8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_tier ADD provider_references JSON NOT NULL');
        $this->addSql("UPDATE ecommerce_product_tier SET provider_references = '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_product_tier DROP provider_references');
        $this->addSql('ALTER TABLE ecommerce_provider_customer DROP FOREIGN KEY FK_575F8FF8A76ED395');
        $this->addSql('DROP TABLE ecommerce_provider_customer');
    }
}

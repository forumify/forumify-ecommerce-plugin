<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919124156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the product catalog: categories, products, tiers and prices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_product (title VARCHAR(255) NOT NULL, product_key VARCHAR(128) NOT NULL, short_description VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, state VARCHAR(16) NOT NULL, id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, position INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_C2D8016D6ABFFFB6 (product_key), UNIQUE INDEX UNIQ_C2D8016D989D9B62 (slug), INDEX IDX_C2D8016D462CE4F5 (position), INDEX IDX_C2D8016D8B8E8428 (created_at), INDEX IDX_C2D8016D43625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_categories (product_id INT NOT NULL, product_category_id INT NOT NULL, INDEX IDX_BA66A57A4584665A (product_id), INDEX IDX_BA66A57ABE6903FD (product_category_id), PRIMARY KEY (product_id, product_category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_category (title VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, position INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D06C851989D9B62 (slug), INDEX IDX_8D06C851462CE4F5 (position), INDEX IDX_8D06C8518B8E8428 (created_at), INDEX IDX_8D06C85143625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_price (amount INT NOT NULL, type VARCHAR(16) NOT NULL, billing_interval VARCHAR(16) DEFAULT NULL, interval_count INT DEFAULT NULL, active TINYINT NOT NULL, locked TINYINT NOT NULL, provider_references JSON NOT NULL, currency VARCHAR(3) NOT NULL, id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, replaced_by_id INT DEFAULT NULL, tier_id INT NOT NULL, INDEX IDX_B8312829462CE4F5 (position), INDEX IDX_B83128298B8E8428 (created_at), INDEX IDX_B831282943625D9F (updated_at), INDEX IDX_B83128299AC69B54 (replaced_by_id), INDEX IDX_B8312829A354F9DC (tier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_tier (tier_key VARCHAR(64) NOT NULL, label VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, product_id INT NOT NULL, INDEX IDX_DAF3E24C462CE4F5 (position), INDEX IDX_DAF3E24C8B8E8428 (created_at), INDEX IDX_DAF3E24C43625D9F (updated_at), UNIQUE INDEX UNIQ_DAF3E24C4584665A6E82093C (product_id, tier_key), INDEX IDX_DAF3E24C4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_product_categories ADD CONSTRAINT FK_BA66A57A4584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_categories ADD CONSTRAINT FK_BA66A57ABE6903FD FOREIGN KEY (product_category_id) REFERENCES ecommerce_product_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_price ADD CONSTRAINT FK_B83128299AC69B54 FOREIGN KEY (replaced_by_id) REFERENCES ecommerce_product_price (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_product_price ADD CONSTRAINT FK_B8312829A354F9DC FOREIGN KEY (tier_id) REFERENCES ecommerce_product_tier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_tier ADD CONSTRAINT FK_DAF3E24C4584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_product_categories DROP FOREIGN KEY FK_BA66A57A4584665A');
        $this->addSql('ALTER TABLE ecommerce_product_categories DROP FOREIGN KEY FK_BA66A57ABE6903FD');
        $this->addSql('ALTER TABLE ecommerce_product_price DROP FOREIGN KEY FK_B83128299AC69B54');
        $this->addSql('ALTER TABLE ecommerce_product_price DROP FOREIGN KEY FK_B8312829A354F9DC');
        $this->addSql('ALTER TABLE ecommerce_product_tier DROP FOREIGN KEY FK_DAF3E24C4584665A');
        $this->addSql('DROP TABLE ecommerce_product');
        $this->addSql('DROP TABLE ecommerce_product_categories');
        $this->addSql('DROP TABLE ecommerce_product_category');
        $this->addSql('DROP TABLE ecommerce_product_price');
        $this->addSql('DROP TABLE ecommerce_product_tier');
    }
}

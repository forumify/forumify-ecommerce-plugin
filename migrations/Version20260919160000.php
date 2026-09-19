<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the storefront fields: product galleries, category descriptions and highlighted tiers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_product_image (image VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, product_id INT NOT NULL, INDEX IDX_B7C40EAF462CE4F5 (position), INDEX IDX_B7C40EAF8B8E8428 (created_at), INDEX IDX_B7C40EAF43625D9F (updated_at), INDEX IDX_B7C40EAF4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_product_image ADD CONSTRAINT FK_B7C40EAF4584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_category ADD description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE ecommerce_product_tier ADD highlighted TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_product_image DROP FOREIGN KEY FK_B7C40EAF4584665A');
        $this->addSql('DROP TABLE ecommerce_product_image');
        $this->addSql('ALTER TABLE ecommerce_product_category DROP description');
        $this->addSql('ALTER TABLE ecommerce_product_tier DROP highlighted');
    }
}

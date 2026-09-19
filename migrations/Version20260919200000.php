<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the paid order status, fulfillment types on products and orders, the past due grace period, and role and badge grants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_grant (pre_existing TINYINT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, role_id INT DEFAULT NULL, badge_id INT DEFAULT NULL, order_id INT NOT NULL, subscription_id INT DEFAULT NULL, INDEX IDX_CE6620C18B8E8428 (created_at), INDEX IDX_CE6620C143625D9F (updated_at), INDEX IDX_CE6620C1A76ED395 (user_id), INDEX IDX_CE6620C1D60322AC (role_id), INDEX IDX_CE6620C1F7A2C2FC (badge_id), INDEX IDX_CE6620C18D9F6D38 (order_id), INDEX IDX_CE6620C19A1887DC (subscription_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_granted_roles (product_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_C71C7FA64584665A (product_id), INDEX IDX_C71C7FA6D60322AC (role_id), PRIMARY KEY (product_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_product_granted_badges (product_id INT NOT NULL, badge_id INT NOT NULL, INDEX IDX_423220054584665A (product_id), INDEX IDX_42322005F7A2C2FC (badge_id), PRIMARY KEY (product_id, badge_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_grant ADD CONSTRAINT FK_CE6620C1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_grant ADD CONSTRAINT FK_CE6620C1D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_grant ADD CONSTRAINT FK_CE6620C1F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_grant ADD CONSTRAINT FK_CE6620C18D9F6D38 FOREIGN KEY (order_id) REFERENCES ecommerce_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_grant ADD CONSTRAINT FK_CE6620C19A1887DC FOREIGN KEY (subscription_id) REFERENCES ecommerce_subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_granted_roles ADD CONSTRAINT FK_C71C7FA64584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_granted_roles ADD CONSTRAINT FK_C71C7FA6D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_granted_badges ADD CONSTRAINT FK_423220054584665A FOREIGN KEY (product_id) REFERENCES ecommerce_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_product_granted_badges ADD CONSTRAINT FK_42322005F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_order ADD paid_at DATETIME DEFAULT NULL, ADD fulfillment_type VARCHAR(64) NOT NULL, ADD fulfillment_data JSON NOT NULL, ADD fulfillment_note LONGTEXT DEFAULT NULL, ADD completed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD CONSTRAINT FK_F24AD51585ECDE76 FOREIGN KEY (completed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F24AD51585ECDE76 ON ecommerce_order (completed_by_id)');
        $this->addSql('ALTER TABLE ecommerce_product ADD fulfillment_type VARCHAR(64) NOT NULL, ADD fulfillment_settings JSON NOT NULL, ADD fulfillment_role_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_product ADD CONSTRAINT FK_C2D8016DB9BCB4C0 FOREIGN KEY (fulfillment_role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C2D8016DB9BCB4C0 ON ecommerce_product (fulfillment_role_id)');
        $this->addSql('ALTER TABLE ecommerce_subscription ADD past_due_since DATETIME DEFAULT NULL');

        $this->addSql("UPDATE ecommerce_product SET fulfillment_type = 'instant', fulfillment_settings = '[]'");
        $this->addSql("UPDATE ecommerce_order SET fulfillment_type = 'instant', fulfillment_data = '[]'");
        $this->addSql("UPDATE ecommerce_order SET paid_at = completed_at WHERE status = 'completed'");
        $this->addSql("UPDATE ecommerce_subscription SET past_due_since = NOW() WHERE status = 'past_due'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_grant DROP FOREIGN KEY FK_CE6620C1A76ED395');
        $this->addSql('ALTER TABLE ecommerce_grant DROP FOREIGN KEY FK_CE6620C1D60322AC');
        $this->addSql('ALTER TABLE ecommerce_grant DROP FOREIGN KEY FK_CE6620C1F7A2C2FC');
        $this->addSql('ALTER TABLE ecommerce_grant DROP FOREIGN KEY FK_CE6620C18D9F6D38');
        $this->addSql('ALTER TABLE ecommerce_grant DROP FOREIGN KEY FK_CE6620C19A1887DC');
        $this->addSql('ALTER TABLE ecommerce_product_granted_roles DROP FOREIGN KEY FK_C71C7FA64584665A');
        $this->addSql('ALTER TABLE ecommerce_product_granted_roles DROP FOREIGN KEY FK_C71C7FA6D60322AC');
        $this->addSql('ALTER TABLE ecommerce_product_granted_badges DROP FOREIGN KEY FK_423220054584665A');
        $this->addSql('ALTER TABLE ecommerce_product_granted_badges DROP FOREIGN KEY FK_42322005F7A2C2FC');
        $this->addSql('DROP TABLE ecommerce_grant');
        $this->addSql('DROP TABLE ecommerce_product_granted_roles');
        $this->addSql('DROP TABLE ecommerce_product_granted_badges');
        $this->addSql('ALTER TABLE ecommerce_order DROP FOREIGN KEY FK_F24AD51585ECDE76');
        $this->addSql('DROP INDEX IDX_F24AD51585ECDE76 ON ecommerce_order');
        $this->addSql('ALTER TABLE ecommerce_order DROP paid_at, DROP fulfillment_type, DROP fulfillment_data, DROP fulfillment_note, DROP completed_by_id');
        $this->addSql('ALTER TABLE ecommerce_product DROP FOREIGN KEY FK_C2D8016DB9BCB4C0');
        $this->addSql('DROP INDEX IDX_C2D8016DB9BCB4C0 ON ecommerce_product');
        $this->addSql('ALTER TABLE ecommerce_product DROP fulfillment_type, DROP fulfillment_settings, DROP fulfillment_role_id');
        $this->addSql('ALTER TABLE ecommerce_subscription DROP past_due_since');
    }
}

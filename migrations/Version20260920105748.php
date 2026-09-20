<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920105748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer self-service: refund requests and their refunded orders, and cancellation requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_cancellation_request (completed_at DATETIME DEFAULT NULL, reminded_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, subscription_id INT NOT NULL, completed_by_id INT DEFAULT NULL, INDEX IDX_82278CBD8B8E8428 (created_at), INDEX IDX_82278CBD43625D9F (updated_at), UNIQUE INDEX UNIQ_82278CBD9A1887DC (subscription_id), INDEX IDX_82278CBD85ECDE76 (completed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ecommerce_refund_request (reason LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, handled_at DATETIME DEFAULT NULL, note LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, order_id INT NOT NULL, user_id INT DEFAULT NULL, handled_by_id INT DEFAULT NULL, INDEX IDX_BA934F698B8E8428 (created_at), INDEX IDX_BA934F6943625D9F (updated_at), UNIQUE INDEX UNIQ_BA934F698D9F6D38 (order_id), INDEX IDX_BA934F69A76ED395 (user_id), INDEX IDX_BA934F69FE65AF40 (handled_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ecommerce_cancellation_request ADD CONSTRAINT FK_82278CBD9A1887DC FOREIGN KEY (subscription_id) REFERENCES ecommerce_subscription (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_cancellation_request ADD CONSTRAINT FK_82278CBD85ECDE76 FOREIGN KEY (completed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_refund_request ADD CONSTRAINT FK_BA934F698D9F6D38 FOREIGN KEY (order_id) REFERENCES ecommerce_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_refund_request ADD CONSTRAINT FK_BA934F69A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_refund_request ADD CONSTRAINT FK_BA934F69FE65AF40 FOREIGN KEY (handled_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_order ADD refunded_at DATETIME DEFAULT NULL, ADD refund_period_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_product ADD refund_period_days INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_cancellation_request DROP FOREIGN KEY FK_82278CBD9A1887DC');
        $this->addSql('ALTER TABLE ecommerce_cancellation_request DROP FOREIGN KEY FK_82278CBD85ECDE76');
        $this->addSql('ALTER TABLE ecommerce_refund_request DROP FOREIGN KEY FK_BA934F698D9F6D38');
        $this->addSql('ALTER TABLE ecommerce_refund_request DROP FOREIGN KEY FK_BA934F69A76ED395');
        $this->addSql('ALTER TABLE ecommerce_refund_request DROP FOREIGN KEY FK_BA934F69FE65AF40');
        $this->addSql('DROP TABLE ecommerce_cancellation_request');
        $this->addSql('DROP TABLE ecommerce_refund_request');
        $this->addSql('ALTER TABLE ecommerce_order DROP refunded_at, DROP refund_period_days');
        $this->addSql('ALTER TABLE ecommerce_product DROP refund_period_days');
    }
}

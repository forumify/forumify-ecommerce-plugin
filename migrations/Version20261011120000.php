<?php

declare(strict_types=1);

namespace ForumifyEcommercePluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link every initial order to the subscription it started, like its renewals already are.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE ecommerce_order o INNER JOIN ecommerce_subscription s ON s.initial_order_id = o.id'
                . ' SET o.subscription_id = s.id',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE ecommerce_order o INNER JOIN ecommerce_subscription s ON s.initial_order_id = o.id'
                . ' SET o.subscription_id = NULL',
        );
    }
}

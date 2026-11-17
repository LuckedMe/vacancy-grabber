<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram allow notifications flag to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            ADD `telegram_notifications_enabled` TINYINT DEFAULT 0 NOT NULL AFTER `telegram_phone`
        '
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            DROP `telegram_notifications_enabled`
        '
        );
    }
}

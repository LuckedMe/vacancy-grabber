<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make telegram username of users unique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        CREATE UNIQUE INDEX `uniq_telegram_username` ON `users` (`telegram_username`)
        '
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        DROP INDEX `uniq_telegram_username` ON `users`
        '
        );
    }
}

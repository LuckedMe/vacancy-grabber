<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram chat id to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(/** @lang MySQL */ '
        ALTER TABLE `users`
            ADD `telegram_chat_id` VARCHAR(255) DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(/** @lang MySQL */ '
        ALTER TABLE `users`
            DROP `telegram_chat_id`
        ');
    }
}

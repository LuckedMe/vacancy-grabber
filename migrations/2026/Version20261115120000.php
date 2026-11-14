<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram username to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            ADD `telegram_username` VARCHAR(255) DEFAULT NULL AFTER `last_name`
        '
        );

        // a username that was saved in the chat id field is moved to its own field
        $this->addSql(
        /** @lang MySQL */ '
        UPDATE `users`
        SET
            `telegram_username` = `telegram_chat_id`,
            `telegram_chat_id`  = NULL
        WHERE `telegram_chat_id` IS NOT NULL
          AND `telegram_chat_id` NOT REGEXP \'^-?[0-9]+$\'
        '
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            DROP `telegram_username`
        '
        );
    }
}

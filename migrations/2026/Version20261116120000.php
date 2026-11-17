<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add telegram phone to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            ADD `telegram_phone` VARCHAR(255) DEFAULT NULL AFTER `telegram_chat_id`
        '
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
        /** @lang MySQL */ '
        ALTER TABLE `users`
            DROP `telegram_phone`
        '
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Telegram;

use RuntimeException;

/**
 * Port for sending Telegram messages, implemented in Infrastructure.
 */
interface TelegramSender
{
    /**
     * Whether the bot is configured (the API token is set).
     */
    public function isEnabled(): bool;

    /**
     * @throws RuntimeException when Telegram rejects the message or can not be reached
     */
    public function send(string $chatId, string $text): void;
}

<?php

declare(strict_types=1);

namespace App\Domain\User;

use RuntimeException;

/**
 * Port: finds the Telegram chat ID of a user by the Telegram username.
 */
interface TelegramChatResolver
{
    /**
     * @param string $username without "@", lower case (see User::resolveTelegramUsername())
     *
     * @return string|null null when the user has not written to the bot yet or Telegram notifications are disabled
     *
     * @throws RuntimeException when Telegram could not be asked
     */
    public function resolveChatId(string $username): ?string;
}

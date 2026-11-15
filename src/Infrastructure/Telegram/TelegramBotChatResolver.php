<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use App\Domain\User\TelegramChatResolver;

use function is_array;
use function is_string;
use function strtolower;

/**
 * Looks the username up in the private chats of the messages sent to the bot (getUpdates).
 */
final readonly class TelegramBotChatResolver implements TelegramChatResolver
{
    public function __construct(
        private TelegramNotifier $telegramNotifier,
    ) {
    }

    public function resolveChatId(string $username): ?string
    {
        if (!$this->telegramNotifier->isEnabled()) {
            return null;
        }

        $chatId = null;

        // the updates are ordered from old to new: the last message of the user wins
        foreach ($this->telegramNotifier->getUpdates() as $update) {
            $chat = $update['message']['chat'] ?? null;

            if (is_array($chat)
                && ($chat['type'] ?? null) === 'private'
                && is_string($chat['username'] ?? null)
                && strtolower($chat['username']) === strtolower($username)
            ) {
                $chatId = (string) $chat['id'];
            }
        }

        return $chatId;
    }
}

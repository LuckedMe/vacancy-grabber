<?php

declare(strict_types=1);

namespace App\Application\Telegram\Message\Command;

use App\Application\Shared\Bus\StandardPriorityAsyncMessageInterface;
use App\Application\Telegram\MessageHandler\Command\SendTelegramNotificationHandler;

/** @see SendTelegramNotificationHandler */
final readonly class SendTelegramNotificationAsyncCommand implements StandardPriorityAsyncMessageInterface
{
    public function __construct(
        public string $chatId,
        public string $message,
    ) {
    }
}

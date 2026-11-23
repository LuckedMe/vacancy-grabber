<?php

declare(strict_types=1);

namespace App\Application\Telegram\MessageHandler\Command;

use App\Application\Shared\Bus\CommandHandlerInterface;
use App\Application\Telegram\Message\Command\SendTelegramNotificationAsyncCommand;
use App\Infrastructure\Telegram\TelegramNotifier;

final readonly class SendTelegramNotificationHandler implements CommandHandlerInterface
{
    public function __construct(
        private TelegramNotifier $telegramNotifier,
    ) {
    }

    /**
     * A TelegramException is not caught on purpose: Messenger retries the message and then moves it to the failed transport.
     */
    public function __invoke(SendTelegramNotificationAsyncCommand $command): void
    {
        if (!$this->telegramNotifier->isEnabled()) {
            return;
        }

        $this->telegramNotifier->send($command->chatId, $command->message);
    }
}

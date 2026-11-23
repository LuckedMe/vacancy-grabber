<?php

declare(strict_types=1);

namespace App\Application\Telegram\MessageHandler\Command;

use App\Application\Shared\Bus\CommandHandlerInterface;
use App\Application\Telegram\Message\Command\SendTelegramNotificationAsyncCommand;
use App\Application\Telegram\TelegramSender;

final readonly class SendTelegramNotificationHandler implements CommandHandlerInterface
{
    public function __construct(
        private TelegramSender $telegramSender,
    ) {
    }

    /**
     * A send error is not caught on purpose: Messenger retries the message and then moves it to the failed transport.
     */
    public function __invoke(SendTelegramNotificationAsyncCommand $command): void
    {
        if (!$this->telegramSender->isEnabled()) {
            return;
        }

        $this->telegramSender->send($command->chatId, $command->message);
    }
}

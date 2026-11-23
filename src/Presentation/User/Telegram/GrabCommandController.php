<?php

declare(strict_types=1);

namespace App\Presentation\User\Telegram;

use App\Application\Shared\Bus\MessageBus;
use App\Application\Source\Message\Command\GrabSourceAsyncCommand;
use App\Domain\Source\Repository\SourceRepository;
use App\Domain\User\Repository\UserRepository;
use Luzrain\TelegramBotApi\BotApi;
use Luzrain\TelegramBotApi\Event;
use Luzrain\TelegramBotApi\EventCallbackReturn;
use Luzrain\TelegramBotApi\Exception\TelegramApiException;
use Luzrain\TelegramBotApi\Exception\TelegramApiServerException;
use Luzrain\TelegramBotApi\Method;
use Luzrain\TelegramBotApi\Type;
use Luzrain\TelegramBotBundle\Attribute\OnCommand;
use Luzrain\TelegramBotBundle\Attribute\OnEvent;
use Luzrain\TelegramBotBundle\TelegramCommand;
use Throwable;

use function sprintf;

/**
 * "/grab" (the "Get Updates" button): grabs vacancies from the active sources of the user linked to the chat.
 * Available to registered users only.
 */
final class GrabCommandController extends TelegramCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SourceRepository $sourceRepository,
        private readonly MessageBus $messageBus,
        private readonly BotApi $botApi,
    ) {
    }

    #[OnCommand(command: '/grab', description: 'Get Updates', publish: true)]
    public function __invoke(Type\Message $message): Method
    {
        return $this->grab($message);
    }

    #[OnEvent(Event\Message::class)]
    public function pressed(Type\Message $message): Method|EventCallbackReturn
    {
        if ($message->text !== StartCommandController::BUTTON_GRAB) {
            return $this->continue();
        }

        return $this->grab($message);
    }

    private function grab(Type\Message $message): Method
    {
        // the pressed button text / typed "/grab" is only a command to the bot, so it is removed from the chat
        $this->deleteMessage($message);

        $user = $this->userRepository->findOneByTelegramChatId((string) $message->chat->id);

        if ($user === null) {
            return $this->reply('This chat is not linked to an account yet. Send /start first.');
        }

        $queued = $failed = 0;

        foreach ($this->sourceRepository->findAllSortByName($user->getId()) as $source) {
            if (!$source->isActive() || $source->getUrl() === null) {
                continue;
            }

            try {
                // async: the result is not returned, the grab runs in a worker
                $this->messageBus->handleCommand(
                    new GrabSourceAsyncCommand(userId: $user->getId(), sourceId: $source->getId())
                );

                $queued++;
            } catch (Throwable) {
                $failed++;
            }
        }

        if ($queued === 0 && $failed === 0) {
            return $this->reply('You have no active sources to grab.');
        }

        return $this->reply(
            $failed > 0
                ? sprintf(
                    '%s Grabbing started for %d source(s), %d could not be queued. We will report when the grab process is finished.',
                    StartCommandController::ICO_GRAB,
                    $queued,
                    $failed,
                )
                : StartCommandController::ICO_GRAB . ' We will report when the grab process is finished.',
        );
    }

    private function deleteMessage(Type\Message $message): void
    {
        try {
            $this->botApi->call(new Method\DeleteMessage(chatId: $message->chat->id, messageId: $message->messageId));
        } catch (TelegramApiException|TelegramApiServerException) {
            // the message is already deleted, too old (48 hours) or the bot has no right to delete it
        }
    }
}

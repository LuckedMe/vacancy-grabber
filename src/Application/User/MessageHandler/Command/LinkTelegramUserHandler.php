<?php

declare(strict_types=1);

namespace App\Application\User\MessageHandler\Command;

use App\Application\Shared\Bus\CommandHandlerInterface;
use App\Application\User\Message\Command\LinkTelegramUserCommand;
use App\Domain\User\Repository\UserRepository;
use App\Domain\User\TelegramChatResolver;

final readonly class LinkTelegramUserHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private TelegramChatResolver $telegramChatResolver,
    ) {
    }

    public function __invoke(LinkTelegramUserCommand $command): void
    {
        if (!$user = $this->userRepository->findOneById($command->userId)) {
            return;
        }

        // nothing to do: the chat is already known or there is no username to look for
        if ($user->getTelegramChatId() || !($username = $user->resolveTelegramUsername())) {
            return;
        }

        // null: the user has not pressed Start in the bot yet; app:telegram:link-users tries again later
        if (!$chatId = $this->telegramChatResolver->resolveChatId($username)) {
            return;
        }

        $user->changeTelegramChatId($chatId);
        $this->userRepository->save($user);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\User\Message\Command;

use App\Application\Shared\Bus\StandardPriorityAsyncMessageInterface;
use App\Application\User\MessageHandler\Command\LinkTelegramUserHandler;
use Symfony\Component\Uid\Uuid;

/** @see LinkTelegramUserHandler */
final readonly class LinkTelegramUserCommand implements StandardPriorityAsyncMessageInterface
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}

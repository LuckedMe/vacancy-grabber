<?php

declare(strict_types=1);

namespace App\Application\Source\Message\Command;

use App\Application\Shared\Bus\StandardPriorityAsyncMessageInterface;
use App\Application\Source\MessageHandler\Command\GrabSourceHandler;
use Symfony\Component\Uid\Uuid;

/** @see GrabSourceHandler */
final readonly class GrabSourceAsyncCommand implements StandardPriorityAsyncMessageInterface
{
    public function __construct(
        public Uuid $userId,
        public Uuid $sourceId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Shared\Bus;

/**
 * Port for dispatching commands, queries and events, implemented in Infrastructure.
 */
interface MessageBus
{
    /**
     * @param int $delay The delay in milliseconds
     */
    public function handleCommand(object $message, bool $dispatchAfterCurrentBus = false, int $delay = 0): mixed;

    public function handleQuery(object $message): mixed;

    /**
     * @param int $delay The delay in milliseconds
     */
    public function handleEvent(object $message, bool $dispatchAfterCurrentBus = false, int $delay = 0): mixed;
}

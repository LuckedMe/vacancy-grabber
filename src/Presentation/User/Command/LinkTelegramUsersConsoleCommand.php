<?php

declare(strict_types=1);

namespace App\Presentation\User\Command;

use App\Domain\User\Repository\UserRepository;
use App\Infrastructure\Telegram\TelegramException;
use App\Infrastructure\Telegram\TelegramNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;

#[AsCommand(
    name: 'app:telegram:link-users',
    description: 'Fill in the Telegram chat ID of users who have a Telegram username, using the messages sent to the bot',
)]
final class LinkTelegramUsersConsoleCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TelegramNotifier $telegramNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->telegramNotifier->isEnabled()) {
            $io->error('TELEGRAM_API_TOKEN is not set.');

            return Command::FAILURE;
        }

        $pending = [];

        foreach ($this->userRepository->findAllAwaitingTelegramLink() as $user) {
            if (($username = $user->resolveTelegramUsername()) !== null) {
                $pending[$username][] = $user;
            }
        }

        if (!$pending) {
            $io->success('No users are waiting for a Telegram link.');

            return Command::SUCCESS;
        }

        try {
            $updates = $this->telegramNotifier->getUpdates();
        } catch (TelegramException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // username => chat id, from private chats only
        $chats = [];

        foreach ($updates as $update) {
            $chat = $update['message']['chat'] ?? null;

            if (is_array($chat) && ($chat['type'] ?? null) === 'private' && is_string($chat['username'] ?? null)) {
                $chats[strtolower($chat['username'])] = (string) $chat['id'];
            }
        }

        $linked  = 0;
        $waiting = 0;

        foreach ($pending as $username => $users) {
            if (!isset($chats[$username])) {
                $io->writeln(sprintf(
                    '<comment>@%s: no message from this user, ask them to press Start in the bot</comment>',
                    $username
                ));

                $waiting += count($users);

                continue;
            }

            foreach ($users as $user) {
                $user->changeTelegramChatId($chats[$username]);
                $this->userRepository->save($user);
                $linked++;
                $io->writeln(sprintf('@%s linked to %s (chat %s)', $username, $user, $chats[$username]));
            }
        }

        $io->{$waiting ? 'warning' : 'success'}(sprintf('Linked: %d, still waiting: %d', $linked, $waiting));

        return Command::SUCCESS;
    }
}

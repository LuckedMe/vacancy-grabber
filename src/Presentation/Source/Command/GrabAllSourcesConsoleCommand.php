<?php

declare(strict_types=1);

namespace App\Presentation\Source\Command;

use App\Application\Source\DTO\GrabSourceResultDTO;
use App\Application\Source\Message\Command\GrabSourceCommand;
use App\Domain\Source\Repository\SourceRepository;
use App\Domain\User\Repository\UserRepository;
use App\Infrastructure\Messenger\MessageBusHandler;
use App\Infrastructure\Telegram\TelegramNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function rtrim;
use function sprintf;

#[AsCommand(name: 'app:source:grab-all', description: 'Grab vacancies from all active sources stored in the database')]
final class GrabAllSourcesConsoleCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SourceRepository $sourceRepository,
        private readonly MessageBusHandler $messageBusHandler,
        private readonly TelegramNotifier $telegramNotifier,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $baseUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $updated = $failed = 0;
        $notify  = [];

        foreach ($this->sourceRepository->findAllReadyToGrab() as $source) {
            $userId = $source->getUser()->getId()->toString();
            $owner  = $source->getUser();

            $io->write(sprintf('<info>%s</info> (%s) ... ', $source->getName(), $owner));

            try {
                /** @var GrabSourceResultDTO $result */
                $result = $this->messageBusHandler->handleCommand(
                    new GrabSourceCommand(userId: $owner->getId(), sourceId: $source->getId())
                );

                // update user notification storage
                if ($result->vacanciesCreated > 0 || $result->vacanciesUpdated > 0) {
                    // user notification storage
                    if (!array_key_exists($userId, $notify)) {
                        $notify[$userId] = ['created' => $result->vacanciesCreated, 'updated' => $result->vacanciesUpdated];
                    } else {
                        $notify[$userId]['created'] += $result->vacanciesCreated;
                        $notify[$userId]['updated'] += $result->vacanciesUpdated;
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                $io->writeln(sprintf('<error>failed: %s</error>', $e->getMessage()));

                continue;
            }

            $created += $result->vacanciesCreated;
            $updated += $result->vacanciesUpdated;
            $io->writeln(sprintf('created: %d, updated: %d', $result->vacanciesCreated, $result->vacanciesUpdated));
        }

        $io->newLine();
        $io->{$failed ? 'warning' : 'success'}(sprintf(
            'Created: %d, updated: %d, failed: %d',
            $created,
            $updated,
            $failed,
        ));

        // send notifications
        $this->notifyUsers($io, $notify);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function notifyUsers(SymfonyStyle $io, array $notify): void
    {
        if (!$this->telegramNotifier->isEnabled()) {
            return;
        }

        $ids   = array_map(static fn (string $uuid) => Uuid::fromString($uuid), array_keys($notify));
        $users = $ids ? $this->userRepository->findAllByIds($ids) : [];
        $link  = rtrim($this->baseUrl, '/') . '/vacancies?viewed=0';

        foreach ($users as $user) {
            $chatId = $user->getTelegramChatId();
            $stat   = $notify[$user->getId()->toString()] ?? null;

            if ($chatId === null || $stat === null) {
                continue;
            }

            $message = $this->translator->trans(
                id: 'SOURCE.GRAB_REPORT.TELEGRAM',
                parameters: [
                    '%created%' => $stat['created'],
                    '%updated%' => $stat['updated'],
                    '%link%'    => $link,
                ],
                domain: 'messages',
            );

            try {
                $this->telegramNotifier->send($chatId, $message);
                $io->writeln(sprintf('Telegram notification sent to %s', $user));
            } catch (Throwable $e) {
                $io->writeln(sprintf('<error>Telegram notification to %s failed: %s</error>', $user, $e->getMessage()));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\User\Telegram;

use App\Domain\User\Repository\UserRepository;
use Luzrain\TelegramBotApi\Event;
use Luzrain\TelegramBotApi\EventCallbackReturn;
use Luzrain\TelegramBotApi\Method;
use Luzrain\TelegramBotApi\Type;
use Luzrain\TelegramBotBundle\Attribute\OnCommand;
use Luzrain\TelegramBotBundle\Attribute\OnEvent;
use Luzrain\TelegramBotBundle\TelegramCommand;

use function sprintf;

/**
 * "/contact" asks the user to share their phone number with the "Share my phone" button
 * and saves the number on the user linked to the chat (see StartCommandController).
 */
final class ContactCommandController extends TelegramCommand
{
    public const string BUTTON_SHARE     = 'Share my phone';
    public const string BUTTON_SHARE_NEW = 'Update my phone';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly StartCommandController $startCommand,
    ) {
    }

    #[OnCommand(command: '/contact', description: 'Share your phone number', publish: true)]
    public function request(Type\Message $message): Method
    {
        if ($message->chat->type !== 'private') {
            return $this->reply('Please write to me in a private chat.');
        }

        $user = $this->userRepository->findOneByTelegramChatId((string) $message->chat->id);

        if ($user === null) {
            return $this->reply('This chat is not linked to an account yet. Send /start first.');
        }

        $phone = $user->getTelegramPhone();

        return $this->reply(
            $phone === null
                ? 'Press the button below to share your phone number.'
                : sprintf('Your phone number is %s. To change it, press the button below and share the new one.', $phone),
            replyMarkup: new Type\ReplyKeyboardMarkup(
                keyboard: [[new Type\KeyboardButton(
                    text: $phone === null ? self::BUTTON_SHARE : self::BUTTON_SHARE_NEW,
                    requestContact: true,
                )]],
                resizeKeyboard: true,
                oneTimeKeyboard: true,
            ),
        );
    }

    #[OnEvent(Event\Message::class)]
    public function receive(Type\Message $message): Method|EventCallbackReturn
    {
        $contact = $message->contact;

        if ($contact === null) {
            return $this->continue();
        }

        // only the user's own contact is accepted, not a forwarded one
        if ($contact->userId === null || $contact->userId !== $message->from?->id) {
            return $this->reply('Please share your own phone number with the button.', replyMarkup: new Type\ReplyKeyboardRemove());
        }

        $user = $this->userRepository->findOneByTelegramChatId((string) $message->chat->id);

        if ($user === null) {
            return $this->reply('This chat is not linked to an account yet. Send /start first.', replyMarkup: new Type\ReplyKeyboardRemove());
        }

        $previous = $user->getTelegramPhone();

        $user->changeTelegramPhone($contact->phoneNumber);
        $this->userRepository->save($user);

        $text = match (true) {
            $previous === null                      => sprintf('Thanks! Your phone number %s is saved.', $contact->phoneNumber),
            $previous === $user->getTelegramPhone() => sprintf('Your phone number %s is already saved, nothing changed.', $contact->phoneNumber),
            default                                 => sprintf('Done! Your phone number is updated: %s (was %s).', $contact->phoneNumber, $previous),
        };

        return $this->reply(
            $text . "\n\nChoose an action using the buttons below the input field:",
            // the "Share my phone" keyboard is replaced by the /start menu
            replyMarkup: $this->startCommand->buildKeyboard($user),
        );
    }
}

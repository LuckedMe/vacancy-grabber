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

        if ($this->userRepository->findOneByTelegramChatId((string) $message->chat->id) === null) {
            return $this->reply('This chat is not linked to an account yet. Send /start first.');
        }

        return $this->reply(
            'Press the button below to share your phone number.',
            replyMarkup: new Type\ReplyKeyboardMarkup(
                keyboard: [[new Type\KeyboardButton(text: 'Share my phone', requestContact: true)]],
                resizeKeyboard: true,
                oneTimeKeyboard: true,
            ),
        );
    }

    #[OnEvent(Event\TextMessage::class)]
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

        $user->changeTelegramPhone($contact->phoneNumber);
        $this->userRepository->save($user);

        return $this->reply(
            sprintf("Thanks! Your phone number %s is saved.\n\nChoose an action using the buttons below the input field:", $contact->phoneNumber),
            // the "Share my phone" keyboard is replaced by the /start menu
            replyMarkup: $this->startCommand->buildKeyboard($user),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\User\Telegram;

use App\Domain\User\Entity\User;
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

use function in_array;
use function sprintf;
use function strtolower;

/**
 * "/start": links the chat to the user whose Telegram username matches the sender, so notifications reach them
 * without app:telegram:link-users.
 */
final class StartCommandController extends TelegramCommand
{
    public const string ICO_START    = '▶️';
    public const string ICO_STATUS   = '⚙️';
    public const string ICO_CANCEL   = '🚫';
    public const string ICO_ENABLED  = '🟢';
    public const string ICO_DISABLED = '🔴';
    public const string ICO_GRAB     = '🔍';

    private const string BUTTON_START                 = self::ICO_START . ' Start';
    private const string BUTTON_STATUS                = self::ICO_STATUS . ' Status';
    private const string BUTTON_CANCEL                = self::ICO_CANCEL . ' Cancel';
    private const string BUTTON_ENABLE_NOTIFICATIONS  = self::ICO_DISABLED . ' Enable notifications';
    private const string BUTTON_DISABLE_NOTIFICATIONS = self::ICO_ENABLED . ' Disable notifications';
    public const string BUTTON_GRAB                   = self::ICO_GRAB . ' Get Updates';

    private const array BUTTONS = [
        self::BUTTON_START,
        self::BUTTON_STATUS,
        self::BUTTON_ENABLE_NOTIFICATIONS,
        self::BUTTON_DISABLE_NOTIFICATIONS,
        self::BUTTON_CANCEL,
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BotApi $botApi,
    ) {
    }

    #[OnCommand(command: '/start', description: 'Start communications with the Vacancy Grabber bot', publish: true)]
    public function __invoke(Type\Message $message): Method
    {
        $chatId   = (string) $message->chat->id;
        $username = $message->from?->username;

        // link telegram user (a Telegram user may have no public username)
        if ($username !== null) {
            foreach ($this->userRepository->findAllAwaitingTelegramLink() as $user) {
                if ($user->resolveTelegramUsername() === strtolower($username)) {
                    $user->changeTelegramChatId($chatId);
                    $this->userRepository->save($user);
                    $this->userRepository->refresh($user);
                }
            }
        }

        return $this->reply(
            'Choose an action using the buttons below the input field:',
            replyMarkup: $this->buildKeyboard($this->userRepository->findOneByTelegramChatId($chatId)),
        );
    }

    /**
     * The keyboard under the input field; the notifications button depends on the user's current setting.
     * Public because other bot controllers show the same menu (e.g. after the phone number is shared).
     */
    public function buildKeyboard(?User $user): Type\ReplyKeyboardMarkup
    {
        $buttonStart   = new Type\KeyboardButton(text: self::BUTTON_START);
        $buttonStatus  = new Type\KeyboardButton(text: self::BUTTON_STATUS);
        $buttonCancel  = new Type\KeyboardButton(text: self::BUTTON_CANCEL);
        $buttonEnable  = new Type\KeyboardButton(text: self::BUTTON_ENABLE_NOTIFICATIONS);
        $buttonDisable = new Type\KeyboardButton(text: self::BUTTON_DISABLE_NOTIFICATIONS);
        $buttonGrab    = new Type\KeyboardButton(text: self::BUTTON_GRAB);

        $keyboard   = [];
        $keyboard[] = [$buttonGrab, $buttonStatus];

        if ($user !== null) {
            // a request_contact button: one tap shares the phone number (or a new one), see ContactCommandController
            $buttonContactShare = new Type\KeyboardButton(
                text: $user->getTelegramPhone() === null
                          ? ContactCommandController::BUTTON_SHARE
                          : ContactCommandController::BUTTON_SHARE_NEW,
                requestContact: true,
            );

            $keyboard[] = $user->isTelegramNotificationsEnabled()
                ? [$buttonDisable, $buttonContactShare]
                : [$buttonEnable, $buttonContactShare];
        }

        $keyboard[] = [$buttonCancel];

        return new Type\ReplyKeyboardMarkup(
            keyboard: $keyboard,
            // false: the keyboard can be hidden and opened again with the keyboard icon
            isPersistent: false,
            // fit the keyboard height to the number of rows
            resizeKeyboard: true,
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

    #[OnEvent(Event\Message::class)]
    public function pressed(Type\Message $message): Method|EventCallbackReturn
    {
        if ($message->text === null || !in_array($message->text, self::BUTTONS, true)) {
            return $this->continue();
        }

        // the text of a pressed button is only a command to the bot, so it is removed from the chat
        $this->deleteMessage($message);

        $chatId = (string) $message->chat->id;
        $user   = $this->userRepository->findOneByTelegramChatId($chatId);

        if ($message->text === self::BUTTON_ENABLE_NOTIFICATIONS
            || $message->text === self::BUTTON_DISABLE_NOTIFICATIONS
        ) {
            if ($user === null) {
                return $this->reply('This chat is not linked to an account yet. Send /start first.');
            }

            $enabled = $message->text === self::BUTTON_ENABLE_NOTIFICATIONS;

            $user->changeTelegramNotificationsEnabled($enabled);
            $this->userRepository->save($user);
            $this->userRepository->refresh($user);

            // the keyboard is sent again so that it shows the opposite button
            return $this->reply(
                $enabled
                    ? self::ICO_ENABLED . ' Notifications Enabled.'
                    : self::ICO_DISABLED . ' Notifications Disabled.',
                replyMarkup: $this->buildKeyboard($user),
            );
        }

        // the same as the /start command
        if ($message->text === self::BUTTON_START) {
            return $this->__invoke($message);
        }

        if ($message->text === self::BUTTON_STATUS) {
            if ($user === null) {
                return $this->reply('This chat is not linked to an account yet. Send /start first.');
            }

            return $this->reply(
                sprintf(
                    "Account: %s\nNotifications about new vacancies: %s\nPhone: %s",
                    $user,
                    $user->isTelegramNotificationsEnabled() ? self::ICO_ENABLED . ' enabled' : self::ICO_DISABLED . ' disabled',
                    $user->getTelegramPhone() ?? 'not shared (send /contact)',
                ),
                replyMarkup: $this->buildKeyboard($user),
            );
        }

        if ($message->text === self::BUTTON_CANCEL) {
            // the menu shrinks to the "Start" button, which opens it again
            return $this->reply(
                'Menu closed. Press "Start" to open it again.',
                replyMarkup: new Type\ReplyKeyboardMarkup(
                    keyboard: [[new Type\KeyboardButton(text: self::BUTTON_START)]],
                    resizeKeyboard: true,
                ),
            );
        }

        return $this->continue();
    }
}

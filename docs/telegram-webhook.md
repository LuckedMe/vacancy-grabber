# Telegram webhook (luzrain/telegram-bot-bundle)

Notes on how the bot receives messages. Outgoing notifications are described in `telegram-notifications.md`.

## Bundle overview

- Package: `luzrain/telegram-bot-bundle` ^2.3 (Symfony bundle over `luzrain/telegram-bot-api`), needs PHP >= 8.2.
- Needs a PSR-18 HTTP client and PSR-17 factories. `nyholm/psr7` is installed; its factory is registered for
  autowiring in `config/packages/nyholm_psr7.yaml`.
- Registered in `config/bundles.php`; config in `config/packages/telegram_bot.yaml`.
- Updates can be received by webhook (recommended, production) or by a polling daemon (dev).
- Handlers are controllers with `#[OnCommand('/start')]` (extend `TelegramCommand`) or `#[OnEvent(Event\Message::class)]`.
- Commands: `telegram:webhook:update|info|delete`, `telegram:polling:start`, `telegram:button:update|delete`.
- Full option list: `bin/console config:dump-reference telegram_bot`.

## What was added

| File | Purpose |
|------|---------|
| `config/packages/telegram_bot.yaml` | token + `webhook.url` + `webhook.secret_token` from env |
| `config/routes/telegram_bot.yaml` | `POST /telegram/webhook` -> `telegram_bot.webhook_controller` |
| `config/packages/security.yaml` | `^/telegram/webhook` is `PUBLIC_ACCESS` (Telegram has no session) |
| `.env`, `.env.prod.dist` | `TELEGRAM_WEBHOOK_URL`, `TELEGRAM_WEBHOOK_SECRET` |

The bundle and `TelegramNotifier` share one token, `TELEGRAM_API_TOKEN` (renamed from `TELEGRAM_BOT_TOKEN`).

## Setup

1. Pick a secret: `openssl rand -hex 32` -> `TELEGRAM_WEBHOOK_SECRET` (only `A-Z a-z 0-9 _ -`, 1-256 chars).
2. Set `TELEGRAM_WEBHOOK_URL=https://<your-domain>/telegram/webhook` (must be public HTTPS, port 443/80/88/8443,
   valid certificate).
3. Register it in Telegram:
   ```bash
   bin/console telegram:webhook:update
   bin/console telegram:webhook:info     # check url / last error
   ```
   Run `telegram:webhook:update` again after every change of the webhook or `allowed_updates` config
   (put it in the deploy script).
4. Remove: `bin/console telegram:webhook:delete` (also drops pending updates).

Webhook and `getUpdates` are mutually exclusive: while a webhook is set, `app:telegram:link-users` (which uses
`getUpdates`) gets no data; users link themselves with `/start` instead.

## Local development

Telegram cannot reach `localhost`: use a tunnel (`ngrok http 80`) and pass `--url`, or use polling instead:

```bash
bin/console telegram:webhook:update --url=https://<tunnel>/telegram/webhook
bin/console telegram:polling:start    # alternative, no public URL needed
```

## /start handler

`src/Presentation/User/Telegram/StartCommandController.php` (`#[OnCommand('/start')]`, autowired like any class in `src/`):

- private chat + sender has a public username: finds users from `findAllAwaitingTelegramLink()` (username set,
  chat ID empty) with the same username and saves the chat ID. Same effect as `app:telegram:link-users`, but instant.
- already linked users and groups: just replies with the chat ID, nothing is changed.
- unknown username: replies that no account is waiting and shows the chat ID for manual entry.

## /contact handler

`src/Presentation/User/Telegram/ContactCommandController.php`:

1. `/contact` (private chat, chat already linked via `/start`) replies with a one-time keyboard button
   **Share my phone** (`KeyboardButton(requestContact: true)`). Telegram asks the user to confirm sending.
2. A second handler (`#[OnEvent(Event\Message::class)]`) catches messages with a `contact`. It accepts only the
   sender's own contact (`contact.user_id === from.id`), so a forwarded or typed contact is rejected.
3. The number is saved to `users.telegram_phone` (migration `Version20261116120000`; shown in the admin user form
   as **Telegram phone**, editable). The bot confirms it and, in the same message, replaces the one-time keyboard with
   the `/start` menu (`StartCommandController::buildKeyboard()`), so the user lands in the main menu.

Changing the number: sharing again overwrites it. `/contact` with a saved number shows it and a button
**Update my phone**; the `/start` menu has the same one-tap `request_contact` button (**Share my phone** while the
number is empty, **Update my phone** afterwards). The reply says whether the number was saved, updated
("updated: new (was old)") or was the same ("already saved, nothing changed").

The chat must be linked first: the user is found by `telegram_chat_id` (`UserRepository::findOneByTelegramChatId`).
Messages without a contact are passed on (`continue()`), so other handlers still run.
Apply the migration: `bin/console doctrine:migrations:migrate`.

## /buttons handler

`src/Presentation/User/Telegram/ButtonsCommandController.php`: `/buttons` sends "Choose a button:" with an inline
keyboard (Button 1, Button 2, Button 3, one per row). Each button has `callback_data` `button:N`, handled by
`#[OnCallback('button:N')]`, which answers the callback query with a notification "You pressed button N"
(this also stops the loading spinner on the button). To add real actions, put them into the `button1..3` methods;
callback data is limited to 64 bytes.

## /keyboard handler

`src/Presentation/User/Telegram/KeyboardCommandController.php`: `/keyboard` shows a persistent reply keyboard under
the input field (Button 1 and Button 2 in the first row, Button 3 in the second). Unlike the inline buttons of
`/buttons`, a reply keyboard button just sends its text as a normal message, so an `#[OnEvent(Event\Message::class)]`
method matches the text and replies "You pressed Button N"; any other message is passed on (`continue()`).
`/keyboard_hide` removes the keyboard (`ReplyKeyboardRemove`; the command is not published to the menu).

## /contact_us and the "Contact Us" button

`src/Presentation/User/Telegram/ContactUsCommandController.php`: `/contact_us` replies with the contact text (the `TEXT`
constant, placeholder: put the real contact details there). The **Contact Us** button of the `/start` keyboard is a
reply keyboard button, so it only sends its text as a message; a bot cannot send a command in the user's name. The same
controller therefore matches that text (`#[OnEvent(Event\TextMessage::class)]`) and runs the same method, so the button
and the command give the same answer. `StartCommandController::pressed()` passes this button on with `continue()`.

## /start keyboard buttons

`StartCommandController` shows **Status**, **Enable/Disable notifications** (depends on `telegram_notifications_enabled`),
**Contact Us** and **Cancel**. Every pressed button deletes the user's message from the chat. Enable/Disable saves the
setting, confirms and redraws the keyboard; **Status** replies with the linked account, whether notifications are
enabled and the shared phone (or "not shared"), or says the chat is not linked; Contact Us is answered by `ContactUsCommandController`; **Cancel** replies
"Menu closed" and shrinks the keyboard to a single **Start** button; **Start** (like `/start`) shows the full menu again.

Publish the commands to the bot menu button: `bin/console telegram:button:update` (done for `/start` and `/contact`).

With the webhook active this replaces `app:telegram:link-users` (which needs `getUpdates`).

Note: `bin/console` was not run while writing this (no `php` on the host), so verify inside the container:
`bin/console debug:router telegram_webhook` and `bin/console lint:container`.

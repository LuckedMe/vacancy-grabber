# Plan: general async Messenger message for sending a Telegram notification

## Context
`TelegramNotifier::send(string $chatId, string $text)` (`src/Infrastructure/Telegram/TelegramNotifier.php:30`) is called synchronously from `GrabSourceHandler` and `GrabAllSourcesConsoleCommand`, so a slow or failing Telegram API blocks the caller (and the cron command's loop). We want one general-purpose async message with params `chatId` and `message` that any code can dispatch, so the HTTP call is done by the worker with retries.

## Design (follows existing conventions)
- Module: new `Telegram` module under `Application`, since the message is general and not tied to Source or User.
- Message: `src/Application/Telegram/Message/Command/SendTelegramNotificationAsyncCommand.php`
  - `final readonly`, implements `StandardPriorityAsyncMessageInterface` (`App\Application\Shared\Bus`), so the existing routing in `config/packages/messenger.yaml` sends it to `queue_standard_priority`. No config change and no worker change; the prod worker already consumes that queue.
  - Public promoted props: `string $chatId`, `string $message`. Docblock `/** @see SendTelegramNotificationHandler */`, as in `GrabSourceAsyncCommand`.
- Handler: `src/Application/Telegram/MessageHandler/Command/SendTelegramNotificationHandler.php`
  - `final readonly`, implements `CommandHandlerInterface`. Services.yaml `_instanceof` tags it for `command.bus` automatically.
  - `__invoke(SendTelegramNotificationAsyncCommand $command): void`:
    - returns without sending when `!$telegramNotifier->isEnabled()`
    - otherwise calls `$this->telegramNotifier->send($command->chatId, $command->message)`
    - does not catch `TelegramException`, so Messenger's retry strategy (5 retries, 60 s delay, x2) and the `failed` transport apply.
  - Injects the concrete `TelegramNotifier`, like `GrabSourceHandler` does today. This is an Application → Infrastructure import that the DDD skill's wording does not allow. Not fixed here; noted so it can be handled with a Domain port later.
- Dispatch: `$messageBusHandler->handleCommand(new SendTelegramNotificationAsyncCommand($chatId, $message));` via `src/Infrastructure/Messenger/MessageBusHandler.php` (callers use this wrapper, not `MessageBusInterface`).

## Call sites to switch to the async message
1. `src/Application/Source/MessageHandler/Command/GrabSourceHandler.php` (`notifyOwner()`, lines ~85-101): replace the `isEnabled()` check plus `send()` with a dispatch. Inject `MessageBusHandler` instead of `TelegramNotifier`. The existing `try/catch (Throwable)` can stay for the dispatch.
2. `src/Presentation/Source/Command/GrabAllSourcesConsoleCommand.php` (lines ~105, 133): replace `send()` with a dispatch. Keep the `isEnabled()` check to skip building reports when Telegram is off.

`LinkTelegramUsers*` and `TelegramBotChatResolver` only use `getUpdates()` and stay unchanged.

## Behaviour changes to be aware of
- With `sync://` (`.env.local`, `.env.test`) the message runs inline, so tests and local dev behave as before.
- With `doctrine://` (prod) delivery moves to the worker. A Telegram failure no longer shows in the cron command or `/grab` flow. It is retried and finally lands in the `failed` transport.
- `GrabSourceHandler` is itself handled inside `command.bus` with the `doctrine_transaction` middleware, so dispatching an async message there should use `handleCommand($msg, dispatchAfterCurrentBus: true)` to send only after the transaction commits.

## Docs
Add a short "Async sending" section to `docs/telegram-notifications.md`: the message class, how to dispatch it, the retry and failed-queue behaviour, and that the prod `worker` service must be running (`docker-compose.prod.yaml:27-39`).

## Verification
- Unit test `tests/Suite/Unit/Application/Telegram/SendTelegramNotificationHandlerTest.php` (Codeception `Unit`, `final class`, `testXxx`). `TelegramNotifier` is `final readonly`, so build it with a `MockHttpClient` and a token string; assert the request body has `chat_id` and `text`, that nothing is sent with an empty token, and that `TelegramException` propagates.
- `bin/console debug:messenger` lists the new handler on `command.bus`.
- `bin/console messenger:consume queue_standard_priority -vv --limit=1` after dispatching from a console command, with `MESSENGER_TRANSPORT_DSN=doctrine://...`; check Telegram receives the text.
- `bash .claude/skills/ddd-structure-fix/scripts/check.sh` shows no new violations.
- Run the Codeception unit and API suites.

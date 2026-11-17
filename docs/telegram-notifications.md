# Telegram notifications

`bin/console app:source:grab-all` sends each user a short report (vacancies created / updated and a link to
unviewed vacancies) to their Telegram chat. This needs a bot token and a chat ID per user.

## 1. Get TELEGRAM_API_TOKEN

1. Open Telegram and start a chat with [@BotFather](https://t.me/BotFather) (check the official verified account).
2. Send `/newbot`.
3. Enter a display name for the bot (e.g. `Vacancy Grabber`).
4. Enter a unique username that ends with `bot` (e.g. `my_vacancy_grabber_bot`).
5. BotFather replies with the token, e.g. `123456789:AAH...`. Copy it.

Lost or leaked token: send `/mybots` to BotFather, choose the bot, then `API Token` -> `Revoke current token`.

## 2. Configure the app

Put the token in `.env.local` (dev) or in the environment of the production container. Never commit it.

```dotenv
TELEGRAM_API_TOKEN=123456789:AAH...
```

When the variable is empty, the command skips Telegram notifications and prints a note.

## 3. Get a chat ID for each user

1. The user opens the bot in Telegram and presses **Start** (a bot cannot message a user who has not written to it first).
2. The user sends any message to the bot.
3. Open `https://api.telegram.org/bot<TELEGRAM_API_TOKEN>/getUpdates` in a browser (or use `curl`).
4. Find `message.chat.id` in the JSON response. This number is the chat ID.
   Alternatively, the user can message [@userinfobot](https://t.me/userinfobot), which replies with their ID.
5. In the admin panel, edit the user and fill in **Telegram chat ID**.

For a group chat, add the bot to the group, send a message there, and read `chat.id` from `getUpdates`
(group IDs are negative).

### Alternative: enter a username, let the app find the chat ID

A user has two Telegram fields in the admin:

- **Telegram username** - `@username` or `https://t.me/username`. Used only to find the chat ID.
- **Telegram chat ID** - the chat the notifications are sent to.

Fill in the username, ask the user to press **Start** in the bot once, then run:

```bash
bin/console app:telegram:link-users
```

For every user with a username and an empty chat ID, the command reads the messages sent to the bot
(`getUpdates`), matches private chats by username and saves the numeric chat ID into **Telegram chat ID**.
The username field is not changed, and users who already have a chat ID are skipped.
Run it before `app:source:grab-all` (e.g. in cron). Telegram keeps the bot's messages for about 24 hours.
The user needs a public Telegram username (Settings -> Username).

## 4. Check

```bash
curl "https://api.telegram.org/bot<TELEGRAM_API_TOKEN>/sendMessage" -d chat_id=<CHAT_ID> -d text=test
bin/console app:source:grab-all
```

## Database

The username is stored in `users.telegram_username` and the chat ID in `users.telegram_chat_id`; apply the migration with
`bin/console doctrine:migrations:migrate`.

## Switching notifications off for a user

`users.telegram_notifications_enabled` (admin user form, Telegram section: **Allow Telegram notifications**, default
**No**) controls whether the user gets the report. Only with **Yes** `app:source:grab-all` sends it; with **No** the
user is skipped even if a chat ID is set, so enable it for each user who should be notified. Apply the migration with `bin/console doctrine:migrations:migrate`.

## Unique Telegram username

`users.telegram_username` has a unique index (migration `Version20261118120000`, which fails if two users already
share a username: fix the duplicates first). The admin form shows a validation error for a taken username. The
comparison is on the value as entered and is case-insensitive in MySQL, so `@Name` and `name` are different values
for the database even though `/start` treats them as the same user.

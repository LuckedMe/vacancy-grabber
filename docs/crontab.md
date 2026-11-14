# Crontab on the production server

The CLI commands run inside the production `php-fpm` container, started from the host's cron with
`docker compose exec`. Nothing is installed in the image for scheduling.

Server layout, user and paths come from the VPS guide (`vps-setup` project, `docs/vps-setup.md`):

| What | Value |
|---|---|
| User | `dad` (docker works without sudo) |
| Project | `/home/dad/workspace/www/vacancy-grabber` (compose file `docker-compose.prod.yaml`, env `.env.prod.local`) |
| Cron logs | `/home/dad/workspace/www/vacancy-grabber/var/log/crontab/` (on the host, inside the project folder) |
| Backups and their log | `/home/dad/workspace/backups/` |
| Server timezone | set in VPS guide step 1: `UTC` or `Europe/Kyiv` (`timedatectl`) |
| Existing crontab entry | DB backup at 03:00 (VPS guide step 17); **keep it** |

| Command | What it does | Schedule |
|---|---|---|
| `app:source:grab-all` | Grabs all active sources and sends the Telegram report | every hour from 08:00 to 20:00 |
| `app:telegram:link-users` | Finds the Telegram chat ID of users who entered a username | every 10 minutes |

See `docs/telegram-notifications.md` for what the Telegram commands need (bot token, chat IDs).

## Crontab entries

```cron
CRON_TZ=Europe/Kyiv

# vacancy-grabber: grab all sources every hour, 08:00 ... 20:00 (13 runs a day)
0 8-20 * * * cd /home/dad/workspace/www/vacancy-grabber && flock -n /tmp/vacancy-grabber-grab-all.lock docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console app:source:grab-all --no-interaction >> /home/dad/workspace/www/vacancy-grabber/var/log/crontab/grab-all.log 2>&1

# vacancy-grabber: link Telegram usernames to chat IDs every 10 minutes
*/10 * * * * cd /home/dad/workspace/www/vacancy-grabber && flock -n /tmp/vacancy-grabber-link-users.lock docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console app:telegram:link-users --no-interaction >> /home/dad/workspace/www/vacancy-grabber/var/log/crontab/link-users.log 2>&1
```

Why it looks like this:

- `0 8-20 * * *` runs at minute 0 of every hour from 8 to 20 inclusive, so the last run is at 20:00.
- `CRON_TZ=Europe/Kyiv` makes the hours local time (the app uses `TIMEZONE=Europe/Kiev`) whatever timezone the
  server has. `CRON_TZ` applies to all entries below it, **including the existing backup entry**: put it
  after the backup line (or use `CRON_TZ=` with the timezone you want for it) if the backup must stay on server
  time. Ubuntu 24.04 cron supports `CRON_TZ`.
- `exec -T` is required in cron: there is no terminal, and without `-T` docker fails with
  "the input device is not a TTY".
- `flock -n` skips a run when the previous one is still going, so a slow grab never overlaps the next one.
- `cd ...` is needed so docker compose finds the compose file and `.env.prod.local`.
- `php-fpm` must be running (`make prod-ps`); `exec` does not start it. After `make prod-up` (project update) the
  container is recreated and cron keeps working, because it only addresses the service by name.

### How often should `app:telegram:link-users` run

Every **10 minutes** is my recommendation.

- A user who presses Start in the bot is linked within ten minutes, so the next hourly grab report already
  reaches them.
- It is cheap: when nobody is waiting (no user has a username without a chat ID) the command exits before calling
  Telegram. Otherwise it makes one `getUpdates` request.
- It must run at least once a day: Telegram keeps the bot's messages for about 24 hours only.
- For less noise, run it every 30 minutes, or hourly at `:50`, just before the grab
  (`50 7-19 * * *`).

## Install

On the server (`ssh dad@100.91.57.78`):

```bash
mkdir -p /home/dad/workspace/www/vacancy-grabber/var/log/crontab     # the cron output goes here

crontab -l                  # see what is there already: the 03:00 backup entry must stay
crontab -e                  # add the entries above at the end, save
crontab -l                  # check
```

Do not use `crontab -r` or paste a whole new crontab over the old one: that deletes the backup entry.
`crontab -e` asks for an editor on the first run: choose `nano`.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Nothing in the log, no `CRON` line in syslog | Entry not saved, or the cron service is stopped: `systemctl status cron` |
| `the input device is not a TTY` | `-T` is missing in `docker compose exec` |
| `no configuration file provided` / `.env.prod.local not found` | `cd` points to the wrong directory |
| `service "php-fpm" is not running` | The stack is down: `make prod-up` |
| `permission denied ... docker.sock` | The crontab user is not in the `docker` group (VPS guide step 8) |
| `No such file or directory` for the log | `mkdir -p /home/dad/workspace/www/vacancy-grabber/var/log/crontab` was not run |
| Log files grow | There is no log rotation yet; check their size from time to time (`ls -lh var/log/crontab`) and truncate with `: > var/log/crontab/grab-all.log` |
| Runs at the wrong hour | Timezone: see `CRON_TZ` above and `timedatectl` |
| Log says `TELEGRAM_BOT_TOKEN is not set` | Set it in `.env.prod.local` and recreate the container (`make prod-up`) |

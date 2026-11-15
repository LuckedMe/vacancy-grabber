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

See `docs/telegram-notifications.md` for what the Telegram report needs (bot token, chat IDs).

## Crontab entries

The job itself is the script `config/crontab/source-grab-all.sh` (in the repository, so it is updated by
`git pull`). The crontab only starts it:

```cron
CRON_TZ=Europe/Kyiv

# vacancy-grabber: grab all sources every hour, 08:00 ... 20:00 (13 runs a day)
0 8-20 * * * /home/dad/workspace/www/vacancy-grabber/config/crontab/source-grab-all.sh
```

What the script does (`config/crontab/source-grab-all.sh`):

1. Finds the project folder from its own location, so it works from cron's empty environment and from any
   directory.
2. Creates `var/log/crontab/` if it is missing.
3. Deletes `grab-all-*.log` files older than 7 days (`LOG_KEEP_DAYS` in the script). Other files are never touched,
   and a failure of this step does not stop the grab.
4. Skips the run when the previous one is still going (`flock`); the skip is written to the log.
5. Runs `docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console app:source:grab-all`
   and writes the output to its own file, `var/log/crontab/grab-all-YYYY-MM-DD_HHMM.log`
   (e.g. `grab-all-2026-11-16_0800.log`).

Why the crontab line looks like this:

- `0 8-20 * * *` runs at minute 0 of every hour from 8 to 20 inclusive, so the last run is at 20:00.
- `CRON_TZ=Europe/Kyiv` makes the hours local time (the app uses `TIMEZONE=Europe/Kiev`) whatever timezone the
  server has. `CRON_TZ` applies to all entries below it, **including the existing backup entry**: put it
  after the backup line (or use `CRON_TZ=` with the timezone you want for it) if the backup must stay on server
  time. Ubuntu 24.04 cron supports `CRON_TZ`. The `date` in the log file name uses the same timezone.
- The script must be executable (`chmod +x`, stored in git). After a `git pull` that changed it nothing else is
  needed: cron runs the file on disk at the next start.
- `php-fpm` must be running (`make prod-ps`); `exec` does not start it. After `make prod-up` (project update) the
  container is recreated and cron keeps working, because the script only addresses the service by name.

Run the job by hand, exactly as cron does:

```bash
/home/dad/workspace/www/vacancy-grabber/config/crontab/source-grab-all.sh
ls -lt /home/dad/workspace/www/vacancy-grabber/var/log/crontab | head -3
```

## Install

On the server (`ssh dad@100.91.57.78`):

```bash
chmod +x /home/dad/workspace/www/vacancy-grabber/config/crontab/source-grab-all.sh   # if git did not keep the flag

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
| `the input device is not a TTY` | `-T` is missing in `docker compose exec` in the script |
| `Permission denied` for the script | `chmod +x config/crontab/source-grab-all.sh` |
| Old logs are not deleted | Only `grab-all-*.log` older than 7 days are removed, by the script itself; check `ls -lt var/log/crontab` |
| `no configuration file provided` / `.env.prod.local not found` | The script is not inside the project folder (it uses `../..` from its own location) |
| `service "php-fpm" is not running` | The stack is down: `make prod-up` |
| `permission denied ... docker.sock` | The crontab user is not in the `docker` group (VPS guide step 8) |
| Runs at the wrong hour | Timezone: see `CRON_TZ` above and `timedatectl` |
| Log says `TELEGRAM_BOT_TOKEN is not set` | Set it in `.env.prod.local` and recreate the container (`make prod-up`) |

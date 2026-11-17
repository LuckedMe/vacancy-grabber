# Deploy to production

How to roll out a new version on the VPS. The first-time server setup (Docker, Traefik, secrets, first start)
is in the `vps-setup` project, `docs/vps-setup.md` (steps 8-15); this file is only about updating a running
stack.

| What | Value |
|---|---|
| Server | `ssh dad@100.91.57.78` (Tailscale IP; your machine must be in the tailnet) |
| Project | `/home/dad/workspace/www/vacancy-grabber` |
| Stack | `docker-compose.prod.yaml` + `.env.prod.local`: `php-fpm`, `worker`, `nginx`, `mysql` |
| Shortcuts | `make prod-*` (see `make help`); each one is a plain `docker compose ...` command, listed below |

## How a deploy works

- The code is **baked into the Docker images** (`vacancy-grabber-prod-app`, `vacancy-grabber-prod-nginx`), it is
  not mounted from the server folder. `git pull` alone changes nothing in the running site: the images must be
  rebuilt and the containers recreated (`make prod-up`).
- `php-fpm` and `worker` use the same `app` image, so one build updates both. The worker is recreated too, which
  also restarts its consumer with the new code.
- The frontend is built **inside** the image build (`assets` stage: `yarn install --frozen-lockfile` and
  `yarn build`). Node and yarn are not needed on the server. Docker reuses the cached result when
  `package.json`, `yarn.lock`, `assets/` and `webpack.config.js` did not change.
- Composer runs in the `vendor` stage (`--no-dev`), and the Symfony cache is warmed up during the build, so there
  is no `composer install` or `cache:clear` step on the server.
- `.env.prod.local` is read when a container is **created**. Changing it requires `make prod-up` (recreate), a
  restart is not enough for a new value to be applied.
- Data survives a deploy: the database is in the `mysql-data` volume, JWT keys in the `jwt` volume.

## Deploy script (recommended)

`bin/deploy.sh` does the whole standard deploy below in one go and stops at the first failing step:

```bash
bin/deploy.sh --remote          # from your machine (needs the tailnet), runs the server's copy over ssh
# or on the server:
cd /home/dad/workspace/www/vacancy-grabber && bin/deploy.sh     # same as: make prod-deploy
```

What it does, in order:

1. **Checks**: docker/git present, `docker-compose.prod.yaml` and `.env.prod.local` exist, no other deploy is running,
   no tracked file was edited by hand on the server.
2. **Fetches** `origin/main`, prints the commits that are coming and the rollback point (the commit that runs now).
   Nothing new -> "already up to date", exit 0 (`--force` rebuilds anyway, e.g. after a change of `.env.prod.local`).
3. **Compares `.env.prod.dist` with `.env.prod.local`** and stops, **before** anything is changed, when a variable
   is missing (it lists the names; add them and run again).
4. **Backs up the database** with `BACKUP_SCRIPT`, only when the release contains files in `migrations/`
   (`--no-backup` skips it).
5. `git pull --ff-only`, then `docker compose up -d --build --remove-orphans`.
6. **Migrations**: `doctrine:migrations:migrate` and `messenger:setup-transports` (the same as `make prod-migrate`).
7. **Telegram webhook**: `telegram:webhook:update` and `telegram:button:update` when `TELEGRAM_API_TOKEN` and
   `TELEGRAM_WEBHOOK_URL` are set; a failure there is only a warning.
8. **Check**: `docker compose ps`, an HTTP request to the site (a warning when it is not 200/301/302) and
   `docker image prune -f`.

| Option | |
|---|---|
| `--branch NAME` | deploy another branch (default `main`), e.g. to try a release before the merge |
| `--no-backup` | skip the database backup |
| `--force` | rebuild and restart even when there is nothing new to pull |
| `--dry-run` | print the commands, change nothing (`git fetch` still runs): read it before the first real run |
| `--remote` | run on the server over ssh; the other options are passed on |

Environment: `DEPLOY_HOST` (default `dad@100.91.57.78`), `DEPLOY_DIR` (default
`/home/dad/workspace/www/vacancy-grabber`), `BACKUP_SCRIPT` (default
`/home/dad/workspace/backups/backup-vacancy-grabber.sh`), `APP_URL` (default `https://vacancy-grabber.lucked.me/`).

The script never edits `.env.prod.local`: new variables are added by hand, see the release notes below. It does
not roll back by itself; on a failure read the message, the rollback point is in the output (see "Rollback").
The first run on the server needs the file executable (`chmod +x bin/deploy.sh`, it is committed with the mode).

## Standard deploy (manual steps)

The steps `bin/deploy.sh` runs, for when you want to do or debug them one by one.

```bash
ssh dad@100.91.57.78
cd /home/dad/workspace/www/vacancy-grabber
```

### 1. Check the server state

```bash
git status --short            # must be clean; .env.prod.local is not tracked, that is fine
git log --oneline -1          # the version that runs now (note it for a rollback)
make prod-ps                  # all containers Up, mysql healthy
df -h /                       # builds need free disk space (several GB for old layers)
```

If `git status` shows changed files, find out why before pulling: someone edited the code on the server by hand.

### 2. Back up the database (before migrations)

```bash
/home/dad/workspace/backups/backup-vacancy-grabber.sh
ls -lh /home/dad/workspace/backups/dump | tail -3
```

Skip it only for a release without migrations. A migration is a change in `migrations/`; look at what is coming:

```bash
git fetch
git diff --stat HEAD..origin/main -- migrations composer.lock package.json yarn.lock .env.prod.dist
```

### 3. Get the code

```bash
git pull
```

- Read the output: new files in `migrations/`, `.env.prod.dist`, `composer.lock`, `yarn.lock` tell what the
  next steps must do.
- If `.env.prod.dist` got new variables, add them to `.env.prod.local` **before** step 4
  (`diff <(grep -oE '^[A-Z_]+=' .env.prod.dist | sort) <(grep -oE '^[A-Z_]+=' .env.prod.local | sort)` shows
  the missing names).

### 4. Rebuild the images and restart the stack

```bash
make prod-up
```

It is `docker compose -f docker-compose.prod.yaml --env-file .env.prod.local up -d --build --remove-orphans`:

- builds the images (composer vendor, **frontend build**, Symfony cache warmup, nginx);
- recreates the containers whose image or config changed; `mysql` is left running;
- takes about 1-3 minutes with a warm cache and 5-15 minutes after a dependency change.

To check the frontend build on its own first, without touching the running site: `make prod-assets`
(the log must contain `yarn install` and `encore production` and end without `ERROR`).

### 5. Run the migrations

```bash
make prod-migrate
```

It runs `doctrine:migrations:migrate --no-interaction --allow-no-migration` and
`messenger:setup-transports`; with nothing to migrate it is harmless, so run it on every deploy. Between the
new code starting (step 4) and the migration finishing (step 5) the site may fail on a request that needs a new
column: for a release with a destructive migration (dropping or renaming a column) do the backup, run steps 4-5
back to back and do not leave a gap.

```bash
# what was applied
docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm \
  php bin/console doctrine:migrations:status
```

### 6. Check

```bash
make prod-ps                                   # php-fpm, worker, nginx Up; mysql healthy
make prod-logs                                 # Ctrl+C to leave; look for errors after the restart
curl -sI https://vacancy-grabber.lucked.me/ | head -1      # HTTP/2 200 (or 302 to the login page)
```

Open the site in the browser (hard refresh, `Ctrl+Shift+R`, to drop the cached JS) and click through the changed
screens. Check the new assets are in the image:

```bash
docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm ls public/assets | head
```

Check the worker picked up the queue:

```bash
docker compose -f docker-compose.prod.yaml --env-file .env.prod.local logs --tail=20 worker
```

### 7. Clean up

```bash
docker image prune -f          # dangling images from the previous builds
```

## One-liner

The script above replaces it. By hand, when the release is routine (the backup is automatic at 03:00 and you accept that):

```bash
cd /home/dad/workspace/www/vacancy-grabber && git pull && make prod-up && make prod-migrate && make prod-ps
```

`&&` stops at the first failing step. Do not use `;`.

## What to run for what changed

| Changed | Needed |
|---|---|
| PHP code, templates, translations, config | `make prod-up` |
| `assets/`, `package.json`, `yarn.lock`, `webpack.config.js` | `make prod-up` (frontend is rebuilt in it) |
| `composer.json` / `composer.lock` | `make prod-up` |
| `migrations/` | `make prod-up`, then `make prod-migrate` (after a backup) |
| `.env.prod.dist` (new variable) | add it to `.env.prod.local`, then `make prod-up` |
| Only `.env.prod.local` | `make prod-up` (recreates the containers with the new env) |
| `docker-compose.prod.yaml` / `Dockerfile` | `make prod-up` |
| A new CLI command to run on a schedule | add it to the crontab, see `docs/crontab.md` |
| `.docker/mysql/config/mysqld.cnf` | `make prod-up`, then `docker compose ... restart mysql` |

## Release notes: Telegram bot (notifications, webhook, menu)

The release that adds the Telegram bot (`luzrain/telegram-bot-bundle`, docs: `docs/telegram-notifications.md`,
`docs/telegram-webhook.md`) needs, besides the standard steps:

1. **New variables in `.env.prod.local`**, before `make prod-up` (they are in `.env.prod.dist`):

   | Variable | Value |
   |---|---|
   | `TELEGRAM_API_TOKEN` | the token from @BotFather (renamed from `TELEGRAM_BOT_TOKEN`: rename it if it is already set, the old name is no longer read) |
   | `TELEGRAM_WEBHOOK_URL` | `https://vacancy-grabber.lucked.me/telegram/webhook` (public HTTPS) |
   | `TELEGRAM_WEBHOOK_SECRET` | `openssl rand -hex 32` (only `A-Z a-z 0-9 _ -`) |

2. **Before the backup/migration, check the data**: the migrations add a unique index on `users.telegram_username`,
   which fails if two users already have the same username:

   ```bash
   docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console dbal:run-sql \
     "SELECT telegram_username, COUNT(*) c FROM users WHERE telegram_username IS NOT NULL GROUP BY 1 HAVING c > 1"
   ```

3. `make prod-migrate` adds to `users`: `telegram_chat_id`, `telegram_username` (unique), `telegram_phone`,
   `telegram_notifications_enabled`. **The notifications flag defaults to No for everybody**, so nobody gets the
   report until you switch **Allow Telegram notifications** to Yes on the user in the admin (or the user presses
   the enable button in the bot).
4. **Register the webhook** (`bin/deploy.sh` does it when both variables are set; by hand otherwise) after `make prod-up` (the containers must run the new code) and after every change of
   the token, URL or secret:

   ```bash
   docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console telegram:webhook:update
   docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console telegram:webhook:info
   docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm php bin/console telegram:button:update   # bot menu commands
   ```

   `telegram:webhook:info` must show the URL and no `last_error_message`.
5. The cron entry for `app:source:grab-all` from `docs/crontab.md` and the folder `var/log/crontab`.
   `app:telegram:link-users` (reads `getUpdates`) cannot work while a webhook is set: users are linked by
   `/start` in the bot instead (their Telegram username must be filled in in the admin).
6. Check in Telegram: send `/start` to the bot (the menu appears, the chat is linked), then **Status**.

Without the webhook variables the bot only sends notifications; it just does not answer messages.

## Rollback

If the new version is broken:

```bash
cd /home/dad/workspace/www/vacancy-grabber
git log --oneline -5                       # find the previous good commit (noted in step 1)
git checkout <previous-commit>             # detached HEAD is fine for an emergency
make prod-up
```

Migrations are not rolled back automatically. Code that is older than the schema usually keeps working when the
migration only **added** columns or tables (as the Telegram ones do). When a migration changed or dropped
something, restore the database from the backup of step 2 (restore commands: `vps-setup/docs/vps-setup.md`,
step 17, "Restore"), or roll one migration back:

```bash
docker compose -f docker-compose.prod.yaml --env-file .env.prod.local exec -T php-fpm \
  php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20261115120000' --down --no-interaction
```

Do this with the **old** code running or the class of the migration will not exist. After the fix is on `main`,
return to it: `git checkout main && git pull && make prod-up && make prod-migrate`.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `git pull` says local changes would be overwritten | Files were edited on the server. `git diff` to see them, `git stash` to put them aside |
| `git pull` asks for credentials / permission denied | The server deploy key (VPS guide step 11); `ssh -T git@github.com` (or bitbucket) to test |
| The site still shows the old version | `make prod-up` was not run (only `git pull`), or the browser caches: hard refresh |
| Build killed / `exit code 137` during `yarn build` | Out of memory: check swap (VPS guide step 6), then `docker compose ... build --build-arg NODE_MAX_OLD_SPACE=1024` and `make prod-up` |
| `no space left on device` | `docker image prune -f`; if still short, `docker builder prune -f` (the next build is slow) |
| `yarn install` fails with a network timeout | Run `make prod-up` again, the yarn cache is kept between builds |
| 500 after deploy, log mentions an unknown column | `make prod-migrate` was not run |
| 500, log mentions an environment variable not found | New variable missing in `.env.prod.local`, add it and `make prod-up` |
| The bot does not answer in Telegram | `telegram:webhook:info`: wrong `url`, `last_error_message`, or pending updates. Check `TELEGRAM_WEBHOOK_URL` / `_SECRET` in `.env.prod.local`, `make prod-up`, then `telegram:webhook:update`. A 401/403 on `/telegram/webhook` means the secret differs from the one set in Telegram |
| Changed `.env.prod.local` but nothing changed | Containers were not recreated: `make prod-up` |
| `worker` keeps restarting | `docker compose ... logs worker`; usually the same cause as the 500s above |
| Frontend broken (404 on `/assets/...`) | `make prod-assets` to see the build error; clear a broken cache with `docker builder prune -f` and `make prod-up` |

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

## Standard deploy

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

When the release is routine (backup is automatic at 03:00 and you accept that):

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

## Release notes: Telegram notifications

The first deploy that includes the Telegram notifications needs, besides the standard steps:

1. `TELEGRAM_BOT_TOKEN=...` in `.env.prod.local` **before** `make prod-up` (see `docs/telegram-notifications.md`;
   the variable is in `.env.prod.dist`).
2. `make prod-migrate`: adds `users.telegram_chat_id` and `users.telegram_username`.
3. The cron entries from `docs/crontab.md` (`app:source:grab-all`, `app:telegram:link-users`) and the folder
   `var/log/crontab`.
4. The users' Telegram username or chat ID in the admin.

## Rollback

If the new version is broken:

```bash
cd /home/dad/workspace/www/vacancy-grabber
git log --oneline -5                       # find the previous good commit (noted in step 1)
git checkout <previous-commit>             # detached HEAD is fine for an emergency
make prod-up
```

Migrations are not rolled back automatically. Code that is older than the schema usually keeps working when the
migration only **added** columns or tables (as the Telegram one does). When a migration changed or dropped
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
| Changed `.env.prod.local` but nothing changed | Containers were not recreated: `make prod-up` |
| `worker` keeps restarting | `docker compose ... logs worker`; usually the same cause as the 500s above |
| Frontend broken (404 on `/assets/...`) | `make prod-assets` to see the build error; clear a broken cache with `docker builder prune -f` and `make prod-up` |

# Vacancy grabber
Grab vacancies by source URL

## Requirements:
- php 8.4
- mysql 8

## Installation:

### Add a host 
edit /etc/hosts  
add a new host: vacancy-grabber.local

### Compile all docker images

#### Build docker images
```bash
make docker-build
```

#### Run docker images
```bash
make docker-up
```

#### Run docker logs
```bash
make docker-logs
```

#### Connect to php-fpm console
```bash
make php-console
```

#### Connect to mysql console
```bash
make mysql-console
```

[//]: # (#### Connect to nodejs console)
[//]: # (```bash)
[//]: # (make nodejs-console)
[//]: # (```)

#### Install vendor packages
```bash
make install
```

#### Run Migrations
```bash
make migrate
```
It migrates both the dev database and the test database (`--env=test`), so the test suite has its schema.

#### Generate the JWT keypair (one-time)
```bash
make jwt-keys
```
The keys are written to `config/jwt/` (git-ignored). If you protect the private key with a passphrase, set `JWT_PASSPHRASE` in `.env.local` **and** in `.env.test.local`: Symfony does not read `.env.local` when `APP_ENV=test`.

#### Build the frontend
```bash
make yarn-install
make yarn-dev        # or `make yarn-watch` while developing
```

### Docker environment variables
`COMPOSE_PROJECT_NAME`, `COMPOSE_PHP_VERSION`, `COMPOSE_USER_ID` and `COMPOSE_GROUP_ID` live in the root `.env` and are read by `docker-compose.yaml`. After changing the PHP version or the user/group id, rebuild with `make docker-build`. Details: [docs/docker-env.md](docs/docker-env.md).

## Makefile commands
Run `make help` for the list of documented targets. The main ones:

| Group | Command | What it does |
|---|---|---|
| Docker | `make docker-build` / `docker-up` / `docker-stop` / `docker-down` / `docker-restart` | Build, start, stop, remove and restart the dev containers |
| | `make docker-ps` / `docker-logs` | Container status / follow all logs (`php-logs`, `node-logs`, `mysql-logs` for one service) |
| Shells | `make php-console` / `node-console` / `mysql-console` | Open a shell in the php-fpm / nodejs container, or the mysql client |
| Backend | `make install` / `update` | `composer install` / `composer update` |
| | `make migrate` | Doctrine migrations for dev **and** test databases |
| | `make fixtures` | Load database fixtures |
| | `make cache-clear` | Clear the Symfony cache (dev and test) |
| | `make jwt-keys` | Generate the JWT keypair (one-time) |
| | `make lint` / `lint-fix` | PHP-CS-Fixer check / fix |
| Frontend | `make yarn-install` / `yarn-dev` / `yarn-watch` / `yarn-build` | Install JS packages, build for dev, watch, build for production |
| | `make yarn-lint` / `yarn-lint-fix` | ESLint check / fix |
| Tests | `make test` / `test-unit` / `test-api` / `test-web` / `test-acceptance` | See [Testing](#testing) |
| | `make test-coverage` / `test-coverage-html` | Coverage reports |
| Production | `make prod-build` / `prod-up` / `prod-down` / `prod-migrate` / `prod-jwt-keys` / `prod-deploy` / `prod-logs` / `prod-ps` / `prod-console` / `prod-assets` | Same idea for `docker-compose.prod.yaml`, see [Deploy](#deploy-to-live-vps) |

All dev targets run inside the containers, so the stack must be up (`make docker-up`).

## Code Style:

### PHP
> **_NOTE:_** All commands must be executed inside docker image

#### Check
```bash
composer run lint
```
#### Fix
```bash
composer run lint:fix
```

## Testing
The tests use [Codeception](https://codeception.com/) (suites in `tests/Suite/`):

| Suite | Command | What it covers |
|---|---|---|
| Unit | `make test-unit` | Domain and Application classes without the framework or a database |
| API | `make test-api` | REST API (`/api/...`) against the test database, JSON-schema checks in `tests/_data/schemas` |
| Web | `make test-web` | Server-rendered pages and redirects |
| Acceptance | `make test-acceptance` | Browser tests in the `selenium` container (watch at http://localhost:7900); filter with `make test-acceptance ARGS="LoginPageCest"` |

`make test` runs everything **except** acceptance (unit, api, web). `make test-coverage` and `make test-coverage-html` (report in `tests/_output/coverage`) build coverage reports.

Before the first run:
- `make migrate` creates the schema in the test database (`<dbname>_test`).
- `config/jwt/` needs a keypair (`make jwt-keys`). With a passphrase-protected key, add `JWT_PASSPHRASE` to `.env.test.local` (see above), otherwise login returns 500.
- The test env uses `MESSENGER_TRANSPORT_DSN=sync://`, so async messages are handled immediately.

The login and API rate limits are raised for the test env in `.env.test` (`RATE_LIMIT_*`). If a test still gets `429 Too many requests`, clear the limiter cache (inside `make php-console`):
```bash
bin/console cache:pool:clear cache.rate_limiter cache.app --env=test
```

## Architecture
The code in `src/` is split into four layers: **Domain** (entities, repository ports, no framework) <- **Application** (commands, queries, handlers; uses ports only) <- **Infrastructure** (Doctrine, Telegram, Messenger adapters) <- **Presentation** (controllers, console commands, Sonata admin). Commands, queries and events go through the `MessageBus` port; handlers implement `CommandHandlerInterface` / `QueryHandlerInterface`.

The layer rules are checked by a script (also available as the Claude Code skill `/ddd-structure-fix`):
```bash
bash .claude/skills/ddd-structure-fix/scripts/check.sh   # runs on the host, exit code 1 on violations
```
The last result is in [docs/ddd-structure-fix-result.md](docs/ddd-structure-fix-result.md). Other project skills in `.claude/skills/`: `fix-migrations` (format Doctrine migrations) and `main-dockerfile-fix` (check the production Dockerfile).

## Telegram notifications
After a grab, users get a report in Telegram (vacancies created/updated and a link to the unviewed ones).
- Set `TELEGRAM_API_TOKEN` in `.env.local` (empty = notifications are skipped).
- Each user needs a Telegram chat ID: enter a username in the admin and run `bin/console app:telegram:link-users`, or let the bot link users on `/start`.
- Notifications are per user (admin form: **Allow Telegram notifications**).
- Messages are sent **asynchronously**: `SendTelegramNotificationAsyncCommand($chatId, $message)` goes to the `queue_standard_priority` transport and is delivered by the messenger `worker` (retried 5 times, then moved to the `failed` transport).
- The bot also answers commands (`/start`, `/grab`, ...) through a webhook.

Guides: [docs/telegram-notifications.md](docs/telegram-notifications.md) (token, chat IDs, async sending) and [docs/telegram-webhook.md](docs/telegram-webhook.md) (webhook, bot commands).

## Scheduled grabbing
In production, cron runs `bin/console app:source:grab-all` every hour from 08:00 to 20:00 through `bin/source-grab-all.sh`: [docs/crontab.md](docs/crontab.md). Run it by hand with
```bash
bin/console app:source:grab-all
```

## Start using:
### Sources
- Open [Vacancies ⇒ Sources](https://vacancy-grabber.local/admin/source/list) left menu 
- Update URL for the required sources 
- Click on the "Grab" action button for the chosen sources
  ![Vacancies ⇒ Sources list](./docs/images/admin-source-list.png)

## Deploy to live (VPS)

The production image is built from the root `Dockerfile` (targets `app` and `nginx`). The stack lives in `docker-compose.prod.yaml`: php-fpm, the messenger worker, nginx and mysql. It runs behind a shared Traefik reverse proxy.

Full step-by-step server setup (Ubuntu 24.04, Docker, Traefik, first deploy, backups, domains, more projects): **[vps-setup/docs/vps-setup.md](../vps-setup/docs/vps-setup.md)**.

### Short version (server already prepared)
```bash
cd /home/dad/workspace/www/vacancy-grabber
cp .env.prod.dist .env.prod.local   # fill in real values
make prod-up        # build images and start the stack
make prod-jwt-keys  # one-time: JWT keypair (stored in the "jwt" volume)
make prod-migrate   # DB migrations + messenger transports
```

### Update
```bash
bin/deploy.sh --remote      # from your machine (or `bin/deploy.sh` on the server, `make prod-deploy`)
```
It pulls, rebuilds, migrates and registers the Telegram webhook; options and the manual steps: [docs/deploy.md](docs/deploy.md).

## Documentation
| File | Topic |
|---|---|
| [docs/docker-env.md](docs/docker-env.md) | `COMPOSE_*` variables and how they are used |
| [docs/telegram-notifications.md](docs/telegram-notifications.md) | Bot token, chat IDs, async sending |
| [docs/telegram-webhook.md](docs/telegram-webhook.md) | Telegram webhook and bot commands |
| [docs/crontab.md](docs/crontab.md) | Scheduled grabbing on the server |
| [docs/deploy.md](docs/deploy.md) | Production deploy |
| [docs/ddd-structure-fix-result.md](docs/ddd-structure-fix-result.md) | Result of the last DDD layer check |

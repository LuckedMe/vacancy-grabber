# Vacancy Grabber

Collects job vacancies from the sources you choose (Djinni, DOU), keeps them up to date and helps you track your applications: status, resume sent date, deadline and notes. New and changed vacancies are reported to you in Telegram.

**Backend**<br>
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)
![Doctrine](https://img.shields.io/badge/Doctrine-ORM_3-FC6A31?logo=doctrine&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-auth-000000?logo=jsonwebtokens&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot-26A5E4?logo=telegram&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-2-885630?logo=composer&logoColor=white)

**Frontend**<br>
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Vuetify](https://img.shields.io/badge/Vuetify-4-1867C0?logo=vuetify&logoColor=white)
![Pinia](https://img.shields.io/badge/Pinia-4-FFD859?logo=pinia&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-6-3178C6?logo=typescript&logoColor=white)
![Sass](https://img.shields.io/badge/Sass-SCSS-CC6699?logo=sass&logoColor=white)
![Webpack Encore](https://img.shields.io/badge/Webpack_Encore-7-8DD6F9?logo=webpack&logoColor=black)
![Node.js](https://img.shields.io/badge/Node.js-24-5FA04E?logo=nodedotjs&logoColor=white)
![Yarn](https://img.shields.io/badge/Yarn-2C8EBB?logo=yarn&logoColor=white)

**Tooling and infrastructure**<br>
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![Traefik](https://img.shields.io/badge/Traefik-proxy-24A1C1?logo=traefikproxy&logoColor=white)
![Codeception](https://img.shields.io/badge/Codeception-5-1E1E1E)
![Selenium](https://img.shields.io/badge/Selenium-tests-43B02A?logo=selenium&logoColor=white)
![ESLint](https://img.shields.io/badge/ESLint-4B32C3?logo=eslint&logoColor=white)
![PHP-CS-Fixer](https://img.shields.io/badge/PHP--CS--Fixer-code_style-777BB4?logo=php&logoColor=white)

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [Usage](#usage)
- [Development](#development)
- [Testing](#testing)
- [Architecture](#architecture)
- [Telegram notifications](#telegram-notifications)
- [Scheduled grabbing](#scheduled-grabbing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [License](#license)

## Features

- **Grabbing by source URL**: each source is a search/list URL; the grabber imports new vacancies and updates the ones that changed on the site.
- **Vacancy tracking**: statuses, resume sent date, deadline, notes, companies, grab log per run.
- **Dashboards** with vacancy status and resume statistics.
- **Web app** (Vue 3) on top of a REST API secured with JWT, plus a **Sonata admin** at `/admin`.
- **Telegram**: a report after every grab, and a bot (`/start`, `/grab`, ...) working through a webhook.
- **Scheduled grabbing** by cron and **async processing** by a Symfony Messenger worker.

## Tech stack

| Area | Technology |
|---|---|
| Backend | PHP 8.4, Symfony 8.1, Doctrine ORM 3, Symfony Messenger |
| Admin | Sonata Admin (with Google Authenticator) |
| API auth | LexikJWTAuthenticationBundle (JWT + refresh tokens) |
| Frontend | Vue 3, Vuetify 4, Pinia, TypeScript, Sass, Webpack Encore, Yarn (Node 24) |
| Database | MySQL 8 |
| Telegram | `luzrain/telegram-bot-bundle` (webhook) and the Bot API |
| Tests | Codeception 5 (unit, api, web, acceptance with Selenium) |
| Code style | PHP-CS-Fixer, ESLint |
| Runtime | Docker Compose (dev and production), Traefik on the server |

## Quick start

### Prerequisites

- Docker with the Compose plugin, `make`, `git`
- A local reverse proxy that routes `*.vacancy-grabber.local` by `VIRTUAL_HOST` (nginx-proxy style). `docker-compose.yaml` expects two **external** Docker networks: `reverse-proxy` and `shared-smtp`. If you do not have them yet:
  ```bash
  docker network create reverse-proxy
  docker network create shared-smtp
  ```
- The host name in `/etc/hosts`:
  ```
  127.0.0.1  vacancy-grabber.local
  ```

### Install

```bash
git clone git@github.com:LuckedMe/vacancy-grabber.git
cd vacancy-grabber

make docker-build     # build the images
make docker-up        # start the containers
make install          # composer install
make jwt-keys         # one-time: JWT keypair into config/jwt/
make migrate          # migrations for the dev AND the test database
make yarn-install     # JS dependencies
make yarn-dev         # build the frontend (or `make yarn-watch` while developing)
```

Open <https://vacancy-grabber.local>. `make help` lists all documented make targets.

> **Note**
> Every dev command runs inside the containers, so the stack must be up (`make docker-up`).

## Configuration

Symfony loads the files in this order, the latter taking precedence:

| File | Committed | Purpose |
|---|---|---|
| `.env` | yes | defaults and documentation of every variable |
| `.env.local` | no | your machine secrets and overrides (not read when `APP_ENV=test`) |
| `.env.test` | yes | test environment defaults |
| `.env.test.local` | no | test environment secrets |
| `.env.prod.local` | no | production values, created from `.env.prod.dist` |

Never commit secrets: put tokens, passwords and keys into the `*.local` files.

| Variable | Meaning |
|---|---|
| `APP_ENV`, `APP_SECRET`, `TIMEZONE` | Symfony environment, secret, application time zone |
| `DEFAULT_URI` | public base URL (used in Telegram links) |
| `DATABASE_URL` | MySQL connection |
| `MESSENGER_TRANSPORT_DSN` | queue transport (`doctrine://...` in production, `sync://` in tests and local dev) |
| `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`, `JWT_TOKEN_TTL` | JWT keypair, its passphrase and token lifetime |
| `RATE_LIMIT_*` | login and API rate limits |
| `TELEGRAM_API_TOKEN`, `TELEGRAM_WEBHOOK_URL`, `TELEGRAM_WEBHOOK_SECRET` | Telegram bot, see [Telegram notifications](#telegram-notifications) |
| `LUCKED_ME_CMS_CLIENT_*` | CMS client that supplies the public homepage content |
| `COMPOSE_PROJECT_NAME`, `COMPOSE_PHP_VERSION`, `COMPOSE_USER_ID`, `COMPOSE_GROUP_ID` | Docker Compose settings read from `.env`; after changing the PHP version or user/group id run `make docker-build`. Details: [docs/docker-env.md](docs/docker-env.md) |

Optionally copy `docker-compose.override.yaml.dist` to `docker-compose.override.yaml` (git-ignored) to publish the MySQL port `3306` on the host.

## Usage

| What | URL |
|---|---|
| Application | <https://vacancy-grabber.local> |
| Admin | <https://vacancy-grabber.local/admin> |
| phpMyAdmin | <https://webmysql.vacancy-grabber.local> |
| Adminer | <https://adminer.vacancy-grabber.local> |
| Selenium viewer (acceptance tests) | <http://localhost:7900> |

### Grab vacancies

1. Open [Vacancies ⇒ Sources](https://vacancy-grabber.local/admin/source/list) in the admin menu.
2. Set the URL for each source you need.
3. Click the **Grab** action for the chosen sources.

![Vacancies ⇒ Sources list](./docs/images/admin-source-list.png)

From the console:

```bash
bin/console app:source:grab-all        # all active sources, then the Telegram reports
```

## Development

### Make targets

| Group | Commands | What they do |
|---|---|---|
| Docker | `make docker-build` `docker-up` `docker-stop` `docker-down` `docker-restart` | build, start, stop, remove and restart the dev containers |
| | `make docker-ps` `docker-logs` | status / follow all logs (`php-logs`, `node-logs`, `mysql-logs` for one service) |
| Shells | `make php-console` `node-console` `mysql-console` | shell in the php-fpm / nodejs container, mysql client |
| Backend | `make install` `update` | `composer install` / `composer update` |
| | `make migrate` | Doctrine migrations for the dev **and** the test database |
| | `make fixtures` | load database fixtures |
| | `make cache-clear` | clear the Symfony cache (dev and test) |
| | `make jwt-keys` | generate the JWT keypair (one-time) |
| | `make lint` `lint-fix` | PHP-CS-Fixer check / fix |
| Frontend | `make yarn-install` `yarn-dev` `yarn-watch` `yarn-build` | install packages, build for dev, watch, build for production |
| | `make yarn-lint` `yarn-lint-fix` | ESLint check / fix |
| Tests | `make test` `test-unit` `test-api` `test-web` `test-acceptance` | see [Testing](#testing) |
| | `make test-coverage` `test-coverage-html` | coverage reports |
| Production | `make prod-build` `prod-up` `prod-down` `prod-migrate` `prod-jwt-keys` `prod-deploy` `prod-logs` `prod-ps` `prod-console` `prod-assets` | the same idea for `docker-compose.prod.yaml`, see [Deployment](#deployment) |

### Code style

```bash
make lint          # check (composer run lint inside the container)
make lint-fix      # fix
make yarn-lint     # frontend
```

### Database migrations

Generate with `bin/console doctrine:migrations:diff`, then format the file with the project skill `fix-migrations` so it matches the hand-formatted references in `migrations/2026/`. Apply with `make migrate`.

## Testing

[Codeception](https://codeception.com/) suites live in `tests/Suite/`:

| Suite | Command | Covers |
|---|---|---|
| Unit | `make test-unit` | Domain and Application classes, no framework, no database |
| Api | `make test-api` | REST API (`/api/...`) on the test database, JSON-schema checks in `tests/_data/schemas` |
| Web | `make test-web` | server-rendered pages and redirects |
| Acceptance | `make test-acceptance` | browser tests in the `selenium` container (watch at <http://localhost:7900>); filter with `make test-acceptance ARGS="LoginPageCest"` |

`make test` runs everything **except** acceptance (unit, api, web). `make test-coverage` and `make test-coverage-html` (report in `tests/_output/coverage`) build coverage reports.

**Before the first run**

- `make migrate` creates the schema in the test database (`<dbname>_test`).
- `config/jwt/` needs a keypair (`make jwt-keys`). With a passphrase-protected key put `JWT_PASSPHRASE` into **both** `.env.local` and `.env.test.local`; otherwise login returns 500.
- The test environment uses `MESSENGER_TRANSPORT_DSN=sync://`, so async messages are handled immediately.
- Login and API rate limits are raised in `.env.test` (`RATE_LIMIT_*`).

### Run a single test method

Run these inside the php-fpm container (`make php-console`). Acceptance tests also need the `selenium` and `nginx` containers (`make test-acceptance` starts them). The pattern is `codecept run <suite> [<path/to/Class>[::<method>]]`; `<Class>:<method>` works too, and `-d pcov.enabled=1` is only needed for coverage.

Run a single test method (do not forget it):

```bash
php -d pcov.enabled=1 -d memory_limit=-1 ./vendor/bin/codecept run acceptance PageCest::privatePageSuccess
```

Examples per suite:

```bash
# Unit suite
./vendor/bin/codecept run unit                                                  # whole suite
./vendor/bin/codecept run unit Application/Telegram/SendTelegramNotificationHandlerTest                 # one class
./vendor/bin/codecept run unit Application/Telegram/SendTelegramNotificationHandlerTest::testSendsTheMessageToTheChat   # one method

# Api suite
./vendor/bin/codecept run api                                                   # whole suite
./vendor/bin/codecept run api Auth                                              # one folder
./vendor/bin/codecept run api Auth/LoginAuthCest::loginSuccess                  # one method

# Web suite
./vendor/bin/codecept run web                                                   # whole suite
./vendor/bin/codecept run web PageControllerCest::pageTest                      # one method

# Acceptance suite (selenium)
php -d pcov.enabled=1 -d memory_limit=-1 ./vendor/bin/codecept run acceptance                           # whole suite
php -d pcov.enabled=1 -d memory_limit=-1 ./vendor/bin/codecept run acceptance PageCest                  # one class
php -d pcov.enabled=1 -d memory_limit=-1 ./vendor/bin/codecept run acceptance PageCest::privatePageSuccess   # one method
```

From the host, `make test-unit`, `make test-api`, `make test-web` and `make test-acceptance ARGS="PageCest"` do the same for whole suites or a filter.

## Architecture

The code in `src/` has four layers, and dependencies point inward only:

```
Presentation  ->  Infrastructure  ->  Application  ->  Domain
(controllers,     (Doctrine, Telegram,  (commands, queries,  (entities, repository
 console, admin)   Messenger, grabbers)  handlers, ports)      ports, no framework)
```

| Layer | Path | Rule |
|---|---|---|
| Domain | `src/Domain/<Module>` | framework-free: entities, value objects, repository ports |
| Application | `src/Application/<Module>` | use cases; depends on Domain and on **ports** only (for example `MessageBus`, `TelegramSender`) |
| Infrastructure | `src/Infrastructure` | adapters implementing the ports: Doctrine repositories and XML mappings, Messenger, Telegram, source grabbers (`SourceGrabber`: Djinni, DOU) |
| Presentation | `src/Presentation/<Module>` | REST controllers, Sonata admin, console commands, Telegram bot controllers |

Modules: Company, GrabLog, Note, Source, User, Vacancy. Commands, queries and events go through the `MessageBus` port; handlers implement `CommandHandlerInterface` or `QueryHandlerInterface`. Messages marked `*PriorityAsyncMessageInterface` are routed to the matching queue and consumed by the `worker`.

Other top-level folders: `assets/` (Vue + TypeScript), `templates/`, `translations/`, `migrations/`, `config/`, `bin/` (deploy and cron scripts), `docs/`, `tests/`.

The layer rules are enforced by a script that runs on the host:

```bash
bash .claude/skills/ddd-structure-fix/scripts/check.sh   # exit code 1 on violations
```

It is also available as the Claude Code skill `/ddd-structure-fix`; the last result is in [docs/ddd-structure-fix-result.md](docs/ddd-structure-fix-result.md). Other project skills in `.claude/skills/`: `fix-migrations` (format Doctrine migrations) and `main-dockerfile-fix` (check the production Dockerfile).

## Telegram notifications

After a grab, a user gets a report in Telegram: how many vacancies were created and updated, with a link to the unviewed ones.

1. Create a bot with [@BotFather](https://t.me/BotFather) and put the token into `.env.local` as `TELEGRAM_API_TOKEN` (empty = notifications are skipped).
2. Give every user a chat ID: enter a Telegram username in the admin and run `bin/console app:telegram:link-users`, or let the bot link the user on `/start`.
3. Enable **Allow Telegram notifications** on the user in the admin (off by default).

Messages are sent **asynchronously**: `SendTelegramNotificationAsyncCommand($chatId, $message)` goes to the `queue_standard_priority` transport and is delivered by the `worker`. A failed delivery is retried 5 times with growing delays, then moved to the `failed` transport (`bin/console messenger:failed:show`). The bot also answers `/start`, `/grab` and other commands through a webhook.

More: [docs/telegram-notifications.md](docs/telegram-notifications.md) (token, chat IDs, async sending) and [docs/telegram-webhook.md](docs/telegram-webhook.md) (webhook, bot commands).

## Scheduled grabbing

In production cron runs `app:source:grab-all` every hour from 08:00 to 20:00 through `bin/source-grab-all.sh`. Installation and troubleshooting: [docs/crontab.md](docs/crontab.md).

## Deployment

The production image is built from the root `Dockerfile` (targets `app` and `nginx`). The stack in `docker-compose.prod.yaml` has php-fpm, the Messenger worker, nginx and MySQL, and runs behind a shared Traefik reverse proxy.

Full server setup (Ubuntu 24.04, Docker, Traefik, first deploy, backups, domains, more projects): [vps-setup/docs/vps-setup.md](../vps-setup/docs/vps-setup.md).

**First deploy** (server already prepared):

```bash
cd /home/dad/workspace/www/vacancy-grabber
cp .env.prod.dist .env.prod.local   # fill in real values
make prod-up                        # build images and start the stack
make prod-jwt-keys                  # one-time: JWT keypair (stored in the "jwt" volume)
make prod-migrate                   # DB migrations and Messenger transports
```

**Update**:

```bash
bin/deploy.sh --remote      # from your machine; `bin/deploy.sh` on the server, or `make prod-deploy`
```

It pulls, rebuilds, migrates and registers the Telegram webhook. Options and the manual steps: [docs/deploy.md](docs/deploy.md).

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `network reverse-proxy declared as external, but could not be found` | create the external networks, see [Prerequisites](#prerequisites) |
| Tests: login returns 500 "encode the JWT token" | the key is passphrase-protected and `JWT_PASSPHRASE` is missing in `.env.test.local` |
| Tests: `Table ... doesn't exist` | the test database has no schema, run `make migrate` |
| Tests: `429 Too many requests` | clear the limiter: `bin/console cache:pool:clear cache.rate_limiter cache.app --env=test` |
| Containers and volumes got new names | `COMPOSE_PROJECT_NAME` changed, run `docker compose down` with the old setup first ([docs/docker-env.md](docs/docker-env.md)) |
| No Telegram report | check `TELEGRAM_API_TOKEN`, the user's chat ID and notifications flag, and that the `worker` is running |

## Documentation

| File | Topic |
|---|---|
| [docs/docker-env.md](docs/docker-env.md) | `COMPOSE_*` variables and how they are used |
| [docs/telegram-notifications.md](docs/telegram-notifications.md) | bot token, chat IDs, async sending |
| [docs/telegram-webhook.md](docs/telegram-webhook.md) | Telegram webhook and bot commands |
| [docs/crontab.md](docs/crontab.md) | scheduled grabbing on the server |
| [docs/deploy.md](docs/deploy.md) | production deploy |
| [docs/ddd-structure-fix-result.md](docs/ddd-structure-fix-result.md) | result of the last DDD layer check |

## License

Proprietary. All rights reserved.

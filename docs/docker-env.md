# Docker Compose environment variables

The variables used by `docker-compose.yaml` live in the root `.env` file.
Docker Compose loads that file automatically, so no `--env-file` flag is needed for dev.

They used to be in `.docker/.env.dist`. Nothing ever loaded that file, so the compose defaults were always used.
The file has been removed.

| Variable | Default in compose | Used for |
|---|---|---|
| `COMPOSE_PROJECT_NAME` | `vacancy-grabber` | Compose project name (prefix of containers, networks, volumes) |
| `COMPOSE_PHP_VERSION` | `8.4` | build arg `PHP_VERSION` of `.docker/php-fpm/Dockerfile` |
| `COMPOSE_USER_ID` | `1000` | build arg `USER_ID` (uid of the `app` user in the container) |
| `COMPOSE_GROUP_ID` | `1000` | build arg `GROUP_ID` (gid of the `app` user in the container) |

Renames compared with the old file: `PHP_VERSION` -> `COMPOSE_PHP_VERSION`, `USER_ID` -> `COMPOSE_USER_ID`, `GROUP_ID` -> `COMPOSE_GROUP_ID`.
The build-arg names inside the Dockerfiles are unchanged; only the source variables read in `docker-compose.yaml` were renamed.

## Notes

- **Rebuild after a change.** Changing `COMPOSE_PHP_VERSION`, `COMPOSE_USER_ID` or `COMPOSE_GROUP_ID` needs
  `docker compose build --no-cache php-fpm` and then `docker compose up -d`.
- **Project name change.** Without `COMPOSE_PROJECT_NAME` the project name was the directory name (`vacancy-grabber.local`).
  Now it is `vacancy-grabber`, so containers, networks and named volumes get new names.
  Run `docker compose down` with the old setup first. Named volumes under the old name are not reused.
  The MySQL and Redis data are bind mounts under `.docker/`, so they are not affected.
- **Local overrides.** Compose reads only `.env`, not `.env.local`. Edit `.env` or export the variable in the shell,
  e.g. `COMPOSE_USER_ID=$(id -u) COMPOSE_GROUP_ID=$(id -g) docker compose build php-fpm`.
  A shell variable takes precedence over `.env`.
- **Shared file.** `.env` is also parsed by Symfony, by webpack (`webpack.config.js`) and copied by the root `Dockerfile`.
  The extra `COMPOSE_*` keys are harmless for all of them.
- **Prod.** `docker-compose.prod.yaml` uses `--env-file .env.prod.local` and does not use these variables.
- **Checks.** `docker compose config` shows the resolved values.
  the global skill `/fix-dockerfile` (`~/.claude/skills/fix-dockerfile/scripts/check.sh`) still works, because its regex matches `PHP_VERSION:-x.y` inside `COMPOSE_PHP_VERSION:-x.y`.

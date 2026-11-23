# Plan: move compose env vars from `.docker/.env.dist` to root `.env`

## Context
`.docker/.env.dist` is never loaded by anything (no `env_file`, `--env-file`, makefile target or copy script), so `docker-compose.yaml` always falls back to its defaults. Docker Compose auto-loads the root `.env`, so moving the variables there makes them effective. The root `.env` already has commented placeholders (lines 1-5, "todo: put in this filee") using the `COMPOSE_` prefix.

## Mapping
| Source (`.docker/.env.dist`) | Target (`.env`) | Value |
|---|---|---|
| COMPOSE_PROJECT_NAME | COMPOSE_PROJECT_NAME | vacancy-grabber |
| PHP_VERSION | COMPOSE_PHP_VERSION | 8.4 |
| USER_ID | COMPOSE_USER_ID | 1000 |
| GROUP_ID | COMPOSE_GROUP_ID | 1000 |

## Changes
1. `.env` (lines 1-5): replace the todo and commented lines with a short comment block plus the four active variables above. Do not touch the existing uncommitted diffs elsewhere.
2. `docker-compose.yaml:6-8`: keep the build-arg names that the Dockerfile expects, and change only the source variables:
   - `PHP_VERSION: ${COMPOSE_PHP_VERSION:-8.4}`
   - `USER_ID: ${COMPOSE_USER_ID:-1000}`
   - `GROUP_ID: ${COMPOSE_GROUP_ID:-1000}`
   `.docker/php-fpm/Dockerfile` and the root `Dockerfile` stay unchanged (build args keep their names).
3. `.docker/.env.dist`: remove the four moved variables. The file would be empty, so delete it. Also remove the now-pointless `/.docker/.env` line from `.gitignore:4`.
4. `.claude/skills/main-dockerfile-fix/scripts/check.sh:45-53` (and `SKILL.md:23`): update the parser from `PHP_VERSION:-x.y` to `COMPOSE_PHP_VERSION:-x.y`, otherwise the PHP version drift check breaks.
5. Docs: add `docs/docker-env.md` covering:
   - the variable mapping table
   - why they live in the root `.env` (Compose auto-loads it, `.docker/.env.dist` was never loaded)
   - how to override locally (`.env.local` is not read by Compose, so edit `.env` or export the variable in the shell)
   - that `docker compose build --no-cache php-fpm` (or `make` rebuild) is needed after changing `COMPOSE_PHP_VERSION`, `COMPOSE_USER_ID` or `COMPOSE_GROUP_ID`
   - that `COMPOSE_PROJECT_NAME` renames containers, networks and volumes (default was the directory name `vacancy-grabber.local`), so run `docker compose down` first. Volumes under the old name, such as the MySQL data, will not be reused, so back up or rename them before switching
   - that `.env` is also parsed by Symfony, webpack (`webpack.config.js:16`) and `COPY .env` in the root `Dockerfile:64`, and that the extra `COMPOSE_*` keys are harmless there
   - that prod (`docker-compose.prod.yaml`, `--env-file .env.prod.local`) is unaffected

## Verification
- `docker compose config` shows project name `vacancy-grabber` and build args `PHP_VERSION=8.4`, `USER_ID=1000`, `GROUP_ID=1000`.
- Temporarily run `COMPOSE_PHP_VERSION=8.3 docker compose config` to confirm the override is picked up.
- `docker compose -f docker-compose.prod.yaml --env-file .env.prod.local config` still resolves.
- Run `.claude/skills/main-dockerfile-fix/scripts/check.sh` and confirm it still finds the PHP version.
- `grep -rn "\bPHP_VERSION\|USER_ID\|GROUP_ID" docker-compose.yaml .env` shows only the intended names.

## Open note
The container and volume names change with the new project name; this is covered in the docs.

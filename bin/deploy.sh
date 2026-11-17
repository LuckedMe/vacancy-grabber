#!/usr/bin/env bash
#
# Deploy the production stack (docs/deploy.md): pull the code, rebuild the images, restart the containers,
# run the migrations, register the Telegram webhook, check the result.
#
# Run it ON THE SERVER from the project folder:
#
#   cd /home/dad/workspace/www/vacancy-grabber && bin/deploy.sh
#
# or from your machine (it runs the server's copy of this script over ssh):
#
#   bin/deploy.sh --remote
#
# Options:
#   --branch NAME   branch to deploy (default: main)
#   --no-backup     do not back up the database before the migrations
#   --force         rebuild and restart even when there is nothing new to pull
#   --dry-run       show what would be done, change nothing (git fetch still runs)
#   --remote        run on the server over ssh (DEPLOY_HOST, DEPLOY_DIR; the other options are passed on)
#   -h, --help      this help
#
# Environment (all optional):
#   DEPLOY_HOST     ssh target for --remote           (default: dad@100.91.57.78, Tailscale IP)
#   DEPLOY_DIR      project folder on the server      (default: /home/dad/workspace/www/vacancy-grabber)
#   BACKUP_SCRIPT   database backup script            (default: /home/dad/workspace/backups/backup-vacancy-grabber.sh)
#   APP_URL         site checked at the end            (default: https://vacancy-grabber.lucked.me/)
#
# The script stops at the first failing step (set -e). It never touches .env.prod.local: when
# .env.prod.dist has variables that are missing there it stops BEFORE pulling and lists them.

set -Eeuo pipefail

DEPLOY_HOST="${DEPLOY_HOST:-dad@100.91.57.78}"
DEPLOY_DIR="${DEPLOY_DIR:-/home/dad/workspace/www/vacancy-grabber}"
BACKUP_SCRIPT="${BACKUP_SCRIPT:-/home/dad/workspace/backups/backup-vacancy-grabber.sh}"
APP_URL="${APP_URL:-https://vacancy-grabber.lucked.me/}"

BRANCH=main
BACKUP=1
FORCE=0
DRY_RUN=0
REMOTE=0
ARGS=()

usage() {
    sed -n '2,/^$/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//; /^#!/d'
}

# the whole script is inside main() and called on the last line: `git pull` may replace this very file while it
# runs, and bash would otherwise read the new version from the middle of the old one
main() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --branch)    BRANCH="${2:?--branch needs a name}"; ARGS+=("$1" "$2"); shift 2 ;;
            --no-backup) BACKUP=0; ARGS+=("$1"); shift ;;
            --force)     FORCE=1; ARGS+=("$1"); shift ;;
            --dry-run)   DRY_RUN=1; ARGS+=("$1"); shift ;;
            --remote)    REMOTE=1; shift ;;
            -h|--help)   usage; return 0 ;;
            *)           echo "Unknown option: $1 (see --help)" >&2; return 2 ;;
        esac
    done

    if [[ ${REMOTE} -eq 1 ]]; then
        echo ">> ssh ${DEPLOY_HOST}: ${DEPLOY_DIR}/bin/deploy.sh ${ARGS[*]:-}"
        # -t: colours and a prompt-free, line-by-line output
        exec ssh -t "${DEPLOY_HOST}" "cd '${DEPLOY_DIR}' && bin/deploy.sh ${ARGS[*]:-}"
    fi

    APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "${APP_DIR}"

    DEPLOYED_FILE="${APP_DIR}/var/.last-deployed-commit"
    COMPOSE=(docker compose -f docker-compose.prod.yaml --env-file .env.prod.local)
    PHP=("${COMPOSE[@]}" exec -T php-fpm php bin/console)

    step()  { printf '\n\033[1;36m== %s\033[0m\n' "$*"; }
    info()  { printf '   %s\n' "$*"; }
    warn()  { printf '\033[1;33m!! %s\033[0m\n' "$*" >&2; }
    fail()  { printf '\033[1;31mxx %s\033[0m\n' "$*" >&2; exit 1; }
    # run a changing command (only echoed with --dry-run)
    run()   { printf '   $ %s\n' "$*"; [[ ${DRY_RUN} -eq 1 ]] || "$@"; }
    # value of a variable from .env.prod.local (not sourced: values may contain spaces, quotes and $)
    env_get() { grep -E "^$1=" .env.prod.local | tail -1 | cut -d= -f2- | sed -E "s/^['\"]//; s/['\"]$//" || true; }

    [[ ${DRY_RUN} -eq 1 ]] && warn "DRY RUN: nothing is changed"

    # --- 0. checks --------------------------------------------------------------------------------------------
    step "Checks"
    command -v docker >/dev/null || fail "docker is not installed"
    command -v git >/dev/null    || fail "git is not installed"
    [[ -f docker-compose.prod.yaml ]] || fail "docker-compose.prod.yaml not found in ${APP_DIR}"
    [[ -f .env.prod.local ]]          || fail ".env.prod.local not found (cp .env.prod.dist .env.prod.local and fill it in)"

    # one deploy at a time
    exec 9>/tmp/vacancy-grabber-deploy.lock
    flock -n 9 || fail "another deploy is running"

    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        git status --short --untracked-files=no
        fail "tracked files are changed on the server (someone edited the code by hand): look at 'git diff', 'git stash' them"
    fi

    # --- 1. what is coming ------------------------------------------------------------------------------------
    step "Fetch origin/${BRANCH}"
    git fetch --quiet origin "${BRANCH}"
    REMOTE_REF="origin/${BRANCH}"
    HEAD_SHA="$(git rev-parse HEAD)"
    TARGET="$(git rev-parse --short "${REMOTE_REF}")"
    CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

    # The code on disk is not what runs: the images are baked from the code of the last deploy, and somebody may
    # have pulled by hand without rebuilding. So the commit of the last finished deploy is kept in a marker file
    # and everything is compared with IT (no marker, e.g. the first run: the checked-out commit is assumed).
    LAST="$(cat "${DEPLOYED_FILE}" 2>/dev/null || true)"
    if [[ -n "${LAST}" ]] && ! git cat-file -e "${LAST}^{commit}" 2>/dev/null; then
        LAST=""
    fi
    BASE="${LAST:-${HEAD_SHA}}"
    LAST_LABEL="unknown"
    [[ -z "${LAST}" ]] || LAST_LABEL="$(git rev-parse --short "${LAST}")"
    PREVIOUS="$(git rev-parse --short "${BASE}")"
    info "checked out: $(git rev-parse --short HEAD) (${CURRENT_BRANCH}); last deploy: ${LAST_LABEL}; deploying: ${TARGET} (${BRANCH})  <- rollback point: ${PREVIOUS}"

    if [[ "${HEAD_SHA}" == "$(git rev-parse "${REMOTE_REF}")" && "${CURRENT_BRANCH}" == "${BRANCH}" && ${FORCE} -eq 0 ]]; then
        if [[ "${LAST}" == "${HEAD_SHA}" ]]; then
            info "already up to date, nothing to deploy (use --force to rebuild anyway)"
            "${COMPOSE[@]}" ps
            return 0
        fi
        warn "the code is up to date, but the running images were not built from it (last deploy: ${LAST_LABEL}): rebuilding"
    fi

    git log --oneline --no-decorate "${BASE}..${REMOTE_REF}" | sed 's/^/   + /' || true
    CHANGED="$(git diff --name-only "${BASE}..${REMOTE_REF}")"
    MIGRATIONS="$(grep -c '^migrations/' <<<"${CHANGED}" || true)"
    [[ "${MIGRATIONS}" -gt 0 ]] && info "migrations in this release: ${MIGRATIONS} file(s) changed"
    grep -qE '^(composer\.(json|lock)|package\.json|yarn\.lock)$' <<<"${CHANGED}" \
        && info "dependencies changed: the build will take longer" || true

    # --- 2. new variables in .env.prod.dist must exist in .env.prod.local BEFORE the containers are recreated ---
    step "Check .env.prod.local"
    MISSING="$(comm -23 \
        <(git show "${REMOTE_REF}:.env.prod.dist" | grep -oE '^[A-Z][A-Z0-9_]*=' | sort -u) \
        <(grep -oE '^[A-Z][A-Z0-9_]*=' .env.prod.local | sort -u) | tr -d '=')"
    if [[ -n "${MISSING}" ]]; then
        warn "variables of .env.prod.dist that are missing in .env.prod.local:"
        sed 's/^/     /' <<<"${MISSING}" >&2
        fail "add them to .env.prod.local (see .env.prod.dist and docs/deploy.md), then run the deploy again"
    fi
    info "ok, no variables are missing"

    # --- 3. backup before the migrations ----------------------------------------------------------------------
    if [[ "${MIGRATIONS}" -gt 0 && ${BACKUP} -eq 1 ]]; then
        step "Back up the database"
        [[ -x "${BACKUP_SCRIPT}" ]] || fail "backup script ${BACKUP_SCRIPT} not found or not executable (--no-backup to skip)"
        run "${BACKUP_SCRIPT}"
    fi

    # --- 4. code ----------------------------------------------------------------------------------------------
    step "Get the code"
    if [[ "${CURRENT_BRANCH}" != "${BRANCH}" ]]; then
        run git checkout "${BRANCH}"
    fi
    run git pull --ff-only origin "${BRANCH}"

    # --- 5. images and containers -----------------------------------------------------------------------------
    step "Build the images and restart the stack"
    run "${COMPOSE[@]}" up -d --build --remove-orphans

    # --- 6. migrations ----------------------------------------------------------------------------------------
    step "Migrations"
    run "${PHP[@]}" doctrine:migrations:migrate --no-interaction --allow-no-migration
    run "${PHP[@]}" messenger:setup-transports --no-interaction

    # --- 7. Telegram webhook ----------------------------------------------------------------------------------
    step "Telegram webhook"
    if [[ -n "$(env_get TELEGRAM_API_TOKEN)" && -n "$(env_get TELEGRAM_WEBHOOK_URL)" ]]; then
        # a failure here must not fail the deploy (Telegram may be unreachable): the site already runs
        run "${PHP[@]}" telegram:webhook:update --no-interaction || warn "telegram:webhook:update failed, run it by hand"
        run "${PHP[@]}" telegram:button:update --no-interaction  || warn "telegram:button:update failed, run it by hand"
    else
        info "TELEGRAM_API_TOKEN / TELEGRAM_WEBHOOK_URL are empty: skipped"
    fi

    # --- 8. check ---------------------------------------------------------------------------------------------
    step "Check"
    run "${COMPOSE[@]}" ps
    if [[ ${DRY_RUN} -eq 0 ]]; then
        sleep 5
        CODE="$(curl -s -o /dev/null -m 20 -w '%{http_code}' "${APP_URL}" || true)"
        case "${CODE}" in
            200|301|302) info "${APP_URL} -> HTTP ${CODE}" ;;
            *)           warn "${APP_URL} -> HTTP ${CODE:-none}: look at 'make prod-logs'; rollback: git checkout ${PREVIOUS} && make prod-up" ;;
        esac
        docker image prune -f >/dev/null && info "old images pruned"
    fi

    if [[ ${DRY_RUN} -eq 0 ]]; then
        mkdir -p "$(dirname "${DEPLOYED_FILE}")"
        git rev-parse HEAD > "${DEPLOYED_FILE}"
    fi

    step "Done: ${PREVIOUS} -> ${TARGET}"
}

main "$@"
exit $?

#!/usr/bin/env bash
#
# Cron job: grab all sources (app:source:grab-all) in the production php-fpm container.
# Runs on the HOST, started from the crontab of the server user, see docs/crontab.md:
#
#   0 8-20 * * * /home/dad/workspace/www/vacancy-grabber/config/crontab/source-grab-all.sh
#
# Every run writes its own log file var/log/crontab/grab-all-YYYY-MM-DD_HHMM.log;
# the log files older than LOG_KEEP_DAYS days are deleted before the grab.

set -u

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOG_DIR="${APP_DIR}/var/log/crontab"
LOG_KEEP_DAYS=7
LOCK_FILE=/tmp/vacancy-grabber-grab-all.lock

mkdir -p "${LOG_DIR}"

# delete the old logs (the grab must run even if it fails)
find "${LOG_DIR}" -name 'grab-all-*.log' -mtime "+${LOG_KEEP_DAYS}" -delete || true

LOG_FILE="${LOG_DIR}/grab-all-$(date +%F_%H%M).log"

cd "${APP_DIR}" || exit 1

# skip the run when the previous one is still going
exec 9>"${LOCK_FILE}"
flock -n 9 || { echo "$(date '+%F %T') previous run is still going, skipped" >> "${LOG_FILE}"; exit 0; }

docker compose -f docker-compose.prod.yaml --env-file .env.prod.local \
    exec -T php-fpm php bin/console app:source:grab-all --no-interaction >> "${LOG_FILE}" 2>&1

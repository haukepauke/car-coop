#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
APP_ENV="${APP_ENV:-prod}"
APP_DEBUG="${APP_DEBUG:-0}"
SUPERVISOR_PROGRAM="${SUPERVISOR_PROGRAM:-car-coop-messenger}"

RUN_COMPOSER=1
RUN_MIGRATIONS=1
RUN_ASSETS=1
RESTART_WORKER=0
STOP_WORKERS_FIRST=0

usage() {
    cat <<'EOF'
Usage: deploy/manual-deploy.sh [options]

Deploy Car Coop on a non-Docker host by running the standard production steps.

Options:
  --project-dir DIR        Deploy a different checkout instead of this script's parent directory
  --php-bin PATH           PHP binary to use
  --composer-bin PATH      Composer binary to use
  --skip-composer          Skip composer install
  --skip-migrations        Skip doctrine migrations
  --skip-assets            Skip asset-map compilation
  --restart-worker         Restart the Supervisor messenger worker after deploy
  --stop-workers-first     Stop Symfony messenger workers before deploy
  --worker-program NAME    Supervisor program name (default: car-coop-messenger)
  -h, --help               Show this help text

Environment overrides:
  APP_ENV                  Defaults to prod
  APP_DEBUG                Defaults to 0
  PHP_BIN                  Defaults to php
  COMPOSER_BIN             Defaults to composer
  SUPERVISOR_PROGRAM       Defaults to car-coop-messenger

Examples:
  deploy/manual-deploy.sh
  deploy/manual-deploy.sh --restart-worker
  deploy/manual-deploy.sh --stop-workers-first --restart-worker --worker-program my-worker
EOF
}

log() {
    printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

run() {
    log "$*"
    "$@"
}

while (($# > 0)); do
    case "$1" in
        --project-dir)
            [[ $# -ge 2 ]] || fail "--project-dir requires a value"
            PROJECT_ROOT="$2"
            shift 2
            ;;
        --php-bin)
            [[ $# -ge 2 ]] || fail "--php-bin requires a value"
            PHP_BIN="$2"
            shift 2
            ;;
        --composer-bin)
            [[ $# -ge 2 ]] || fail "--composer-bin requires a value"
            COMPOSER_BIN="$2"
            shift 2
            ;;
        --skip-composer)
            RUN_COMPOSER=0
            shift
            ;;
        --skip-migrations)
            RUN_MIGRATIONS=0
            shift
            ;;
        --skip-assets)
            RUN_ASSETS=0
            shift
            ;;
        --restart-worker)
            RESTART_WORKER=1
            shift
            ;;
        --stop-workers-first)
            STOP_WORKERS_FIRST=1
            shift
            ;;
        --worker-program)
            [[ $# -ge 2 ]] || fail "--worker-program requires a value"
            SUPERVISOR_PROGRAM="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            fail "Unknown option: $1"
            ;;
    esac
done

[[ -d "$PROJECT_ROOT" ]] || fail "Project directory does not exist: $PROJECT_ROOT"
cd "$PROJECT_ROOT"

[[ -f "bin/console" ]] || fail "Could not find bin/console in $PROJECT_ROOT"

require_command "$PHP_BIN"
if (( RUN_COMPOSER )); then
    require_command "$COMPOSER_BIN"
fi
if (( RESTART_WORKER )); then
    require_command supervisorctl
fi

export APP_ENV APP_DEBUG

log "Deploying Car Coop from $PROJECT_ROOT"
log "Using APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG"

mkdir -p var/cache var/log var/uploads/messages public/uploads public/assets

if (( STOP_WORKERS_FIRST )); then
    run "$PHP_BIN" bin/console messenger:stop-workers
fi

if (( RUN_COMPOSER )); then
    run "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
fi

run "$PHP_BIN" bin/console cache:clear --no-warmup
run "$PHP_BIN" bin/console cache:warmup
run "$PHP_BIN" bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

if (( RUN_MIGRATIONS )); then
    run "$PHP_BIN" bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

if (( RUN_ASSETS )); then
    run "$PHP_BIN" bin/console asset-map:compile
fi

if (( RESTART_WORKER )); then
    run supervisorctl restart "$SUPERVISOR_PROGRAM"
fi

log "Deployment finished successfully."

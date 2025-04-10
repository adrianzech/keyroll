#!/bin/sh
# This script prepares the environment and runs the main container command (php-fpm).

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Environment Variable Setup ---

APP_ENV="${APP_ENV:-prod}"
CURRENT_USER=$(id -un)
CURRENT_GROUP=$(id -gn)
CURRENT_UID=$(id -u)
CURRENT_GID=$(id -g)


# Function to log messages (POSIX compliant)
log() {
    # Print timestamp and message to standard output
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [Entrypoint ${CURRENT_USER}(${CURRENT_UID})] $1"
}

log "--- Entrypoint Start ---"
log "Running as: ${CURRENT_USER}(${CURRENT_UID}):${CURRENT_GROUP}(${CURRENT_GID})"
log "APP_ENV=${APP_ENV}"
log "WORKDIR=$(pwd)"

# --- DATABASE_URL Construction ---
# Check if DATABASE_URL is already set. If not, try constructing it.
if [ -z "${DATABASE_URL}" ]; then
  log "INFO: DATABASE_URL not set, attempting to construct from KEYROLL_DATABASE_* variables..."

  missing_vars=""
  if [ -z "$KEYROLL_DATABASE_HOST" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_HOST"; fi
  if [ -z "$KEYROLL_DATABASE_PORT" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PORT"; fi
  if [ -z "$KEYROLL_DATABASE_NAME" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_NAME"; fi
  if [ -z "$KEYROLL_DATABASE_USER" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_USER"; fi
  if [ -z "$KEYROLL_DATABASE_PASSWORD" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PASSWORD"; fi

  if [ -n "$missing_vars" ]; then
    log "ERROR: Missing required environment variables to construct DATABASE_URL:" >&2
    log " $missing_vars" >&2
    log "Please set these variables or provide a complete DATABASE_URL." >&2
    exit 1
  fi

  DATABASE_URL="mysql://${KEYROLL_DATABASE_USER}:${KEYROLL_DATABASE_PASSWORD}@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}?charset=utf8mb4"
  export DATABASE_URL
  masked_db_url="mysql://${KEYROLL_DATABASE_USER}:***@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}"
  log "INFO: DATABASE_URL constructed: ${masked_db_url}"
else
    masked_db_url=$(echo "$DATABASE_URL" | sed 's|\(mysql://.*:\)[^@]*\(@.*\)|\1***\2|')
    log "INFO: Using provided DATABASE_URL: ${masked_db_url}"
    export DATABASE_URL
fi

# --- Command Handling ---
# Determine if setup is needed based on the command ($1)
run_setup=0
case "$1" in
    # If command starts with '-', assume it's an option for php-fpm
    -*)
        run_setup=1
        ;;
    # If command is php-fpm itself (the default CMD), run setup
    php-fpm)
        run_setup=1
        ;;
    # If command is specific like bin/console or php, run setup
    bin/console|php)
        run_setup=1
        ;;
    *)
        run_setup=0
        ;;
esac

if [ "$run_setup" = 1 ]; then
    log "INFO: Running application setup steps..."

    # --- Wait for Database ---
    db_wait_needed=0
    case "$DATABASE_URL" in
        *sqlite*)
            log "INFO: Using SQLite, skipping database wait and migrations."
            db_wait_needed=0
            ;;
        "")
            log "INFO: No DATABASE_URL configured, skipping database wait and migrations."
            db_wait_needed=0
            ;;
        *)
            db_wait_needed=1
            ;;
    esac

    if [ "$db_wait_needed" = 1 ]; then
        log "INFO: Waiting for database connection..."
        ATTEMPTS=0
        MAX_ATTEMPTS=60
        until php bin/console dbal:run-sql "SELECT 1" --env="$APP_ENV" --quiet --no-interaction > /dev/null 2>&1; do
            ATTEMPTS=$((ATTEMPTS+1))
            if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
                 log "ERROR: Database connection failed after $MAX_ATTEMPTS attempts." >&2
                 exit 1
            fi
            log "DEBUG: Database unavailable, waiting 1 second... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 1
        done
        log "INFO: Database connection successful."

        # Apply database migrations if migrations exist
        if [ -d migrations ] && find migrations -maxdepth 1 -name '*.php' -print -quit | grep -q .; then
            log "INFO: Applying database migrations..."
            php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
        else
            log "INFO: No migrations directory found or no *.php files in it, skipping migrations."
        fi
    fi

    # --- Perform remaining setup tasks ---
    log "INFO: Performing remaining setup tasks..."

    # Create var/ssh directory if it doesn't exist
    mkdir -p var/ssh

    # Generate SSH key if it doesn't exist
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        log "INFO: Generating KeyRoll SSH key..."
        php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    else
         log "INFO: SSH key 'var/ssh/keyroll_ed25519' already exists."
    fi

    # Clear/Warmup Cache only for prod/relevant environments
    if [ "$APP_ENV" != "dev" ]; then
      log "INFO: Clearing and warming up application cache for ${APP_ENV}..."
      php bin/console cache:clear --env="$APP_ENV"
      php bin/console cache:warmup --env="$APP_ENV"
      log "INFO: Cache warmed up."
    else
      log "INFO: Skipping cache warmup for APP_ENV=${APP_ENV}."
    fi

    log "INFO: Application setup complete."

else
    log "INFO: Command '$1' does not trigger app setup. Executing directly."
fi

# --- Execute Main Command ---
log "INFO: Executing command: $*"
exec "$@"

# Fallback if exec fails (should not happen normally)
exit $?

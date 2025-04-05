#!/bin/bash
# This script prepares the environment and runs the main container command.

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Environment Variable Setup ---
APP_ENV="${APP_ENV:-prod}"
APP_USER="${APP_USER:-keyroll}"
APP_GROUP="${APP_GROUP:-keyroll}"
APP_UID="${APP_UID:-1000}"
APP_GID="${APP_GID:-1000}"

# Function to log messages
log() {
    echo "[Entrypoint] $1"
}

log "--- Entrypoint Start ---"
log "User: $(id -u):$(id -g)"
log "APP_USER=${APP_USER}, APP_GROUP=${APP_GROUP}, APP_UID=${APP_UID}, APP_GID=${APP_GID}"
log "APP_ENV=${APP_ENV}"
log "WORKDIR=$(pwd)"

# --- DATABASE_URL Construction ---
# Check if DATABASE_URL is already set. If not, try constructing it.
if [ -z "${DATABASE_URL}" ]; then
  log "INFO: DATABASE_URL not set, attempting to construct from KEYROLL_DATABASE_* variables..."

  # Check for required environment variables for construction
  missing_vars=""
  if [ -z "$KEYROLL_DATABASE_HOST" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_HOST"; fi
  if [ -z "$KEYROLL_DATABASE_PORT" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PORT"; fi
  if [ -z "$KEYROLL_DATABASE_NAME" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_NAME"; fi
  if [ -z "$KEYROLL_DATABASE_USER" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_USER"; fi
  if [ -z "$KEYROLL_DATABASE_PASSWORD" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PASSWORD"; fi

  # Exit if any required variables for construction are missing
  if [ -n "$missing_vars" ]; then
    log "ERROR: Missing required environment variables to construct DATABASE_URL:" >&2
    log " $missing_vars" >&2
    log "Please set these variables or provide a complete DATABASE_URL." >&2
    exit 1
  fi

  # Construct the MySQL/MariaDB DATABASE_URL
  DATABASE_URL="mysql://${KEYROLL_DATABASE_USER}:${KEYROLL_DATABASE_PASSWORD}@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}?charset=utf8mb4"
  export DATABASE_URL
  masked_db_url="mysql://${KEYROLL_DATABASE_USER}:***@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}"
  log "INFO: DATABASE_URL constructed: ${masked_db_url}"
else
    # Mask password if displaying existing URL
    masked_db_url=$(echo "$DATABASE_URL" | sed -E 's/(mysql:\/\/.*:)([^@]+)(@.*)/\1***\3/')
    log "INFO: Using provided DATABASE_URL: ${masked_db_url}"
    # Ensure DATABASE_URL is exported even if provided externally
    export DATABASE_URL
fi

# --- Command Handling ---
# Check if the first argument looks like an option (e.g., `-f`, `--some-option`)
# Or if it's one of the commands that should be run as APP_USER after setup
run_setup=0
if [ "${1#-}" != "$1" ]; then
    # Command starts with a hyphen, assume it's an option for the default CMD
    run_setup=1
elif [ "$1" = 'apache2-foreground' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    # Command is one that requires the application runtime environment
    run_setup=1
fi

if [ "$run_setup" = 1 ]; then
    log "INFO: Running application setup steps before command: $1"

    # --- Wait for Database ---
    # Check only if DATABASE_URL is set and not SQLite
    if [[ -n "$DATABASE_URL" ]] && [[ "$DATABASE_URL" != *"sqlite"* ]]; then
        log "INFO: Waiting for database connection..."
        ATTEMPTS=0
        MAX_ATTEMPTS=60 # Wait up to 60 seconds
        # Use the exported DATABASE_URL implicitly by bin/console
        until gosu "${APP_USER}" php bin/console dbal:run-sql "SELECT 1" --env="$APP_ENV" --quiet --no-interaction > /dev/null 2>&1; do
            ATTEMPTS=$((ATTEMPTS+1))
            if [ $ATTEMPTS -ge $MAX_ATTEMPTS ]; then
                 log "ERROR: Database connection failed after $MAX_ATTEMPTS attempts." >&2
                 exit 1
            fi
            log "DEBUG: Database unavailable, waiting 1 second... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 1
        done
        log "INFO: Database connection successful."
    else
        log "INFO: No external DATABASE_URL configured or using SQLite, skipping database wait."
    fi

    # --- Run As App User ---
    log "INFO: Performing setup tasks as user ${APP_USER}..."

    # Create var/ssh directory if it doesn't exist (should already exist from Dockerfile)
    mkdir -p var/ssh # Ensure it exists, Dockerfile step might run before volume mount overlays it
    chown "${APP_USER}:${APP_GROUP}" var/ssh # Ensure ownership just in case

    # Generate SSH key if it doesn't exist (run as APP_USER)
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        log "INFO: Generating KeyRoll SSH key..."
        gosu "${APP_USER}" php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    else
         log "INFO: SSH key already exists."
    fi

    # Apply database migrations if configured and migrations exist (run as APP_USER)
    # Check if migrations dir exists AND contains PHP files
    if [[ -n "$DATABASE_URL" ]] && [[ "$DATABASE_URL" != *"sqlite"* ]] && [ -d migrations ] && ls migrations/*.php &> /dev/null; then
        log "INFO: Applying database migrations..."
        gosu "${APP_USER}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
    elif [[ "$DATABASE_URL" == *"sqlite"* ]]; then
         log "INFO: Using SQLite, skipping migrations."
    elif [ -z "$DATABASE_URL" ]; then
        log "INFO: No database configured, skipping migrations."
    else
        log "INFO: No migrations directory found or no migrations exist, skipping."
    fi

    # Clear/Warmup Cache (run as APP_USER) only for prod/relevant environments
    if [ "$APP_ENV" != "dev" ]; then
      log "INFO: Clearing and warming up application cache for ${APP_ENV}..."
      gosu "${APP_USER}" php bin/console cache:clear --env="$APP_ENV"
      gosu "${APP_USER}" php bin/console cache:warmup --env="$APP_ENV"
      log "INFO: Cache warmed up."
    else
      log "INFO: Skipping cache warmup for APP_ENV=${APP_ENV}."
    fi

    log "INFO: Application setup complete."

    # If the command was an option (started with '-'), prepend the default CMD
    if [ "${1#-}" != "$1" ]; then
        set -- apache2-foreground "$@"
    fi

else
    # If command is something else (e.g., `bash`, `ls`), run it directly without setup
    log "INFO: Command '$1' does not trigger app setup. Executing directly."
fi


# --- Execute Main Command ---
# Use gosu to drop root privileges (if running as root) and execute the main command
# passed as arguments $@ as the non-root user ($APP_USER).

# Log the command safely using printf
printf '[Entrypoint] INFO: Preparing to execute command as user %s (%s):' "${APP_USER}" "$(id -u ${APP_USER} 2>/dev/null || echo 'N/A')"
for arg in "$@"; do
    printf ' %q' "$arg" # %q quotes arguments safely
done
printf '\n'

# Fix stdout/stderr permissions for gosu (often needed when running as root)
# Check if running as root and TTYs are writable by root
if [ "$(id -u)" = "0" ] && [ -w /proc/self/fd/1 ] && [ -w /proc/self/fd/2 ]; then
    log "DEBUG: Fixing stdio permissions for user ${APP_USER}:${APP_GROUP}..."
    chown "${APP_USER}:${APP_GROUP}" /proc/self/fd/1 /proc/self/fd/2 || log "WARNING: Failed to chown stdio descriptors (continuing anyway)" >&2
fi

log "INFO: Executing command: $@"
exec gosu "${APP_USER}" "$@"

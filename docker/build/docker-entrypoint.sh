#!/bin/sh
# This script prepares the environment and runs the main container command.

# Exit immediately if a command exits with a non-zero status.
set -e

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [Entrypoint] $1"
}

log "--- Entrypoint Start ---"
log "Running as: $(id -un) ($(id -u))"
log "APP_ENV=${APP_ENV}"

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

# --- Wait for Database and Run Migrations ---
if [ -n "${DATABASE_URL}" ] && ! echo "${DATABASE_URL}" | grep -q "sqlite"; then
    log "INFO: Waiting for database connection..."
    until php bin/console dbal:run-sql "SELECT 1" --quiet --no-interaction; do
        log "DEBUG: Database unavailable, waiting 1 second..."
        sleep 1
    done
    log "INFO: Database connection successful."

    log "INFO: Applying database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
else
    log "INFO: Using SQLite or no DATABASE_URL, skipping database wait and migrations."
fi

# --- Generate SSH Key if it doesn't exist ---
if [ ! -f var/ssh/keyroll_ed25519 ]; then
    log "INFO: Generating KeyRoll SSH key..."
    php bin/console app:ssh-key:generate --no-interaction
else
    log "INFO: SSH key 'var/ssh/keyroll_ed25519' already exists."
fi

log "INFO: Application setup complete."

exec "$@"

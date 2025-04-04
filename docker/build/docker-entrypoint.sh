#!/bin/bash
# This script prepares the environment and runs the main container command.
# Handles DATABASE_URL construction, waits for the database, sets ACLs,
# generates SSH keys, runs migrations, warms up cache, and executes CMD as APP_USER.

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Environment Variable Setup ---
APP_ENV="${APP_ENV:-prod}"
APP_USER="${APP_USER:-keyroll}"
APP_GROUP="${APP_GROUP:-keyroll}"
APP_UID="${APP_UID:-1000}"
APP_GID="${APP_GID:-1000}"

echo "--- Entrypoint Start ---"
echo "User: $(id -u):$(id -g)"
echo "APP_USER=${APP_USER}, APP_GROUP=${APP_GROUP}, APP_UID=${APP_UID}, APP_GID=${APP_GID}"
echo "APP_ENV=${APP_ENV}"

# --- DATABASE_URL Construction ---
# Check if DATABASE_URL is already set. If not, try constructing it.
if [ -z "${DATABASE_URL}" ]; then
  echo "INFO: DATABASE_URL not set, attempting to construct from KEYROLL_DATABASE_* variables..."

  # Check for required environment variables for construction
  missing_vars=""
  if [ -z "$KEYROLL_DATABASE_HOST" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_HOST"; fi
  if [ -z "$KEYROLL_DATABASE_PORT" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PORT"; fi
  if [ -z "$KEYROLL_DATABASE_NAME" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_NAME"; fi
  if [ -z "$KEYROLL_DATABASE_USER" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_USER"; fi
  if [ -z "$KEYROLL_DATABASE_PASSWORD" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PASSWORD"; fi

  # Exit if any required variables for construction are missing
  if [ -n "$missing_vars" ]; then
    echo "ERROR: Missing required environment variables to construct DATABASE_URL:" >&2
    echo " $missing_vars" >&2
    echo "Please set these variables or provide a complete DATABASE_URL." >&2
    exit 1
  fi

  # Construct the MySQL/MariaDB DATABASE_URL
  DATABASE_URL="mysql://${KEYROLL_DATABASE_USER}:${KEYROLL_DATABASE_PASSWORD}@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}?charset=utf8mb4"
  export DATABASE_URL
  echo "INFO: DATABASE_URL constructed: mysql://${KEYROLL_DATABASE_USER}:***@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}"
else
    # Mask password if displaying existing URL
    masked_db_url=$(echo "$DATABASE_URL" | sed -E 's/(mysql:\/\/.*:)([^@]+)(@.*)/\1***\3/')
    echo "INFO: Using provided DATABASE_URL: ${masked_db_url}"
fi
# Ensure DATABASE_URL is exported even if provided externally
export DATABASE_URL

# --- Command Handling ---
# Check if the first argument looks like an option (e.g., `-f`, `--some-option`)
# Or if it's one of the commands that should be run as APP_USER after setup
if [ "${1#-}" != "$1" ] || [ "$1" = 'apache2-foreground' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    # Only run setup steps if the command requires the app runtime
    echo "INFO: Running application setup steps for command: $1"

    # --- Wait for Database ---
    if [[ -n "$DATABASE_URL" ]] && [[ "$DATABASE_URL" != *"sqlite"* ]]; then
        echo "INFO: Waiting for database connection..."
        ATTEMPTS=0
        MAX_ATTEMPTS=60 # Wait up to 60 seconds
        until gosu "${APP_USER}" php bin/console dbal:run-sql "SELECT 1" --env="$APP_ENV" --quiet --no-interaction; do
            ATTEMPTS=$((ATTEMPTS+1))
            if [ $ATTEMPTS -ge $MAX_ATTEMPTS ]; then
                 echo "ERROR: Database connection failed after $MAX_ATTEMPTS attempts." >&2
                 exit 1
            fi
            echo "DEBUG: Database unavailable, waiting 1 second... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 1
        done
        echo "INFO: Database connection successful."
    else
        echo "INFO: No external DATABASE_URL configured or using SQLite, skipping database wait."
    fi

    # --- Run As App User ---
    # Generate SSH key if it doesn't exist (run as APP_USER)
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "INFO: Generating KeyRoll SSH key..."
        # Ensure var/ssh exists and is writable by APP_USER (ACLs should handle this)
        mkdir -p var/ssh
        gosu "${APP_USER}" php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    else
         echo "INFO: SSH key already exists."
    fi

    # Apply database migrations if configured and migrations exist (run as APP_USER)
    # Check if migrations dir exists AND contains PHP files
    if [[ -n "$DATABASE_URL" ]] && [[ "$DATABASE_URL" != *"sqlite"* ]] && [ -d migrations ] && ls migrations/*.php &> /dev/null; then
        echo "INFO: Applying database migrations..."
        gosu "${APP_USER}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
    elif [[ "$DATABASE_URL" == *"sqlite"* ]]; then
         echo "INFO: Using SQLite, skipping migrations."
    elif [ -z "$DATABASE_URL" ]; then
        echo "INFO: No database configured, skipping migrations."
    else
        echo "INFO: No migrations directory found or no migrations exist, skipping."
    fi

    # Clear/Warmup Cache (run as APP_USER)
    echo "INFO: Clearing and warming up application cache for ${APP_ENV}..."
    gosu "${APP_USER}" php bin/console cache:clear --env="$APP_ENV"
    gosu "${APP_USER}" php bin/console cache:warmup --env="$APP_ENV"
    echo "INFO: Cache warmed up."

    echo "INFO: Application setup complete."

    # If the command was an option, prepend apache2-foreground
    if [ "${1#-}" != "$1" ]; then
        set -- apache2-foreground "$@"
    fi

else
    # If command is something else (e.g., `bash`, `ls`), run it directly without setup
    echo "INFO: Command '$1' does not trigger app setup. Executing directly."
fi


# --- Execute Main Command ---
# Use gosu to drop root privileges (if running as root) and execute the main command
# passed as arguments $@ as the non-root user ($APP_USER).

# Log the command safely using printf
printf 'INFO: Preparing to execute command as user %s (%s):' "${APP_USER}" "$(id -u ${APP_USER})"
for arg in "$@"; do
    printf ' %q' "$arg" # %q quotes arguments safely
done
printf '\n'

# Fix stdout/stderr permissions for gosu (often needed)
# This ensures the target user can write to the container's stdout/stderr
if [ "$(id -u)" = "0" ] && [ -w /proc/self/fd/1 ] && [ -w /proc/self/fd/2 ]; then
    echo "DEBUG: Fixing stdio permissions for user ${APP_USER}:${APP_GROUP}..."
    chown "${APP_USER}:${APP_GROUP}" /proc/self/fd/1 /proc/self/fd/2 || echo "WARNING: Failed to chown stdio descriptors (continuing anyway)" >&2
else
    echo "DEBUG: Not running as root or stdio not writable, skipping stdio permission fix."
fi

echo "INFO: Executing command..."
exec gosu "${APP_USER}" "$@"

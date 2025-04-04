#!/bin/sh
# This script prepares the environment and runs the main container command.
# It handles DATABASE_URL construction, waits for the database, sets ACLs for
# bind mount permissions, generates SSH keys, runs migrations, warms up cache,
# and finally executes the CMD as the non-root application user ($APP_USER).

# Exit immediately if a command exits with a non-zero status.
set -e

# Default APP_ENV to prod if not set externally
: ${APP_ENV:=prod}
# Make sure APP_USER is set, use value from Dockerfile ENV or default
: ${APP_USER:=${APP_USER:-keyroll}}
# Use APP_GROUP from Dockerfile ENV or default to APP_USER
: ${APP_GROUP:=${APP_GROUP:-${APP_USER}}}
# Use APP_UID from Dockerfile ENV or default
: ${APP_UID:=${APP_UID:-1000}}

# --- DATABASE_URL Construction ---
# Check if DATABASE_URL is already set. If not, try constructing it.
if [ -z "$DATABASE_URL" ]; then
  echo "DATABASE_URL not set, attempting to construct from KEYROLL_DATABASE_* variables..."

  # Check for required environment variables for construction
  missing_vars=""
  if [ -z "$KEYROLL_DATABASE_HOST" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_HOST"; fi
  if [ -z "$KEYROLL_DATABASE_PORT" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PORT"; fi
  if [ -z "$KEYROLL_DATABASE_NAME" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_NAME"; fi
  if [ -z "$KEYROLL_DATABASE_USER" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_USER"; fi
  if [ -z "$KEYROLL_DATABASE_PASSWORD" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PASSWORD"; fi

  # Exit if any required variables for construction are missing
  if [ -n "$missing_vars" ]; then
    echo "Error: Missing required environment variables to construct DATABASE_URL:" >&2
    echo " $missing_vars" >&2
    echo "Please set these variables or provide a complete DATABASE_URL." >&2
    exit 1
  fi

  # Construct the MySQL/MariaDB DATABASE_URL
  DATABASE_URL="mysql://${KEYROLL_DATABASE_USER}:${KEYROLL_DATABASE_PASSWORD}@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}?charset=utf8mb4"
  export DATABASE_URL
  echo "DATABASE_URL constructed successfully."
else
    echo "Using provided DATABASE_URL."
fi


# --- Root-Level Setup ---
# This section runs as root (or the initial user the container started as)

# Set file access control lists (ACLs) for bind mount permissions
# Check if running as root, as setfacl typically requires root privileges.
if [ "$(id -u)" = "0" ]; then
    TARGET_DIRS="var/cache var/log var/ssh"
    # Ensure target directories exist (should be created in Dockerfile)
    mkdir -p "$TARGET_DIRS"

    echo "Setting ACLs for user ${APP_UID} on ${TARGET_DIRS}..."
    # Apply ACLs recursively for existing files/dirs and set default ACLs for new ones
    setfacl -Rnm u:"${APP_UID}":rwX,d:u:"${APP_UID}":rwX "$TARGET_DIRS"
else
    echo "Warning: Not running as root. Skipping ACL setup." >&2
    echo "Bind mount permissions might be incorrect if host UID/GID differ." >&2
fi


# --- Command Handling ---
# Check if the first argument looks like an option (e.g., `-f`, `--some-option`)
# If so, prepend `php-fpm` to the command arguments.
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi


# --- Application-Level Setup ---
# Run setup commands only if the main command is php-fpm, php, or bin/console.
# These commands will be executed as the APP_USER via gosu later.
if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    echo "Running application setup for command: $1 (APP_ENV=${APP_ENV})"

    # Wait for database connection if DATABASE_URL is configured
    if [ -n "$DATABASE_URL" ]; then
        echo "Waiting for database connection..."
        ATTEMPTS=0
        MAX_ATTEMPTS=60 # Wait up to 5 minutes (60 * 5 seconds)
        # Use gosu to run the check as the APP_USER
        until gosu "${APP_USER}" php bin/console doctrine:query:sql "SELECT 1" --env="$APP_ENV" --quiet || [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
            ATTEMPTS=$((ATTEMPTS+1))
            echo "Database unavailable, waiting 5 seconds... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 5
        done

        if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
            echo "Error: Database connection failed after $MAX_ATTEMPTS attempts." >&2
            exit 1
        fi
        echo "Database connection successful."
    else
        echo "No DATABASE_URL configured, skipping database wait."
    fi

    # Generate SSH key if it doesn't exist (run as APP_USER)
    # Ensure var/ssh exists and has correct permissions from ACL step
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "Generating KeyRoll SSH key..."
        gosu "${APP_USER}" php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    else
         echo "SSH key already exists."
    fi

    # Apply database migrations if configured and migrations exist (run as APP_USER)
    if [ -n "$DATABASE_URL" ] && [ -d migrations ] && [ -n "$(ls -A migrations/*.php 2>/dev/null)" ]; then
        echo "Applying database migrations..."
        gosu "${APP_USER}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
    elif [ -z "$DATABASE_URL" ]; then
        echo "No database configured, skipping migrations."
    else
        echo "No migrations directory found or no migrations exist, skipping."
    fi

    # --- Cache Management ---
    # Clear and warm up the application cache (run as APP_USER)
    # This is essential when composer scripts are skipped during build
    echo "Clearing and warming up application cache for ${APP_ENV}..."
    gosu "${APP_USER}" php bin/console cache:clear --env="$APP_ENV"
    gosu "${APP_USER}" php bin/console cache:warmup --env="$APP_ENV"
    echo "Cache warmed up."
    # --- End Cache Management ---

    echo "Application setup complete."
fi


# --- Execute Main Command ---
# Use gosu to drop root privileges and execute the main command (passed as arguments $@)
# as the non-root user ($APP_USER).
echo "Executing command as user ${APP_USER}: $@"
exec gosu "${APP_USER}" "$@"

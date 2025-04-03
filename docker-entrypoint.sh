#!/bin/sh
set -e

# Check if DATABASE_URL needs to be constructed
if [ -z "$DATABASE_URL" ]; then
  echo "DATABASE_URL not set, attempting to construct from KEYROLL_DATABASE_* variables..."

  # Check for required environment variables
  missing_vars=""
  if [ -z "$KEYROLL_DATABASE_HOST" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_HOST"; fi
  if [ -z "$KEYROLL_DATABASE_PORT" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PORT"; fi
  if [ -z "$KEYROLL_DATABASE_NAME" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_NAME"; fi
  if [ -z "$KEYROLL_DATABASE_USER" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_USER"; fi
  if [ -z "$KEYROLL_DATABASE_PASSWORD" ]; then missing_vars="$missing_vars KEYROLL_DATABASE_PASSWORD"; fi

  # If any variables are missing, print error and exit
  if [ -n "$missing_vars" ]; then
    echo "Error: Missing required environment variables to construct DATABASE_URL:" >&2
    echo " $missing_vars" >&2
    echo "Please set these variables in your docker-compose.yml or environment." >&2
    exit 1
  fi

  # Construct the URL if all required variables are present
  DATABASE_URL="mysql://${KEYROLL_DATABASE_USER}:${KEYROLL_DATABASE_PASSWORD}@${KEYROLL_DATABASE_HOST}:${KEYROLL_DATABASE_PORT}/${KEYROLL_DATABASE_NAME}?serverVersion=11.4-MariaDB&charset=utf8mb4"
  export DATABASE_URL
  echo "DATABASE_URL constructed successfully."
else
    echo "Using provided DATABASE_URL."
fi

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

# Run setup only when the main process is started
if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    echo "Checking for Symfony runtime environment..."

    if [ -n "$DATABASE_URL" ]; then
        echo "Waiting for database connection..."
        ATTEMPTS=0
        MAX_ATTEMPTS=60
        until php bin/console doctrine:query:sql "SELECT 1" --env="$APP_ENV" || [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
            ATTEMPTS=$((ATTEMPTS+1))
            echo "Database unavailable, waiting 5 seconds... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 5
        done

        if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
            echo "Database connection failed after $MAX_ATTEMPTS attempts."
            exit 1
        fi
        echo "Database connection successful."
    else
        echo "No DATABASE_URL configured, skipping database wait."
    fi

    # Generate SSH key if not present
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "Generating KeyRoll SSH key..."
        php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    fi

    # Run migrations if any exist and database is configured
    if [ -n "$DATABASE_URL" ] && [ -d migrations ] && [ -n "$(ls -A migrations/*.php 2>/dev/null)" ]; then
        echo "Applying database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
    elif [ -z "$DATABASE_URL" ]; then
        echo "No database configured, skipping migrations."
    else
        echo "No migrations found or directory does not exist."
    fi

    # Clear and warm up the cache for the target environment
    echo "Clearing and warming up application cache for $APP_ENV..."
    php bin/console cache:clear --env="$APP_ENV"
    # shellcheck disable=SC2086
    php bin/console cache:warmup --env="$APP_ENV"
    echo "Cache warmed up."
fi

# Execute the original command (e.g. php-fpm)
echo "Executing command: $@"
exec "$@"

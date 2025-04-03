#!/bin/sh
set -e

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
        until php bin/console doctrine:query:sql "SELECT 1" --env="$APP_ENV" > /dev/null 2>&1 || [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
            ATTEMPTS=$((ATTEMPTS+1))
            echo "Database unavailable, waiting 5 seconds... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 5
        done

        if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
            echo "Database connection failed after $MAX_ATTEMPTS attempts."
            exit 1
        fi
        echo "Database connection successful."
    fi

    # Generate SSH key if not present
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "Generating KeyRoll SSH key..."
        php bin/console app:ssh-key:generate --no-interaction --env="$APP_ENV"
    fi

    # Run migrations if any exist
    if [ -d migrations ] && [ -n "$(ls -A migrations/*.php 2>/dev/null)" ]; then
        echo "Applying database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="$APP_ENV"
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
exec "$@"

#!/bin/sh
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    echo "Checking for Symfony runtime environment..."

    # Wait for database to be ready
    if grep -q DATABASE_URL .env; then
        until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
            echo "Waiting for database to be ready..."
            sleep 2
        done
    fi

    # Generate SSH key for KeyRoll if it doesn't exist
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "Generating KeyRoll SSH key..."
        mkdir -p var/ssh
        php bin/console app:ssh-key:generate --no-interaction
    fi

    # Apply database migrations
    if ls -A migrations/*.php > /dev/null 2>&1; then
        echo "Applying database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction
    fi

    # Clear and warmup cache
    echo "Clearing and warming up application cache..."
    php bin/console cache:clear --no-warmup
    php bin/console cache:warmup
fi

exec "$@"

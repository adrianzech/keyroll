#!/bin/sh
set -e

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

# Führe Setup nur aus, wenn der Hauptprozess gestartet wird
if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    echo "Checking for Symfony runtime environment..."

    # KEIN .env.local erstellen - Symfony liest direkt aus der Umgebung!

    # Warte auf die Datenbank, falls DATABASE_URL oder relevante KEYROLL_* vars gesetzt sind
    # Prüfe ob DATABASE_URL oder die Host-Variable gesetzt ist
    if [ -n "$DATABASE_URL" ] || [ -n "$KEYROLL_DATABASE_HOST" ]; then
        echo "Waiting for database connection..."
        # Verwende doctrine:query:sql, was robuster ist als nc oder pg_isready
        # Timeout nach ~60 Sekunden
        ATTEMPTS=0
        MAX_ATTEMPTS=30
        until php bin/console doctrine:query:sql "SELECT 1" --env=$APP_ENV > /dev/null 2>&1 || [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; do
            ATTEMPTS=$((ATTEMPTS+1))
            echo "Database unavailable, waiting 2 seconds... (Attempt $ATTEMPTS/$MAX_ATTEMPTS)"
            sleep 2
        done

        if [ $ATTEMPTS -eq $MAX_ATTEMPTS ]; then
            echo "Database connection failed after $MAX_ATTEMPTS attempts."
            exit 1
        fi
        echo "Database connection successful."
    fi

    # Generiere SSH Key nur wenn nicht vorhanden
    if [ ! -f var/ssh/keyroll_ed25519 ]; then
        echo "Generating KeyRoll SSH key..."
        # Verzeichnis sollte schon existieren und die richtigen Rechte haben vom Dockerfile
        php bin/console app:ssh-key:generate --no-interaction --env=$APP_ENV
    fi

    # Führe Migrationen aus, falls welche existieren
    # Prüfe, ob das migrations-Verzeichnis existiert und nicht leer ist
    if [ -d migrations ] && [ -n "$(ls -A migrations/*.php 2>/dev/null)" ]; then
        echo "Applying database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=$APP_ENV
    else
        echo "No migrations found or directory does not exist."
    fi

    # Leere und wärme den Cache für die Zielumgebung auf
    echo "Clearing and warming up application cache for $APP_ENV..."
    php bin/console cache:clear --env=$APP_ENV
    php bin/console cache:warmup --env=$APP_ENV
    echo "Cache warmed up."

fi

# Führe das ursprüngliche Kommando aus (z.B. php-fpm)
exec "$@"

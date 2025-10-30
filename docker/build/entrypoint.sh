#!/usr/bin/env sh
set -e

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log "Starting application in ${APP_ENV} environment..."

# Build DATABASE_URL and write to .env.local for Symfony
if [ -n "${DATABASE_USER}" ] && [ -n "${DATABASE_PASSWORD}" ] && [ -n "${DATABASE_NAME}" ]; then
  DB_HOST="${DB_HOST:-mariadb}"
  DB_PORT="${DB_PORT:-3306}"

  DATABASE_URL="mysql://${DATABASE_USER}:${DATABASE_PASSWORD}@${DB_HOST}:${DB_PORT}/${DATABASE_NAME}?charset=utf8mb4&serverVersion=11.4.8-MariaDB"

  echo "DATABASE_URL=${DATABASE_URL}" > .env.local
  log "Created .env.local with DATABASE_URL"

  # Wait for database connection
  log "Waiting for database connection..."
  until php bin/console dbal:run-sql "SELECT 1" --quiet --no-interaction 2>/dev/null; do
    sleep 1
  done
  log "Database connection established"

  # Run migrations
  log "Applying database migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

# Generate SSH key if it doesn't exist
if [ ! -f var/ssh/keyroll_ed25519 ]; then
  log "Generating KeyRoll SSH key..."
  php bin/console app:ssh-key:generate --no-interaction
else
  log "SSH key already exists"
fi

# Clear and warmup cache if needed
if [ ! -d "var/cache/${APP_ENV}" ] || [ -z "$(ls -A var/cache/"${APP_ENV}" 2>/dev/null)" ]; then
  log "Building Symfony cache..."
  php bin/console cache:clear --no-warmup
  php bin/console cache:warmup
fi

log "Starting FrankenPHP..."
exec frankenphp run --config="${CADDY_CONFIG}"

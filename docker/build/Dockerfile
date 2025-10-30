###############################################
# Build Arguments
###############################################
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.8
ARG NODE_VERSION=22
ARG APP_ENV=prod
ARG APP_DEBUG=0

###############################################
# Stage 1: Composer Dependencies
###############################################
FROM composer:${COMPOSER_VERSION} AS composer
ARG APP_ENV

WORKDIR /srv/app

COPY . .

# Set dummy DATABASE_URL for build process
ENV DATABASE_URL="mysql://dummy:dummy@localhost:3306/dummy?serverVersion=8.0"

RUN if [ "$APP_ENV" = "dev" ]; then \
      composer install --no-interaction --prefer-dist; \
    else \
      composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; \
    fi

###############################################
# Stage 2: Node Dependencies
###############################################
FROM node:${NODE_VERSION}-alpine AS node-deps

WORKDIR /srv/app

COPY --from=composer /srv/app /srv/app

RUN if [ -f package.json ]; then \
      npm ci --include=dev 2>/dev/null || npm install --include=dev; \
    fi

###############################################
# Stage 3: Tailwind CSS Build
###############################################
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS tailwind
ARG APP_ENV

WORKDIR /srv/app

COPY --from=node-deps /srv/app /srv/app

# Set dummy DATABASE_URL for build process
ENV DATABASE_URL="mysql://dummy:dummy@localhost:3306/dummy?serverVersion=8.0"

RUN set -eux; \
    mkdir -p var/tailwind public/build public/styles; \
    echo "Checking for Tailwind bundle..."; \
    if php bin/console list | grep -q "tailwind:build"; then \
      echo "Building Tailwind CSS..."; \
      php bin/console tailwind:build --minify; \
      echo "Tailwind built successfully"; \
      ls -la var/tailwind/; \
      cp var/tailwind/app.built.css public/styles/app.css; \
      echo "Copied CSS to public/styles/app.css"; \
    else \
      echo "ERROR: Tailwind bundle not found!"; \
      exit 1; \
    fi

###############################################
# Stage 4: Asset Mapper Compilation
###############################################
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS assets
ARG APP_ENV

WORKDIR /srv/app

COPY --from=tailwind /srv/app /srv/app

# Set dummy DATABASE_URL for build process
ENV DATABASE_URL="mysql://dummy:dummy@localhost:3306/dummy?serverVersion=8.0"

# Compile Asset Mapper assets
RUN set -eux; \
    echo "Compiling Asset Mapper assets..."; \
    php bin/console asset-map:compile; \
    echo "Assets compiled successfully"; \
    ls -la public/assets/ || echo "No assets directory found"

###############################################
# Stage 5: Frontend Assets
###############################################
FROM node:${NODE_VERSION}-alpine AS frontend

WORKDIR /srv/app

COPY --from=assets /srv/app /srv/app

RUN set -eux; \
    if [ -f package.json ] && grep -q '"build"' package.json; then \
      npm run build || true; \
    fi

###############################################
# Stage 6: Runtime Image
###############################################
FROM dunglas/frankenphp:php${PHP_VERSION}-alpine AS runtime
ARG APP_ENV
ARG APP_DEBUG

WORKDIR /srv/app

# Install system packages including OpenSSH
RUN apk add --no-cache openssh-client

# PHP Extensions
RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    intl \
    opcache

# Generate SSH host keys for the container
RUN ssh-keygen -A

# Application Files
COPY --from=composer --chown=www-data:www-data /srv/app /srv/app
COPY --from=frontend --chown=www-data:www-data /srv/app/public/build /srv/app/public/build
COPY --from=assets --chown=www-data:www-data /srv/app/public/assets /srv/app/public/assets
COPY --from=tailwind --chown=www-data:www-data /srv/app/var/tailwind /srv/app/var/tailwind

# Caddy Configuration
COPY docker/build/Caddyfile /etc/caddy/Caddyfile

# Environment Variables
ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG} \
    SERVER_NAME=:8000 \
    CADDY_CONFIG=/etc/caddy/Caddyfile \
    APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime \
    FRANKENPHP_CONFIG="worker ./public/index.php"

# Writable Directories
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var && \
    chmod -R 755 var

# Health Check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/healthz || exit 1

EXPOSE 8000

# Entrypoint
COPY docker/build/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]

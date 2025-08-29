# ==============================================================================
# Stage 1: Build Environment
# Purpose: Install dependencies and build a clean production code artifact.
# ==============================================================================
FROM composer:2.8 AS builder

WORKDIR /app

# Install Node.js for frontend asset building
RUN apk add --no-cache \
    nodejs \
    npm

# Copy dependency manifests
COPY composer.json composer.lock symfony.lock ./
COPY package.json package-lock.json ./
COPY importmap.php ./

# Install Composer and Node dependencies
# We need the dev dependencies for the asset compilation
RUN composer install --prefer-dist --no-progress --no-scripts
RUN npm install

# Copy the rest of the application source code
COPY . .

# Generate the autoloader with all dependencies (including dev)
# This is necessary for the console commands to run
RUN composer dump-autoload --optimize --classmap-authoritative

# Compile frontend assets for production using AssetMapper & Tailwind
RUN php bin/console importmap:install && \
    php bin/console tailwind:build --minify && \
    php bin/console asset-map:compile

# Now, remove development-only dependencies and create the final production autoloader
RUN composer install --prefer-dist --no-dev --no-progress --no-scripts --optimize-autoloader \
    && rm -rf node_modules tests

# ==============================================================================
# Stage 2: Final Runtime Environment
# Purpose: Create a minimal, secure image for production.
# ==============================================================================
FROM dunglas/frankenphp:php8.4-alpine AS final

WORKDIR /app

# Install system packages and PHP extensions in a single layer
RUN --mount=type=cache,id=apk-cache,target=/var/cache/apk \
    apk add --no-cache \
        acl \
        openssh-client \
        icu-libs \
        icu-dev \
        zlib-dev \
        libzip-dev \
        postgresql-dev \
        mariadb-dev \
        sqlite-dev \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
    && docker-php-ext-install \
        intl \
        zip \
        pdo_mysql \
        pdo_pgsql \
    && pecl install apcu \
    && docker-php-ext-enable apcu opcache \
    && rm -rf /tmp/pear \
    && apk del .build-deps

# Copy the application artifact from the builder stage
COPY --from=builder /app .

# Copy and prepare the entrypoint script
COPY docker/build/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create and set permissions for required directories
RUN mkdir -p /data/caddy var/cache var/log \
    && chown -R www-data:www-data /data var .

# Switch to the non-root user for security
USER www-data

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

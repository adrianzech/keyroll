# ==============================================================================
# Global Build Arguments
# ==============================================================================
ARG PHP_VERSION=8.4
ARG NODE_MAJOR=22
ARG APP_ENV=prod
ARG APP_USER=keyroll
ARG APP_GROUP=keyroll
ARG APP_UID=1000
ARG APP_GID=1000
ARG DATABASE_URL="pdo-sqlite:///:memory:"

# ==============================================================================
# Stage 1: Build Environment (Alpine based)
# Purpose: Install all dependencies (dev included), build assets, prepare production code artifact.
# ==============================================================================
FROM php:${PHP_VERSION}-cli-alpine AS php_build

# Set build arguments available in this stage
ARG NODE_MAJOR
ARG APP_ENV
ARG APP_USER
ARG APP_GROUP
ARG APP_UID
ARG APP_GID
ARG DATABASE_URL

# Set working directory
WORKDIR /app

# Set environment variables for this stage
ENV APP_ENV=${APP_ENV} \
    DATABASE_URL=${DATABASE_URL}

# Install essential system packages and PHP extension build dependencies for Alpine
RUN apk update && apk add --no-cache \
    acl \
    git \
    curl \
    unzip \
    zip \
    openssh-client \
    icu-dev \
    libzip-dev \
    mariadb-dev \
    sqlite-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    build-base

# Configure and Install PHP extensions required during build or runtime
RUN docker-php-ext-configure intl && \
    # Use docker-php-ext-install for installation
    # -j$(nproc) uses multiple cores for faster compilation
    docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    zip

# Install Node.js using Alpine package manager
RUN apk add --no-cache nodejs npm && \
    npm install -g npm@latest

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# --- Dependency Installation ---

# Copy dependency manifest files for caching
COPY composer.json composer.lock symfony.lock ./
COPY package.json package-lock.json ./
COPY importmap.php ./

# Install Composer dependencies (including dev, NO scripts)
RUN composer install --prefer-dist --no-progress --no-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# Install Node packages (including dev-dependencies needed for build steps)
RUN npm install --omit=optional --legacy-peer-deps

# Copy the rest of the application source code
# This layer changes frequently, so copy it after dependency installation
COPY . .

# --- Build Application Artifacts ---

# Generate optimized Composer autoloader for production (NO scripts)
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# Compile frontend assets for production using AssetMapper & Tailwind
RUN php bin/console importmap:install && \
    php bin/console tailwind:build --minify && \
    php bin/console asset-map:compile

# --- Cleanup Build Stage ---

# Remove development Composer dependencies (NO scripts)
RUN composer install --prefer-dist --no-dev --no-progress --no-autoloader --no-scripts --no-interaction

# Remove development Node packages
RUN npm prune --production

# Clear Symfony cache generated during build (if any)
RUN rm -rf var/cache/*

# Remove unnecessary files and development tools to minimize artifact size
RUN rm -rf \
    node_modules \
    tests \
    phpstan.dist.neon \
    phpmd.xml.dist \
    phpunit.xml.dist \
    .editorconfig \
    .git \
    .github \
    .gitignore \
    .php-cs-fixer.dist.php \
    docker \
    Dockerfile \
    docker-compose.yml \
    README.md \
    var/log/* \
    assets/node_modules \
    /tmp/* \
    ~/.composer \
    /root/.npm \
    /root/.cache \
    /usr/local/bin/composer \
    /usr/bin/npm \
    /usr/bin/node

# Remove build-time system packages and build dependencies
RUN apk del build-base $PHPIZE_DEPS npm nodejs


# ==============================================================================
# Stage 2: Final Runtime Environment (Alpine based FPM)
# Purpose: Create a minimal, secure image with only runtime dependencies and the optimized application code.
# ==============================================================================
FROM php:${PHP_VERSION}-fpm-alpine AS php_runtime

# Set runtime arguments and environment variables
ARG APP_USER
ARG APP_GROUP
ARG APP_UID
ARG APP_GID
ARG APP_ENV

ENV APP_USER=${APP_USER:-keyroll} \
    APP_GROUP=${APP_GROUP:-keyroll} \
    APP_UID=${APP_UID:-1000} \
    APP_GID=${APP_GID:-1000} \
    APP_ENV=${APP_ENV:-prod} \
    PHP_MEMORY_LIMIT=256M \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=128 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=10000 \
    PHP_OPCACHE_REVALIDATE_FREQ=0 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    LOG_PHP_ERRORS=1

# Set working directory
WORKDIR /app

# Install essential runtime system dependencies for Alpine
RUN apk update && apk add --no-cache \
    acl \
    curl \
    gettext \
    icu-libs \
    libzip \
    mariadb-connector-c \
    oniguruma \
    openssh-client

# Install PHP runtime extensions directly in the final stage for better reliability
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev mariadb-dev sqlite-dev oniguruma-dev build-base && \
    docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    zip && \
    docker-php-ext-install opcache && \
    docker-php-ext-enable opcache && \
    runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions | tr ',' '\n' | sort -u | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')" && \
    apk add --no-cache --virtual .php-rundeps $runDeps && \
    apk del .build-deps

# Copy custom PHP configuration (production optimized)
COPY docker/build/php/php.ini-production /usr/local/etc/php/php.ini
COPY docker/build/php/opcache.ini /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
COPY docker/build/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy the optimized application artifact from the build stage
COPY --from=php_build /app /app

# Ensure the base application directory is owned by root but readable/executable by others
RUN chown -R root:root /app && chmod -R ogu+rX /app

# Create the non-root application user and group using Alpine's tools
RUN \
  if ! getent group ${APP_GROUP} > /dev/null 2>&1 && ! getent group ${APP_GID} > /dev/null 2>&1; then \
    addgroup -g ${APP_GID} ${APP_GROUP} ; \
  else \
    EXISTING_GROUP_NAME=$(getent group ${APP_GID} | cut -d: -f1) ; \
    if [ "$EXISTING_GROUP_NAME" != "${APP_GROUP}" ]; then \
        echo "Warning: GID ${APP_GID} already exists with name '$EXISTING_GROUP_NAME'. Renaming group to '${APP_GROUP}'." ; \
        groupmod -n ${APP_GROUP} $EXISTING_GROUP_NAME ; \
    fi ; \
  fi && \
  if ! getent passwd ${APP_USER} > /dev/null 2>&1 && ! getent passwd ${APP_UID} > /dev/null 2>&1; then \
    adduser -u ${APP_UID} -G ${APP_GROUP} -h /app -s /sbin/nologin -D ${APP_USER} ; \
  else \
    EXISTING_USER_NAME=$(getent passwd ${APP_UID} | cut -d: -f1) ; \
    if [ "$EXISTING_USER_NAME" != "${APP_USER}" ]; then \
        echo "Warning: UID ${APP_UID} already exists with name '$EXISTING_USER_NAME'. Modifying user to '${APP_USER}'." ; \
        usermod -l ${APP_USER} -g ${APP_GROUP} -d /app -s /sbin/nologin $EXISTING_USER_NAME ; \
    else \
        # Ensure user is in the correct group even if UID matches
        usermod -g ${APP_GROUP} -d /app -s /sbin/nologin ${APP_USER} ; \
    fi ; \
  fi

# Create directories needed by Symfony and set ownership for the runtime user
RUN mkdir -p var/cache var/log var/ssh \
    && chown -R ${APP_USER}:${APP_GROUP} var

# Copy the entrypoint script (still requires root to copy to /usr/local/bin)
COPY docker/build/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# --- Switch to non-root user ---
USER ${APP_USER}

# Healthcheck to verify FPM is running
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
  CMD curl --fail http://127.0.0.1:9000/ping || exit 1

# Expose the port PHP-FPM listens on
EXPOSE 9000

# Set the entrypoint script to run on container start (will run as APP_USER)
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command: Run PHP-FPM in the foreground (will run as APP_USER)
CMD ["php-fpm"]
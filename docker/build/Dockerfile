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
# Stage 1: Build Environment
# Purpose: Install all dependencies (dev included), build assets, prepare production code artifact.
# ==============================================================================
FROM php:${PHP_VERSION}-apache AS php_build

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

# Install essential system packages and PHP extension build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    git \
    gosu \
    curl \
    unzip \
    zip \
    openssh-client \
    libicu-dev \
    libzip-dev \
    libmariadb-dev-compat \
    libsqlite3-dev \
    libonig-dev \
    build-essential \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure and Install PHP extensions required during build or runtime
RUN docker-php-ext-configure intl && \
    docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    zip

# Install Node.js using NodeSource repository
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - && \
    apt-get update && apt-get install -y --no-install-recommends nodejs && \
    npm install -g npm@latest && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install Symfony CLI globally
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# --- Dependency Installation ---

# Copy dependency manifest files for caching
COPY composer.json composer.lock symfony.lock ./
COPY package.json package-lock.json ./

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
    docker/build/Dockerfile \
    docker/compose.yml \
    README.md \
    var/log/* \
    /tmp/* \
    ~/.composer \
    /root/.npm \
    /root/.cache

# Remove build-time system packages
RUN apt-get purge -y --auto-remove build-essential libicu-dev libzip-dev libmariadb-dev-compat libsqlite3-dev libonig-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*


# ==============================================================================
# Stage 2: Final Runtime Environment
# Purpose: Create a minimal, secure image with only runtime dependencies and the optimized application code.
# ==============================================================================
FROM php:${PHP_VERSION}-apache AS php_runtime

# Set runtime arguments and environment variables
ARG APP_USER
ARG APP_GROUP
ARG APP_UID
ARG APP_GID

ENV APP_USER=${APP_USER:-keyroll} \
    APP_GROUP=${APP_GROUP:-keyroll} \
    APP_UID=${APP_UID:-1000} \
    APP_GID=${APP_GID:-1000} \
    APP_ENV=${APP_ENV:-prod}

# Set working directory
WORKDIR /app

# Install essential runtime system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    gosu \
    gettext \
    libicu72 \
    libzip4 \
    libmariadb3 \
    libonig5 \
    openssh-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache - Copy custom config and enable modules
COPY docker/build/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite expires headers

# Copy PHP configuration (including OPcache) from build stage
COPY --from=php_build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy compiled PHP extensions from build stage
COPY --from=php_build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Copy Symfony CLI from build stage (optional, needed if entrypoint uses `symfony` command)
COPY --from=php_build /usr/local/bin/symfony /usr/local/bin/symfony

# Copy the optimized application artifact from the build stage
COPY --from=php_build /app /app

# Ensure the base application directory is owned by root but readable/executable by others
RUN chown -R root:root /app && chmod -R ogu+rX /app

# Create the non-root application user and group if they don't exist
RUN groupadd -g ${APP_GID} ${APP_GROUP} || groupmod -n ${APP_GROUP} $(getent group ${APP_GID} | cut -d: -f1) \
 && useradd --no-create-home -u ${APP_UID} -g ${APP_GROUP} -s /bin/bash ${APP_USER} || usermod -l ${APP_USER} -g ${APP_GROUP} $(getent passwd ${APP_UID} | cut -d: -f1) \
 && usermod -a -G www-data ${APP_USER}

# Create directories needed by Symfony and set ownership for the runtime user
RUN mkdir -p var/cache var/log var/ssh \
    && chown -R ${APP_UID}:${APP_GID} var

# Copy the entrypoint script from the build context and make it executable
COPY docker/build/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose the port Apache listens on
EXPOSE 80

# Set the entrypoint script to run on container start
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command: Run Apache in the foreground
CMD ["apache2-foreground"]

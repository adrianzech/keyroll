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
FROM php:${PHP_VERSION}-fpm AS php_build

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
    libpq-dev \
    libzip-dev \
    libmariadb-dev \
    libsqlite3-dev \
    build-essential \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required during build or runtime
RUN docker-php-ext-install -j$(nproc) \
    intl \
    opcache \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    pdo_sqlite \
    zip

# Install Node.js using NodeSource repository
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - && \
    apt-get update && apt-get install -y --no-install-recommends nodejs && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install Symfony CLI globally
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Copy dependency manifest files for caching
COPY composer.json composer.lock symfony.lock ./
COPY package.json package-lock.json ./
COPY assets/styles/app.css ./assets/styles/

# Install Composer dependencies (NO scripts)
RUN composer install --prefer-dist --no-progress --no-autoloader --no-scripts

# Install Node packages (including dev-dependencies needed for build steps)
RUN npm install --omit=optional --legacy-peer-deps

# Copy the rest of the application source code
# This layer changes frequently, so copy it after dependency installation
COPY . .

# --- Build Application Artifacts ---

# Generate optimized Composer autoloader for production (NO scripts)
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# Compile frontend assets for production
# Ensure AssetMapper bundles are processed if necessary
# Use --resolve (-r) with importmap:install if you have downloaded assets
RUN php bin/console importmap:install && \
    php bin/console tailwind:build --minify && \
    php bin/console asset-map:compile

# --- Cleanup Build Stage ---

# Remove development Composer dependencies (NO scripts)
RUN composer install --prefer-dist --no-dev --no-progress --no-scripts

# Remove development Node packages
RUN npm prune --production

# Remove unnecessary files and development tools to minimize artifact size
RUN rm -rf \
    node_modules \
    tests \
    phpstan.dist.neon \
    phpmd.xml.dist \
    phpunit.xml.dist \
    .gitignore \
    .php-cs-fixer.dist.php \
    docker-entrypoint.sh \
    Dockerfile \
    docker-compose.yml \
    README.md \
    var/cache/* \
    var/log/* \
    /tmp/* \
    ~/.composer \
    /root/.npm \
    /root/.cache

# Remove build-time system packages
RUN apt-get purge -y --auto-remove build-essential libicu-dev libpq-dev libzip-dev libmariadb-dev libsqlite3-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*


# ==============================================================================
# Stage 2: Final Runtime Environment
# Purpose: Create a minimal, secure image with only runtime dependencies and the optimized application code.
# ==============================================================================
FROM php:${PHP_VERSION}-fpm AS php_runtime

# Set runtime arguments and environment variables
ARG APP_USER
ARG APP_GROUP
ARG APP_UID
ARG APP_GID
ENV APP_USER=${APP_USER} \
    APP_GROUP=${APP_GROUP} \
    APP_UID=${APP_UID} \
    APP_GID=${APP_GID}

# Set working directory
WORKDIR /app

# Install essential runtime system dependencies
# Ensure libicu version matches what PHP was built against (check base image if necessary)
# Example uses libicu72 for recent Debian/PHP versions
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    git \
    gosu \
    gettext \
    openssh-client \
    libicu72 \
    libpq5 \
    libzip4 \
    libmariadb3 \
    postgresql-client \
    mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy PHP configuration from build stage (includes opcache, etc.)
COPY --from=php_build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy compiled PHP extensions from build stage
COPY --from=php_build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# --- Configure PHP-FPM ---

# Modify GLOBAL FPM configuration
RUN \
    # Ensure FPM runs in foreground mode
    sed -i 's#^daemonize = .*#daemonize = no#' /usr/local/etc/php-fpm.conf \
    && sed -i 's#^;daemonize = .*#daemonize = no#' /usr/local/etc/php-fpm.conf
    # DO NOT explicitly set global error_log here when running as non-root.
    # Let FPM use inherited stdout/stderr. Docker will capture it.
    # Worker logs are captured via 'catch_workers_output=yes' in www.conf.

# Configure PHP-FPM pool: set user/group, enable logging, env vars, TCP listen, healthcheck endpoints
RUN sed -i "s#^user\s*=.*#user = ${APP_USER}#" /usr/local/etc/php-fpm.d/www.conf \
 && sed -i "s#^group\s*=.*#group = ${APP_GROUP}#" /usr/local/etc/php-fpm.d/www.conf \
 && sed -i "s#^;listen.owner\s*=.*#listen.owner = ${APP_USER}#" /usr/local/etc/php-fpm.d/www.conf \
 && sed -i "s#^;listen.group\s*=.*#listen.group = ${APP_GROUP}#" /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's#^;clear_env\s*=\s*no#clear_env = no#' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's#^;catch_workers_output\s*=\s*yes#catch_workers_output = yes#' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's#listen\s*=\s*/run/php/php[0-9]\.[0-9]-fpm\.sock#listen = 9000#' /usr/local/etc/php-fpm.d/www.conf \
 && echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/www.conf \
 && echo "ping.path = /ping" >> /usr/local/etc/php-fpm.d/www.conf \
 && echo "ping.response = pong" >> /usr/local/etc/php-fpm.d/www.conf

# Copy Symfony CLI from build stage (optional, for entrypoint/exec commands)
COPY --from=php_build /usr/local/bin/symfony /usr/local/bin/symfony

# Copy the optimized application artifact from the build stage
COPY --from=php_build /app /app

# Create directories needed by Symfony and ensure initial ownership
# Directories must exist before entrypoint script tries to setfacl on them
RUN mkdir -p var/cache var/log var/ssh \
    && chown -R ${APP_UID}:${APP_GID} var

# Create the non-root application user and group
# Use getent to find existing group/user ID if they exist, modify instead of adding if so
RUN groupadd -g ${APP_GID} ${APP_GROUP} || groupmod -n ${APP_GROUP} $(getent group ${APP_GID} | cut -d: -f1) \
 && useradd -u ${APP_UID} -g ${APP_GROUP} -ms /bin/bash ${APP_USER} || usermod -l ${APP_USER} -g ${APP_GROUP} -d /home/${APP_USER} -m $(getent passwd ${APP_UID} | cut -d: -f1) \
 && chown ${APP_USER}:${APP_GROUP} /app

# Copy the entrypoint script and make it executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose the port PHP-FPM listens on
EXPOSE 9000

# Healthcheck: Verify PHP-FPM is responding via its ping endpoint
RUN if ! command -v cgi-fcgi &> /dev/null; then \
        apt-get update && apt-get install -y --no-install-recommends libfcgi-bin && \
        apt-get clean && rm -rf /var/lib/apt/lists/* ;\
    fi
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

# Set the entrypoint script to run on container start
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command: Run PHP-FPM in the foreground
CMD ["php-fpm"]

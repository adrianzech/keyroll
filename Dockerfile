# Stage 1: Build Environment (incl. Dev Dependencies, Node.js, etc.)
FROM php:8.4-fpm AS php_build

# Install system and PHP dependencies (incl. build-essentials etc. if needed)
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    git \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    nodejs \
    npm \
    postgresql-client \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    intl \
    opcache \
    pdo \
    pdo_pgsql \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency files
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./
COPY assets/styles/app.css ./assets/styles/

# Install Composer dev dependencies for build steps
# --no-scripts because we run them later after copying the full code
# --no-autoloader because we dump it later
RUN composer install --prefer-dist --no-scripts --no-progress --no-autoloader

# Install NPM dependencies
RUN npm install

# Copy the rest of the application
COPY . .

# Execute Composer scripts (incl. post-install-cmd)
RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd

# Build Tailwind assets for production
RUN mkdir -p var/tailwind && \
    php bin/console tailwind:build --minify

# Clean up build artifacts and install only production Composer dependencies
# --no-scripts, as they have already been executed
# --optimize-autoloader for production performance
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-progress && \
    rm -rf node_modules tests phpstan.dist.neon phpmd.xml.dist phpunit.xml.dist .gitignore .php-cs-fixer.dist.php var/cache/* var/log/*

# Stage 2: Final Runtime Environment
FROM php:8.4-fpm AS php_runtime

# Install only necessary runtime system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    gettext \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    postgresql-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy PHP configuration (Opcache etc.) from build stage (includes installed ext configs)
COPY --from=php_build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install necessary runtime PHP extensions
# Ensure these match the -dev packages installed above
RUN docker-php-ext-install \
    intl \
    opcache \
    pdo \
    pdo_pgsql \
    zip

WORKDIR /var/www/html

# Copy only the built application artifact from the build stage
COPY --from=php_build /var/www/html /var/www/html

# Copy the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create the non-root user and set permissions for 'var' directory
RUN mkdir -p var/cache var/log var/ssh && \
    useradd -ms /bin/bash keyroll && \
    chown -R keyroll:keyroll /var/www/html

# Switch to the non-root user
USER keyroll

# Expose the port PHP-FPM listens on
EXPOSE 9000

# Define the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command to run PHP-FPM
CMD ["php-fpm"]

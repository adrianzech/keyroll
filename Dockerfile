FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    acl \
    file \
    gettext \
    git \
    libpq-dev \
    postgresql-client \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    opcache \
    intl

# Configure opcache for production use
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./

# First install WITH dev dependencies for Tailwind initialization
RUN composer install --prefer-dist --no-autoloader --no-scripts --no-progress

# Install npm dependencies including daisyUI
RUN npm install

# Copy the rest of the application
COPY . .

# Run scripts with dev dependencies
RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd

# Build Tailwind CSS (with dev dependencies still present)
RUN mkdir -p var/tailwind && \
    APP_ENV=dev php bin/console tailwind:init && \
    APP_ENV=dev php bin/console tailwind:build

# Then remove dev dependencies for production
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-progress

# Create required directories with proper permissions
RUN mkdir -p var/cache var/log var/ssh && \
    chmod -R 777 var

# Remove dev files
RUN rm -rf tests phpstan.dist.neon phpmd.xml.dist phpunit.xml.dist .gitignore .php-cs-fixer.dist.php node_modules

# Add entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create a non-root user to run the application
RUN useradd -ms /bin/bash keyroll && \
    chown -R keyroll:keyroll /var/www/html

USER keyroll

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

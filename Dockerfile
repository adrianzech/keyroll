FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    acl \
    file \
    gettext \
    git \
    libpq-dev \
    postgresql-client \
    zip \
    unzip \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    opcache

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

# Copy application files
COPY . /var/www/html/

# Set proper directory permissions
RUN mkdir -p var/cache var/log var/ssh && \
    chmod -R 777 var && \
    chown -R www-data:www-data var

# Install dependencies
RUN composer install --prefer-dist --no-dev --no-interaction --no-progress

# Build Tailwind assets
RUN composer dump-env prod
RUN php bin/console tailwind:build

# Remove dev files
RUN rm -rf tests phpstan.dist.neon phpmd.xml.dist phpunit.xml.dist .gitignore .php-cs-fixer.dist.php

# Add entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create a non-root user to run the application
RUN adduser --disabled-password --gecos "" keyroll && \
    chown -R keyroll:keyroll /var/www/html

USER keyroll

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

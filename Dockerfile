# Stage 1: Build Environment (incl. Dev Dependencies, Node.js, etc.)
FROM php:8.4-fpm AS php_build

# Install system and PHP dependencies (incl. build-essentials etc. if needed)
# Sorted alphabetically for better readability
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    git \
    libicu-dev \      # Required for the intl PHP extension
    libpq-dev \       # Required for the pdo_pgsql PHP extension
    libzip-dev \      # Required for the zip PHP extension
    nodejs \
    npm \
    postgresql-client \ # For potential build scripts interacting with DB
    unzip \
    zip \
    # Clean up apt cache in the same RUN layer
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
# Copy Tailwind config early if needed
COPY tailwind.config.js ./
COPY assets/styles/app.css ./assets/styles/ # Assumption: Path to your main CSS file

# Install Composer dev dependencies for build steps
# --no-scripts because we run them later after copying the full code
# --no-autoloader because we dump it later
RUN composer install --prefer-dist --no-scripts --no-progress --no-autoloader

# Install NPM dependencies
RUN npm install

# Copy the rest of the application
COPY . .

# Execute Composer scripts (incl. post-install-cmd)
# Set APP_ENV temporarily here for scripts if needed (e.g., for AssetMapper)
# Important: Do NOT use --no-dev here, as scripts might need dev dependencies (like asset building)
RUN composer dump-autoload --optimize && \
    APP_ENV=prod composer run-script post-install-cmd

# Build Tailwind assets for production
# APP_ENV=prod is important for production builds (minification etc.)
RUN mkdir -p var/tailwind && \
    APP_ENV=prod php bin/console tailwind:build --minify

# Clean up build artifacts and install only production Composer dependencies
# --no-scripts, as they have already been executed
# --optimize-autoloader for production performance
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-progress && \
    rm -rf node_modules tests phpstan.dist.neon phpmd.xml.dist phpunit.xml.dist .gitignore .php-cs-fixer.dist.php var/cache/* var/log/*

# -----------------------------------------------------------------------------

# Stage 2: Final Runtime Environment
FROM php:8.4-fpm AS php_runtime

# Install only necessary runtime system dependencies
# Sorted alphabetically for better readability
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    gettext \         # Often needed for Symfony translations
    libicu-dev \      # Required for the intl PHP extension
    libpq-dev \       # Required for the pdo_pgsql PHP extension
    postgresql-client \ # Useful for debugging or entrypoint scripts (e.g., doctrine:query:sql)
    # Clean up apt cache in the same RUN layer
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
    zip # If needed at runtime (e.g., for unpacking archives)

WORKDIR /var/www/html

# Copy only the built application artifact from the build stage
# This includes vendor dir (with prod deps), built assets, optimized autoloader etc.
COPY --from=php_build /var/www/html /var/www/html

# Copy the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create the non-root user and set permissions for 'var' directory
# Important: Create the directories before changing permissions
RUN mkdir -p var/cache var/log var/ssh && \
    useradd -ms /bin/bash keyroll && \
    # Set the owner for the entire app directory and the specific var folders
    # PHP-FPM needs write access to cache and logs
    chown -R keyroll:keyroll /var/www/html

# Switch to the non-root user
USER keyroll

# Expose the port PHP-FPM listens on
EXPOSE 9000

# Define the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command to run PHP-FPM
CMD ["php-fpm"]

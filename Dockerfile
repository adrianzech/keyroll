# Stage 1: Build-Umgebung (inkl. Dev-Dependencies, Node.js, etc.)
FROM php:8.4-fpm AS php_build

# Installiere System- und PHP-Abhängigkeiten (inkl. build-essentials etc. falls nötig)
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    git \
    unzip \
    zip \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    postgresql-client \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Installiere PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    opcache \
    intl

# Installiere Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Kopiere Abhängigkeitsdateien
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./
# Kopiere Tailwind Config früh, falls benötigt
COPY tailwind.config.js ./
COPY assets/styles/app.css ./assets/styles/ # Annahme: Pfad zu deiner Haupt-CSS-Datei

# Installiere Composer Dev-Abhängigkeiten für Build-Schritte
RUN composer install --prefer-dist --no-scripts --no-progress --no-autoloader

# Installiere NPM Abhängigkeiten
RUN npm install

# Kopiere den Rest der Anwendung
COPY . .

# Führe Composer Scripts aus (inkl. post-install-cmd)
# Setze APP_ENV hier temporär für Scripts, falls nötig (z.B. für AssetMapper)
# Wichtig: Verwende --no-dev NICHT hier, da Scripts dev-deps benötigen könnten
RUN composer dump-autoload --optimize && \
    APP_ENV=prod composer run-script post-install-cmd

# Baue Tailwind Assets für Produktion
# APP_ENV=prod ist wichtig für Production Builds (Minimierung etc.)
RUN mkdir -p var/tailwind && \
    APP_ENV=prod php bin/console tailwind:build --minify

# Bereinige Build-Artefakte und installiere nur Production-Composer-Deps
# --no-scripts, da sie schon ausgeführt wurden
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-scripts --no-progress && \
    rm -rf node_modules tests phpstan.dist.neon phpmd.xml.dist phpunit.xml.dist .gitignore .php-cs-fixer.dist.php

# Stage 2: Finale Runtime-Umgebung
FROM php:8.4-fpm AS php_runtime

# Installiere nur notwendige Runtime-Systemabhängigkeiten
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    libicu-dev \ # Für Intl
    libpq-dev \ # Für PDO PgSQL
    postgresql-client \ # Für `doctrine:query:sql` im Entrypoint
    # gettext ist oft für Übersetzungen nötig
    gettext \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Kopiere PHP-Konfiguration (Opcache etc.)
COPY --from=php_build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Installiere notwendige Runtime-PHP-Extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    opcache \
    zip # Falls zur Laufzeit benötigt

WORKDIR /var/www/html

# Kopiere nur das gebaute Artefakt aus der Build-Stage
COPY --from=php_build /var/www/html /var/www/html

# Kopiere den Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Erstelle den non-root user und setze Berechtigungen für 'var' und 'var/ssh'
# Wichtig: Erstelle die Verzeichnisse, bevor du die Rechte änderst
RUN mkdir -p var/cache var/log var/ssh && \
    useradd -ms /bin/bash keyroll && \
    # Setze den Owner für das gesamte App-Verzeichnis und die spezifischen var-Ordner
    chown -R keyroll:keyroll /var/www/html

USER keyroll

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

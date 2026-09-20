# -----------------------------------------------------------------------------

# Base stage: PHP-FPM with common extensions and Composer
# -----------------------------------------------------------------------------
FROM php:8.3-fpm AS base

LABEL maintainer="Cafe Supply Chain"

# Install system dependencies
# nodejs is required by app/Services/H3Service.php (scripts/h3.cjs), and npm
# with it: Debian ships them as separate packages, and the prod stage installs
# h3-js. Without npm that stage cannot build at all.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    nodejs \
    npm \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions. GD is configured with JPEG: without it PHP cannot
# produce a .jpg at all, which is what receipt photos and product images are.
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension (phpredis)
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# -----------------------------------------------------------------------------
# Development stage: bind-mount friendly, dev deps included
# -----------------------------------------------------------------------------
FROM base AS dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php/uploads.ini "$PHP_INI_DIR/conf.d/uploads.ini"
# ponytail: dev opcache disabled to pick up bind-mount changes immediately
RUN echo "opcache.enable=0" > "$PHP_INI_DIR/conf.d/opcache-dev.ini"

# Install xdebug for development (optional, can be enabled via environment)
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY . .
RUN composer install --no-interaction --optimize-autoloader \
    && mkdir -p /var/www/storage/framework/cache /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/logs /var/www/storage/app/public \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Production stage: optimized, no dev deps, owned by www-data
# -----------------------------------------------------------------------------
FROM base AS prod

# PHP production tuning
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/uploads.ini "$PHP_INI_DIR/conf.d/uploads.ini"
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# Copy dependency manifests first for better layer caching
COPY composer.json composer.lock package.json package-lock.json ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader

# ponytail: h3-js is a runtime dependency of the PHP app, not build tooling —
# H3Service shells out to scripts/h3.cjs. .dockerignore excludes node_modules,
# and nothing else installs it, so an image without this line answers every
# zone and address request with the "hexagon service is missing" sentence and
# can never resolve a delivery fee.
RUN npm ci --omit=dev --no-audit --no-fund

COPY . .
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && php artisan route:cache \
    && mkdir -p /var/www/storage/framework/cache /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/logs /var/www/storage/app/public \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

USER www-data

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

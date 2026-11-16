FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql pdo_sqlite bcmath gd exif pcntl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && cp /var/www/database/demo.sqlite /var/www/database/database.sqlite \
    && chmod 666 /var/www/database/database.sqlite /var/www/database/demo.sqlite \
    && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x /var/www/docker-entrypoint.sh

EXPOSE 8000
CMD ["/var/www/docker-entrypoint.sh"]

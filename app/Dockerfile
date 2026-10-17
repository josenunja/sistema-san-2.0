FROM composer:2.8 AS composer
FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip git && docker-php-ext-configure gd --with-jpeg --with-webp && docker-php-ext-install pdo_pgsql intl zip bcmath pcntl gd && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

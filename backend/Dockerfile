FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    nginx \
    supervisor \
    libpq-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi

# OPcache / realpath tuning for the long-running php-fpm workers.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Nginx site (listens on 8000, proxies to php-fpm on 127.0.0.1:9000).
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Supervisor runs php-fpm + nginx together.
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# One-shot OPcache warmup (runs on container start).
COPY docker/warmup.sh /usr/local/bin/warmup.sh
RUN chmod +x /usr/local/bin/warmup.sh

EXPOSE 8000
EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

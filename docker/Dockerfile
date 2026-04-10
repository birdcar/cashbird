# Stage 1: Build frontend assets
FROM oven/bun:1-alpine AS assets
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY vite.config.js ./
COPY resources/ ./resources/
RUN bun run build

# Stage 2: Install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist

# Stage 3: Production image
FROM php:8.4-fpm-alpine AS production

RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    redis \
    && docker-php-ext-install pdo_pgsql opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

RUN mkdir -p /var/log/supervisor

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

RUN rm -rf docker node_modules tests .env .env.example \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN echo '* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1' | crontab -

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

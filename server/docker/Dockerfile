# syntax=docker/dockerfile:1.7
#
# Build context = repo root (so the same `server/...` paths used by the web
# image apply here too — keeps Vite output deterministic across both images).

# ----- Stage 1: composer dependencies (PHP 8.4 to match runtime) -----
FROM php:8.4-cli-alpine AS vendor

RUN apk add --no-cache git unzip libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install -j$(nproc) intl zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
COPY server/composer.json server/composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# ----- Stage 2: frontend assets (Vite + Tailwind) -----
# IMPORTANT: keep this stage byte-identical to the one in server/docker/nginx.Dockerfile —
# same base image, same COPY layout, same npm command. Identical inputs ⇒ identical
# content hashes ⇒ both images reference the same /build/assets/app-XXXX.css.
FROM node:22-alpine AS assets

WORKDIR /app
COPY server/package.json server/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY server/postcss.config.js server/tailwind.config.js server/vite.config.js ./
COPY server/resources/ resources/
RUN npm run build

# ----- Stage 3: final runtime (php-fpm) -----
FROM php:8.4-fpm-alpine AS runtime

# System deps required by Laravel + sqlite + intl + zip
RUN apk add --no-cache \
        bash \
        sqlite \
        sqlite-libs \
        icu-libs \
        libzip \
        oniguruma \
        zip \
        unzip \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        sqlite-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        intl \
        opcache \
        zip \
        bcmath \
    && apk del --no-cache .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

# OPcache tuning for production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.jit_buffer_size=64M'; \
        echo 'opcache.jit=1255'; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini

COPY server/docker/php.ini /usr/local/etc/php/conf.d/zz-nicewatch.ini

# Install composer binary (for occasional `composer dump-autoload` in entrypoint)
COPY --from=vendor /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy application code (server/.dockerignore excludes vendor, node_modules, .env, etc.)
COPY --chown=www-data:www-data server/ /var/www/html

# Bring in installed vendor + built assets from earlier stages
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Regenerate optimized autoloader now that the full app tree is present
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# Storage + cache + sqlite directories must be writable.
# Note: /var/lib/nicewatch holds the SQLite database — it's an external mount
# in compose (volume `app-data`) so app/database/migrations is NOT overlaid.
RUN set -eux; \
    mkdir -p \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /var/lib/nicewatch; \
    chown -R www-data:www-data storage bootstrap/cache /var/lib/nicewatch; \
    chmod -R 775 storage bootstrap/cache /var/lib/nicewatch

COPY server/docker/entrypoint.sh /usr/local/bin/nicewatch-entrypoint
RUN chmod +x /usr/local/bin/nicewatch-entrypoint

EXPOSE 9000
ENTRYPOINT ["nicewatch-entrypoint"]
CMD ["php-fpm", "-F"]

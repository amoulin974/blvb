# syntax=docker/dockerfile:1
#############################################
# Base image (FrankenPHP + PHP 8.4)
#############################################
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

# Install system + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    file \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN git config --global --add safe.directory /app

RUN set -eux; \
    install-php-extensions \
        @composer \
        apcu \
        intl \
        opcache \
        zip \
        pdo_mysql \
        gd \
        mbstring \
        xml \
        xmlwriter \
        dom \
        fileinfo \
		bcmath \
		sodium

# Composer config
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

# Custom config (Caddy / PHP)
COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

# Execute /usr/local/bin/docker-entrypoint du container
ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]


#############################################
# Dev environment
#############################################
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch"]

#############################################
# Production image
#############################################
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Install dependencies (cache Docker optimisé) : Docker copie un premier composer.json et composer.lock.
COPY --link composer.* symfony.* ./

# en prod, Installer dépendances SANS scripts
RUN set -eux; \
  composer install --no-dev --prefer-dist --no-progress --no-scripts --no-interaction

# Copier le reste de l'app : Cette commande recopie tout le projet par-dessus ce qui a été installé précédemment.
# il copie donc aussi le dossier vendor
COPY --link --exclude=frankenphp/ . ./

# Final build steps Symfony (execution des scripts)
RUN set -eux; \
    mkdir -p var/cache var/log var/share; \
    composer dump-autoload --classmap-authoritative --no-dev; \
	chmod +x bin/console; \
	php bin/console cache:clear --env=prod; \
	php bin/console tailwind:build --minify; \
	php bin/console importmap:install; \
	php bin/console asset-map:compile; \
    sync;


	    
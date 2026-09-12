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

# Install dependencies (cache Docker optimisé)
COPY --link composer.* symfony.* ./

# Ces lignes ne sont plus forcemenet necessaires à cause des commandes php bin/console dans le dernier RUN
#RUN set -eux; \
# composer run-script post-install-cmd --no-dev || true
# composer run-script post-install-cmd -vvv || true

# en prod
# Installer dépendances SANS scripts
RUN set -eux; \
#composer install --no-dev --prefer-dist --no-progress --no-interaction
#composer install --no-dev --prefer-dist --no-progress --optimize-autoloader --no-interaction
  composer install --no-dev --prefer-dist --no-progress --no-scripts --no-interaction

# en debug
# RUN composer install -vvv --no-interaction || true	

# Aide
# --no-scripts peremt de ne lancer aucun script
# -vvv --no-interaction permet de debugger
	
# Copier le reste de l'app
COPY --link --exclude=frankenphp/ . ./

# Final build steps Symfony (execution des scripts)
RUN set -eux; \
    mkdir -p var/cache var/log var/share; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    #composer run-script post-install-cmd --no-dev || true; \
    chmod +x bin/console; \
	php bin/console cache:clear --env=prod; \
	php bin/console tailwind:build --minify; \
	php bin/console asset-map:compile; \
    sync;
	
	#composer dump-env prod; \
	    
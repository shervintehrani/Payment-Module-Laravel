# syntax=docker/dockerfile:1
#
# Multi-stage image for developing, testing and demoing the package.
#
#   base  PHP CLI + Composer + common extensions
#   dev   Mounts the package and runs the test suite
#   demo  A throw-away Laravel app that installs the package from the mounted
#         source and serves it on http://localhost:8000
#
ARG PHP_VERSION=8.3

# ---------------------------------------------------------------- base
FROM php:${PHP_VERSION}-cli AS base

# install-php-extensions handles build dependencies and cleans up after itself.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions @composer zip pdo_mysql bcmath intl pcntl

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# The package is bind-mounted from the host, so git must trust its owner.
RUN git config --global --add safe.directory '*'

# ---------------------------------------------------------------- dev
FROM base AS dev

WORKDIR /package

# Dependencies are installed at run time into the mounted folder (see
# docker-compose.yml), so vendor/ stays available on the host for your IDE.
CMD ["sh", "-c", "composer install --no-interaction --prefer-dist && composer test"]

# ---------------------------------------------------------------- demo
FROM base AS demo

# Bake a fresh Laravel app into the image so container start-up is fast.
RUN composer create-project laravel/laravel:^11.0 /var/www/demo \
    --prefer-dist --no-interaction --no-progress

WORKDIR /var/www/demo

COPY docker/demo-entrypoint.sh /usr/local/bin/demo-entrypoint
RUN chmod +x /usr/local/bin/demo-entrypoint

EXPOSE 8000
ENTRYPOINT ["demo-entrypoint"]

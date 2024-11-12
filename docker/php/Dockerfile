FROM composer:2.7.1 as composer

#############################################################################

FROM php:8.3-fpm-alpine as base

WORKDIR /app/

RUN chown -R www-data:www-data /app/

RUN docker-php-ext-install pdo pdo_mysql

RUN apk update && apk add --no-cache \
    git \
    unzip

RUN adduser --disabled-password --no-create-home --home /app/ --gecos '' app
WORKDIR /app/
RUN chown -R app:app /app/ \
    && mkdir -p /app/php-file-cache/

#############################################################################
FROM base as build

COPY --from=composer  /usr/bin/composer /usr/bin/composer

COPY composer.* /app/

USER app
RUN /usr/bin/composer install --no-progress --no-scripts \
    && composer dump-autoload --optimize \
    && rm -rf

USER root

#############################################################################

FROM build as development

USER root

COPY --chown=app:app --from=build /app /app
COPY --chown=app:app . /app
USER app
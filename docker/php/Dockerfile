FROM composer:2.7.1 as composer

#############################################################################

FROM php:8.4-fpm-alpine as base

WORKDIR /app/

RUN chown -R www-data:www-data /app/

RUN docker-php-ext-install pdo pdo_mysql
RUN apk add --no-cache autoconf g++ make && \
    pecl install redis && \
    docker-php-ext-enable redis

RUN apk update && apk add --no-cache \
    git \
    unzip

RUN adduser --disabled-password --no-create-home --home /app/ --gecos '' app
WORKDIR /app/
RUN chown -R app:app /app/ \
    && mkdir -p /app/php-file-cache/

RUN apk add --no-cache curl bash \
    && curl -fsSL https://bun.sh/install | bash

ENV BUN_INSTALL=/root/.bun
ENV PATH=$BUN_INSTALL/bin:$PATH


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

FROM build as production

USER root

RUN curl -L https://github.com/aptible/supercronic/releases/download/v0.2.37/supercronic-linux-amd64 \
    -o /usr/local/bin/supercronic && \
    chmod +x /usr/local/bin/supercronic

COPY ./crontab /etc/crontab

COPY --chown=app:app --from=build /app /app
COPY --chown=app:app . /app

RUN bun install \
    && bun run build:analytics

USER app
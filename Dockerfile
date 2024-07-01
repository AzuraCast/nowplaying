FROM mlocati/php-extension-installer AS php-extension-installer

FROM php:8.3-cli-alpine3.19 AS base

ENV TZ=UTC

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions @composer gd curl xml zip bcmath mbstring intl sockets

RUN apk add --update --no-cache \
    zip git curl bash \
    su-exec

WORKDIR /app

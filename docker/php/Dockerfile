FROM php:8.3-fpm-alpine

RUN apk update && \
    apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    linux-headers && \
    apk add --no-cache \
    git \
    curl \
    libxml2-dev && \
    docker-php-ext-install soap opcache && \
    pecl install redis && \
    docker-php-ext-enable redis

ARG IS_XDEBUG=false
RUN if [ "$IS_XDEBUG" = "true" ]; then \
        pecl install xdebug && \
        docker-php-ext-enable xdebug; \
    fi

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Удобный пользователь (чтобы не было проблем с правами)
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} www && \
    adduser -D -u ${UID} -G www www && \
    apk del .build-deps && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/www/html
COPY --chown=www:www . .

USER www

# CMD ["php-fpm"]

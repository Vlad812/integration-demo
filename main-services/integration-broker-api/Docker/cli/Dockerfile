FROM php:8.5-cli

ARG PHP_REDIS_VERSION=6.3.0
ARG PHP_AMQP_VERSION=2.2.0

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        librabbitmq-dev \
        libpq-dev \
        pkg-config \
        $PHPIZE_DEPS; \
    docker-php-ext-install sockets pdo_pgsql; \
    curl -fsSL "https://github.com/phpredis/phpredis/archive/refs/tags/${PHP_REDIS_VERSION}.tar.gz" \
        | tar xz -C /tmp; \
    cd "/tmp/phpredis-${PHP_REDIS_VERSION}"; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable redis; \
    cd /; \
    curl -fsSL "https://github.com/php-amqp/php-amqp/archive/refs/tags/v${PHP_AMQP_VERSION}.tar.gz" \
        | tar xz -C /tmp; \
    cd "/tmp/php-amqp-${PHP_AMQP_VERSION}"; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable amqp; \
    rm -rf "/tmp/phpredis-${PHP_REDIS_VERSION}" "/tmp/php-amqp-${PHP_AMQP_VERSION}"; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY Docker/cli/outbox-relay-entrypoint.sh /outbox-relay-entrypoint.sh
COPY Docker/cli/messenger-consume-entrypoint.sh /messenger-consume-entrypoint.sh
COPY Docker/cli/scheduler-consume-entrypoint.sh /scheduler-consume-entrypoint.sh
RUN sed -i 's/\r$//' /outbox-relay-entrypoint.sh && chmod +x /outbox-relay-entrypoint.sh \
    && sed -i 's/\r$//' /messenger-consume-entrypoint.sh && chmod +x /messenger-consume-entrypoint.sh \
    && sed -i 's/\r$//' /scheduler-consume-entrypoint.sh && chmod +x /scheduler-consume-entrypoint.sh

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

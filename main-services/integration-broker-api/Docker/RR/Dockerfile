FROM ghcr.io/roadrunner-server/roadrunner:2025.1.0 AS roadrunner
FROM php:8.5-cli

ARG PHP_REDIS_VERSION=6.3.0
ARG PHP_AMQP_VERSION=2.2.0

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        libonig-dev \
        librabbitmq-dev \
        libpq-dev \
        pkg-config \
        $PHPIZE_DEPS; \
    docker-php-ext-install sockets mbstring pdo_pgsql; \
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

WORKDIR /var/www/html

CMD ["rr", "serve", "-c", ".rr.dev.yaml"]

#!/bin/sh
set -e

RABBITMQ_HOST="${RABBITMQ_HOST:-cs-rabbitmq}"
RABBITMQ_PORT="${RABBITMQ_PORT:-5672}"
RABBITMQ_USER="${RABBITMQ_USER:-my_rabbit}"
RABBITMQ_PASS="${RABBITMQ_PASS:-rabbit}"
RABBITMQ_VHOST="${RABBITMQ_VHOST:-integration}"

echo "Waiting for RabbitMQ at ${RABBITMQ_HOST}:${RABBITMQ_PORT} (vhost: ${RABBITMQ_VHOST})..."

until php -r "try { \$c = new AMQPConnection(['host' => '${RABBITMQ_HOST}', 'port' => (int) '${RABBITMQ_PORT}', 'login' => '${RABBITMQ_USER}', 'password' => '${RABBITMQ_PASS}', 'vhost' => '${RABBITMQ_VHOST}']); \$c->connect(); exit(0); } catch (Throwable \$e) { exit(1); }"; do
    sleep 2
done

echo "RabbitMQ is ready"

php bin/console cache:clear --no-warmup
# Consume ONLY doctrine outbox. Never pass --all / broker: that hits AMQP default queue "messages".
exec php bin/console messenger:consume outbox --no-interaction -vv --sleep=1

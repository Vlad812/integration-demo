#!/bin/sh
set -e

php bin/console cache:clear --no-warmup
echo "Starting messenger:consume scheduler_order_polling"
exec php bin/console messenger:consume scheduler_order_polling --no-interaction -vv --sleep=1

#!/bin/sh
set -eu

mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar
chown -R www-data:www-data writable

if [ "${AMS_RUN_MIGRATIONS:-0}" = "1" ]; then
    php spark migrate --all
fi

exec "$@"

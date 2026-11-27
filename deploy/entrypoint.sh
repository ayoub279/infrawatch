#!/bin/sh
set -eu
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
exec docker-php-entrypoint "$@"

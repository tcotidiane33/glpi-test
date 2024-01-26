#!/bin/bash -e

TMP_CACHE_DIR=$(mktemp -d -t glpi-cache-test-XXXXXXXXXX)

for CONFIG in {"--use-default","--dsn=memcached://memcached","--dsn=redis://redis"}; do
  echo "Test cache using following config: $CONFIG"
  php bin/console cache:configure \
    --config-dir=./tests/config --ansi --no-interaction \
    $CONFIG
  vendor/bin/atoum \
    -p 'php -d memory_limit=512M' \
    --debug \
    --force-terminal \
    --use-dot-report \
    --bootstrap-file tests/bootstrap.php \
    --fail-if-skipped-methods \
    --no-code-coverage \
    -d tests/units \
    -t cache
  vendor/bin/atoum \
    -p 'php -d memory_limit=512M' \
    --debug \
    --force-terminal \
    --use-dot-report \
    --bootstrap-file tests/bootstrap.php \
    --fail-if-skipped-methods \
    --no-code-coverage \
    --max-children-number 1 \
    -d tests/functionnal \
    -t cache
done

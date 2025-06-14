#!/usr/bin/env bash
set -e

# Install PHPUnit locally into vendor/bin if not already present
PHPUNIT_BIN="$(dirname "$0")/../vendor/bin/phpunit"

if [ -x "$PHPUNIT_BIN" ]; then
  echo "PHPUnit already installed at $PHPUNIT_BIN"
  exit 0
fi

mkdir -p "$(dirname "$PHPUNIT_BIN")"

if command -v composer >/dev/null 2>&1; then
  composer require --dev --no-progress --no-interaction phpunit/phpunit:^9
else
  curl -L https://phar.phpunit.de/phpunit-9.phar -o "$PHPUNIT_BIN"
  chmod +x "$PHPUNIT_BIN"
fi

echo "PHPUnit installed at $PHPUNIT_BIN"


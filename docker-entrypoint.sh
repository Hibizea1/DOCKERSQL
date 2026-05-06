#!/bin/bash
set -e

# Simple entrypoint: install composer deps for web/php if needed, then start Apache
if command -v composer >/dev/null 2>&1; then
  if [ -f /var/www/html/php/composer.json ]; then
    echo "composer.json found in /var/www/html/php"
    cd /var/www/html/php
    if [ ! -d vendor ]; then
      echo "Installing PHP dependencies with composer..."
      composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction || true
      chown -R www-data:www-data /var/www/html/php
      echo "Composer install completed."
    else
      echo "Vendor directory already present, skipping composer install."
    fi
  fi
fi

exec "$@"

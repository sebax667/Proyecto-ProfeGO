#!/usr/bin/env bash
set -euo pipefail

cd /var/www/profego
sudo -n /usr/local/bin/profego-backup
git fetch --prune
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:css
sudo -n /usr/local/bin/profego-fix-perms
sudo -n /usr/bin/systemctl reload php8.3-fpm

#!/usr/bin/env bash
set -euo pipefail

cd /var/www/profego
sudo install -d -o www-data -g www-data -m 0750 /var/backups/profego
sudo runuser -u www-data -- sqlite3 /var/www/profego/database/database.sqlite ".backup '/var/backups/profego/pre-deploy-$(date +%F-%H%M).sqlite'"
git fetch --prune
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:css
sudo /usr/local/bin/profego-fix-perms
sudo systemctl reload php8.3-fpm

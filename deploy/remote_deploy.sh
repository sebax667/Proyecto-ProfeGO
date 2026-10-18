#!/usr/bin/env bash
set -euo pipefail

cd /var/www/profego
sudo -n /usr/local/bin/profego-backup
sudo -n /usr/local/bin/profego-set-auto-migrate-off

initial_status="$(sudo -n /usr/local/bin/profego-migration-status)"
printf '%s\n' "$initial_status"
if ! grep -qx 'user_version: 2' <<<"$initial_status"; then
    echo "La base no está en user_version 2; se cancela el despliegue." >&2
    exit 1
fi

git fetch --prune
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:css

pending_status="$(sudo -n /usr/local/bin/profego-migration-status)"
printf '%s\n' "$pending_status"
if ! grep -qx 'user_version: 2' <<<"$pending_status" \
    || ! grep -qx 'versión máxima: 9' <<<"$pending_status"; then
    echo "La versión inicial o máxima de migración no coincide con lo esperado." >&2
    exit 1
fi

migration_output="$(sudo -n /usr/local/bin/profego-migrate)"
printf '%s\n' "$migration_output"
if ! grep -qx 'user_version: 9' <<<"$migration_output" \
    || ! grep -qx 'integrity_check: ok' <<<"$migration_output" \
    || ! grep -qx 'foreign_key_check: sin errores' <<<"$migration_output"; then
    echo "La migración no terminó con las verificaciones esperadas." >&2
    exit 1
fi

final_status="$(sudo -n /usr/local/bin/profego-migration-status)"
printf '%s\n' "$final_status"
if ! grep -qx 'user_version: 9' <<<"$final_status" \
    || ! grep -qx 'migraciones pendientes: ninguna' <<<"$final_status"; then
    echo "La verificación final de migraciones falló." >&2
    exit 1
fi

sudo -n /usr/local/bin/profego-fix-perms
sudo -n /usr/bin/systemctl reload php8.3-fpm

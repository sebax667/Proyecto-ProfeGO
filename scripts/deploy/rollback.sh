#!/usr/bin/env bash
set -euo pipefail

app_directory=/var/www/profego
if [ "$#" -ne 0 ]; then
    echo "Este script no acepta argumentos." >&2
    exit 2
fi
if [[ ! "${ROLLBACK_COMMIT:-}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "ROLLBACK_COMMIT debe ser un SHA Git completo de 40 caracteres hexadecimales." >&2
    exit 2
fi
if [[ ! "${HEALTHCHECK_HOST:-}" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]]; then
    echo "HEALTHCHECK_HOST debe ser un nombre de host válido." >&2
    exit 2
fi

cd "$app_directory"
git cat-file -e "${ROLLBACK_COMMIT}^{commit}"
sudo -n /usr/local/sbin/profego-restore-backup
git reset --hard "$ROLLBACK_COMMIT"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:css
sudo -n /usr/local/sbin/profego-fix-perms
sudo -n /usr/local/sbin/profego-reload-services

health_status="$(/usr/bin/curl --silent --show-error --output /dev/null \
    --write-out '%{http_code}' --max-time 20 --max-redirs 3 --location \
    --resolve "${HEALTHCHECK_HOST}:443:127.0.0.1" \
    "https://${HEALTHCHECK_HOST}/catalog")"
if [ "$health_status" != 200 ]; then
    echo "Rollback aplicado, pero /catalog devolvió HTTP ${health_status} inesperado." >&2
    exit 1
fi
echo "Rollback al commit ${ROLLBACK_COMMIT} completado; SQLite restaurada; ruta local /catalog disponible."

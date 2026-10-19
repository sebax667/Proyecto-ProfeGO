#!/usr/bin/env bash
set -euo pipefail

app_directory=/var/www/profego
if [ "$#" -ne 0 ]; then
    echo "Este script no acepta argumentos." >&2
    exit 2
fi
if [[ ! "${DEPLOY_COMMIT:-}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "DEPLOY_COMMIT debe ser un SHA Git completo de 40 caracteres hexadecimales." >&2
    exit 2
fi
if [[ ! "${HEALTHCHECK_HOST:-}" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]]; then
    echo "HEALTHCHECK_HOST debe ser un nombre de host válido." >&2
    exit 2
fi

cd "$app_directory"
previous_commit="$(git rev-parse --verify HEAD)"
if [[ ! "$previous_commit" =~ ^[0-9a-f]{40}$ ]]; then
    echo "No se pudo determinar el commit actualmente desplegado." >&2
    exit 1
fi
printf 'PREVIOUS_COMMIT=%s\n' "$previous_commit"

sudo -n /usr/local/sbin/profego-backup
migration_started=0

rollback() {
    local original_status=$?
    trap - ERR EXIT
    if [ "$original_status" -eq 0 ] || [ "$migration_started" -ne 1 ]; then
        return
    fi

    set +e
    printf 'El despliegue falló; restaurando código y SQLite al commit %s.\n' "$previous_commit" >&2
    sudo -n /usr/local/sbin/profego-restore-backup
    local restore_status=$?
    git reset --hard "$previous_commit"
    local git_status=$?
    composer install --no-dev --optimize-autoloader --no-interaction
    local composer_status=$?
    npm ci
    local npm_status=$?
    npm run build:css
    local build_status=$?
    sudo -n /usr/local/sbin/profego-mark-healthcheck-start
    sudo -n /usr/local/sbin/profego-fix-perms
    local perms_status=$?
    sudo -n /usr/local/sbin/profego-reload-services
    local reload_status=$?
    local rollback_health_status=1
    local rollback_health
    rollback_health="$(/usr/bin/curl --silent --show-error --output /dev/null \
        --write-out '%{http_code}' --max-time 20 --max-redirs 3 --location \
        --resolve "${HEALTHCHECK_HOST}:443:127.0.0.1" \
        "https://${HEALTHCHECK_HOST}/catalog")"
    if [ "$rollback_health" = 200 ]; then
        rollback_health_status=0
    fi

    if [ "$restore_status" -ne 0 ] || [ "$git_status" -ne 0 ] \
        || [ "$composer_status" -ne 0 ] || [ "$npm_status" -ne 0 ] \
        || [ "$build_status" -ne 0 ] || [ "$perms_status" -ne 0 ] \
        || [ "$reload_status" -ne 0 ] || [ "$rollback_health_status" -ne 0 ]; then
        echo "ERROR: el rollback automático no se completó por completo; intervención manual requerida." >&2
    else
        echo "Rollback automático completado." >&2
    fi
    exit "$original_status"
}
trap rollback EXIT
echo "Aviso: se espera HTTP 503 temporal mientras el esquema esté pendiente de migración."
sudo -n /usr/local/sbin/profego-set-auto-migrate-off

initial_status="$(sudo -n /usr/local/sbin/profego-migration-status)"
printf '%s\n' "$initial_status"
initial_version="$(/usr/bin/sed -n 's/^user_version: //p' <<<"$initial_status")"
if [[ ! "$initial_version" =~ ^[0-9]+$ ]] || [ "$initial_version" -lt 2 ]; then
    echo "La base no tiene un esquema de producción válido (se esperaba versión 2 o posterior)." >&2
    exit 1
fi

git fetch --no-tags origin main
git fetch --no-tags origin "$DEPLOY_COMMIT"
git cat-file -e "${DEPLOY_COMMIT}^{commit}"
git merge-base --is-ancestor "$DEPLOY_COMMIT" origin/main
git reset --hard "$DEPLOY_COMMIT"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:css

pending_status="$(sudo -n /usr/local/sbin/profego-migration-status)"
printf '%s\n' "$pending_status"
pending_version="$(/usr/bin/sed -n 's/^user_version: //p' <<<"$pending_status")"
maximum_version="$(/usr/bin/sed -n 's/^versión máxima: //p' <<<"$pending_status")"
if [[ ! "$pending_version" =~ ^[0-9]+$ ]] || [[ ! "$maximum_version" =~ ^[0-9]+$ ]] \
    || [ "$pending_version" -ne "$initial_version" ] \
    || [ "$maximum_version" -lt 9 ] \
    || [ "$pending_version" -gt "$maximum_version" ]; then
    echo "El estado de migraciones no coincide con la versión de despliegue esperada." >&2
    exit 1
fi

migration_started=1
migration_output="$(sudo -n /usr/local/sbin/profego-migrate)"
printf '%s\n' "$migration_output"
final_version="$(/usr/bin/sed -n 's/^user_version: //p' <<<"$migration_output")"
if [[ ! "$final_version" =~ ^[0-9]+$ ]] \
    || [ "$final_version" -ne "$maximum_version" ] \
    || ! /usr/bin/grep -qx 'integrity_check: ok' <<<"$migration_output" \
    || ! /usr/bin/grep -qx 'foreign_key_check: sin errores' <<<"$migration_output"; then
    echo "La migración falló la verificación de versión, integridad o claves foráneas." >&2
    exit 1
fi

final_status="$(sudo -n /usr/local/sbin/profego-migration-status)"
printf '%s\n' "$final_status"
if ! /usr/bin/grep -qx "user_version: ${maximum_version}" <<<"$final_status" \
    || ! /usr/bin/grep -qx 'migraciones pendientes: ninguna' <<<"$final_status"; then
    echo "La verificación final de migraciones falló." >&2
    exit 1
fi

sudo -n /usr/local/sbin/profego-mark-healthcheck-start
sudo -n /usr/local/sbin/profego-fix-perms
sudo -n /usr/local/sbin/profego-reload-services
health_status="$(/usr/bin/curl --silent --show-error --output /dev/null \
    --write-out '%{http_code}' --max-time 20 --max-redirs 3 --location \
    --resolve "${HEALTHCHECK_HOST}:443:127.0.0.1" \
    "https://${HEALTHCHECK_HOST}/catalog")"
if [ "$health_status" != 200 ]; then
    echo "La ruta local /catalog devolvió HTTP ${health_status} inesperado." >&2
    exit 1
fi

sudo -n /usr/local/sbin/profego-check-service-logs

migration_started=0
trap - ERR EXIT
echo "Despliegue completado: ${DEPLOY_COMMIT}; user_version ${maximum_version}; /catalog HTTP 200."

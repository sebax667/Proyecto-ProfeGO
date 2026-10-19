#!/bin/bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/profego}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
DEPLOY_USER="${DEPLOY_USER:-}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
ENV_FILE="${APP_DIR}/.env"
DATABASE_DIR="${APP_DIR}/database"
DATABASE_FILE="${DATABASE_DIR}/database.sqlite"

if [ "$(id -u)" -ne 0 ]; then
    echo "Ejecuta este script como root: sudo $0" >&2
    exit 1
fi

if [ ! -d "${APP_DIR}" ]; then
    echo "No existe el directorio de la aplicación: ${APP_DIR}" >&2
    exit 1
fi

if [ "${APP_DIR}" != /var/www/profego ] || [ "${APP_USER}" != www-data ] \
    || [ "${APP_GROUP}" != www-data ] || [ "${PHP_FPM_SERVICE}" != php8.3-fpm ]; then
    echo "Los helpers de despliegue requieren las rutas y servicios ProfeGo estándar." >&2
    exit 1
fi

if [ -z "${DEPLOY_USER}" ]; then
    DEPLOY_USER="$(stat -c '%U' "${APP_DIR}")"
fi

if [[ ! "${DEPLOY_USER}" =~ ^[a-z_][a-z0-9_-]{0,30}[$]?$ ]]; then
    echo "El nombre del usuario de despliegue no es válido." >&2
    exit 1
fi

if ! id "${APP_USER}" >/dev/null 2>&1; then
    echo "El usuario de aplicación no existe: ${APP_USER}" >&2
    exit 1
fi

if ! id "${DEPLOY_USER}" >/dev/null 2>&1 || [ "${DEPLOY_USER}" = root ]; then
    echo "El usuario de despliegue no es válido: ${DEPLOY_USER}" >&2
    exit 1
fi

install -d -o "${DEPLOY_USER}" -g "${APP_GROUP}" -m 0770 "${DATABASE_DIR}"

if [ ! -f "${DATABASE_FILE}" ]; then
    install -o "${APP_USER}" -g "${APP_GROUP}" -m 0660 /dev/null "${DATABASE_FILE}"
fi

if [ ! -f "${ENV_FILE}" ]; then
    install -o "${APP_USER}" -g "${APP_GROUP}" -m 0640 /dev/null "${ENV_FILE}"
    if ! command -v openssl >/dev/null 2>&1; then
        echo "OpenSSL es necesario para generar JWT_SECRET." >&2
        exit 1
    fi
    cat > "${ENV_FILE}" <<'EOF'
APP_ENV=production
APP_DEBUG=false
AI_PROVIDER=mock
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
EOF
    printf 'JWT_SECRET=%s\n' "$(openssl rand -hex 32)" >> "${ENV_FILE}"
elif ! grep -q '^JWT_SECRET=' "${ENV_FILE}"; then
    if ! command -v openssl >/dev/null 2>&1; then
        echo "OpenSSL es necesario para generar JWT_SECRET." >&2
        exit 1
    fi
    printf 'JWT_SECRET=%s\n' "$(openssl rand -hex 32)" >> "${ENV_FILE}"
fi

chown "${APP_USER}:${APP_GROUP}" "${ENV_FILE}"
chmod 0640 "${ENV_FILE}"

chgrp -R "${APP_GROUP}" "${APP_DIR}"
chown "${DEPLOY_USER}:${APP_GROUP}" "${DATABASE_DIR}"
chmod 0770 "${DATABASE_DIR}"
chown "${APP_USER}:${APP_GROUP}" "${DATABASE_FILE}"
chmod 0660 "${DATABASE_FILE}"

find "${APP_DIR}" -type d -exec chmod 0750 {} \;
find "${APP_DIR}" -type f -exec chmod 0640 {} \;
find "${APP_DIR}/public" -type f -exec chmod 0755 {} \;
chmod 0750 "${APP_DIR}/public"
chmod 0770 "${DATABASE_DIR}"
chmod 0640 "${ENV_FILE}"
chmod 0660 "${DATABASE_FILE}"

if [ ! -f "${APP_DIR}/composer.json" ]; then
    echo "No existe composer.json en ${APP_DIR}." >&2
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "Composer no está instalado o no está disponible en PATH." >&2
    exit 1
fi

run_as_deploy_user() {
    if command -v runuser >/dev/null 2>&1; then
        runuser -u "${DEPLOY_USER}" -- "$@"
    else
        sudo -u "${DEPLOY_USER}" -- "$@"
    fi
}

run_as_deploy_user composer install \
    --working-dir="${APP_DIR}" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

APP_DIR="${APP_DIR}" bash "${APP_DIR}/scripts/deploy/install_helpers.sh"

if command -v systemctl >/dev/null 2>&1; then
    systemctl reload "${PHP_FPM_SERVICE}" 2>/dev/null || systemctl restart "${PHP_FPM_SERVICE}"
    systemctl reload nginx
fi

echo "Configuración de producción aplicada en ${APP_DIR}."
echo "Edita ${ENV_FILE} y establece OPENAI_API_KEY solo si usarás el adapter OpenAI."

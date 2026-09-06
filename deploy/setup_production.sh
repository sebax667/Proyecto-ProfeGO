#!/bin/bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/profego}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
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

if ! id "${APP_USER}" >/dev/null 2>&1; then
    echo "El usuario de aplicación no existe: ${APP_USER}" >&2
    exit 1
fi

install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0750 "${DATABASE_DIR}"

if [ ! -f "${DATABASE_FILE}" ]; then
    install -o "${APP_USER}" -g "${APP_GROUP}" -m 0660 /dev/null "${DATABASE_FILE}"
fi

if [ ! -f "${ENV_FILE}" ]; then
    install -o "${APP_USER}" -g "${APP_GROUP}" -m 0640 /dev/null "${ENV_FILE}"
    cat > "${ENV_FILE}" <<'EOF'
APP_ENV=production
APP_DEBUG=false
AI_PROVIDER=mock
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
EOF
fi

chown "${APP_USER}:${APP_GROUP}" "${ENV_FILE}"
chmod 0640 "${ENV_FILE}"

chown "${APP_USER}:${APP_GROUP}" "${DATABASE_FILE}"
chmod 0660 "${DATABASE_FILE}"

find "${APP_DIR}" -type d -exec chmod 0750 {} \;
find "${APP_DIR}" -type f -exec chmod 0640 {} \;
find "${APP_DIR}/public" -type f -exec chmod 0755 {} \;
chmod 0750 "${APP_DIR}/public"
chmod 0750 "${DATABASE_DIR}"
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

run_as_app_user() {
    if command -v runuser >/dev/null 2>&1; then
        runuser -u "${APP_USER}" -- "$@"
    else
        sudo -u "${APP_USER}" -- "$@"
    fi
}

run_as_app_user composer install \
    --working-dir="${APP_DIR}" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

if command -v systemctl >/dev/null 2>&1; then
    systemctl reload "${PHP_FPM_SERVICE}" 2>/dev/null || systemctl restart "${PHP_FPM_SERVICE}"
    systemctl reload nginx
fi

echo "Configuración de producción aplicada en ${APP_DIR}."
echo "Edita ${ENV_FILE} y establece OPENAI_API_KEY solo si usarás el adapter OpenAI."

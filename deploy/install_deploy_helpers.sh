#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/profego}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Ejecuta este script como root." >&2
    exit 1
fi

if [ "$APP_DIR" != "/var/www/profego" ]; then
    echo "El despliegue remoto está configurado para /var/www/profego." >&2
    exit 1
fi

if [ ! -f "${APP_DIR}/.env" ]; then
    echo "No existe ${APP_DIR}/.env." >&2
    exit 1
fi

install -o root -g root -m 0755 /dev/stdin /usr/local/bin/profego-set-auto-migrate-off <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

env_file=/var/www/profego/.env
if [ "$#" -ne 0 ] || [ ! -f "$env_file" ] || [ -L "$env_file" ]; then
    echo "No se pudo actualizar el archivo .env de forma segura." >&2
    exit 1
fi

temporary_file="$(mktemp "${env_file}.tmp.XXXXXX")"
trap 'rm -f "$temporary_file"' EXIT
awk '
    BEGIN { found = 0 }
    /^[[:space:]]*AUTO_MIGRATE[[:space:]]*=/ {
        if (!found) {
            print "AUTO_MIGRATE=0"
            found = 1
        }
        next
    }
    { print }
    END {
        if (!found) print "AUTO_MIGRATE=0"
    }
' "$env_file" > "$temporary_file"
chown --reference="$env_file" "$temporary_file"
chmod --reference="$env_file" "$temporary_file"
mv -f "$temporary_file" "$env_file"
trap - EXIT
EOF

install -o root -g root -m 0755 /dev/stdin /usr/local/bin/profego-migration-status <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 0 ]; then
    echo "Este comando no acepta argumentos." >&2
    exit 2
fi
cd /var/www/profego
exec runuser -u www-data -- php scripts/migrate.php --status
EOF

install -o root -g root -m 0755 /dev/stdin /usr/local/bin/profego-migrate <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 0 ]; then
    echo "Este comando no acepta argumentos." >&2
    exit 2
fi
cd /var/www/profego
exec runuser -u www-data -- php scripts/migrate.php
EOF

echo "Helpers de despliegue instalados en /usr/local/bin."

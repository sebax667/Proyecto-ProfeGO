#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 0 ]; then
    echo "Este instalador no acepta argumentos." >&2
    exit 2
fi
if [ "$(/usr/bin/id -u)" -ne 0 ]; then
    echo "Ejecuta este instalador como root." >&2
    exit 1
fi

source_directory="$(/usr/bin/dirname -- "$(/usr/bin/readlink -f -- "$0")")"
destination_directory=/usr/local/sbin
helpers=(
    profego-backup
    profego-set-auto-migrate-off
    profego-migration-status
    profego-migrate
    profego-restore-backup
    profego-mark-healthcheck-start
    profego-check-service-logs
    profego-fix-perms
    profego-reload-services
)

for helper in "${helpers[@]}"; do
    source_file="${source_directory}/${helper}"
    if [ ! -f "$source_file" ] || [ -L "$source_file" ]; then
        echo "Helper requerido ausente o no regular: ${helper}" >&2
        exit 1
    fi
done

/usr/bin/install -d -o root -g root -m 0755 "$destination_directory"
for helper in "${helpers[@]}"; do
    /usr/bin/install -o root -g root -m 0755 \
        "${source_directory}/${helper}" "${destination_directory}/${helper}"
done

echo "Helpers ProfeGo instalados en ${destination_directory}."

#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://profego.example.com}"
CURL_TIMEOUT="${CURL_TIMEOUT:-10}"

check_status() {
    local path="$1"
    local expected_status="$2"
    local status

    status="$(curl --silent --show-error --output /dev/null \
        --write-out '%{http_code}' \
        --max-time "${CURL_TIMEOUT}" \
        "${BASE_URL}${path}")"

    if [ "${status}" != "${expected_status}" ]; then
        echo "FALLO: ${path} devolvió HTTP ${status}; esperado ${expected_status}." >&2
        exit 1
    fi

    echo "OK: ${path} -> HTTP ${status}"
}

check_json_contains() {
    local path="$1"
    local expected_text="$2"
    local expected_status="${3:-200}"
    local response
    local status
    response="$(curl --silent --show-error \
        --max-time "${CURL_TIMEOUT}" \
        --header 'Accept: application/json' \
        --write-out $'\n%{http_code}' \
        "${BASE_URL}${path}")"
    status="${response##*$'\n'}"
    response="${response%$'\n'*}"

    if [ "${status}" != "${expected_status}" ]; then
        echo "FALLO: ${path} devolvió HTTP ${status}; esperado ${expected_status}." >&2
        exit 1
    fi

    if ! printf '%s' "${response}" | grep -Fq "${expected_text}"; then
        echo "FALLO: ${path} no contiene la respuesta esperada: ${expected_text}" >&2
        printf 'Respuesta recibida: %s\n' "${response}" >&2
        exit 1
    fi

    echo "OK: ${path} contiene '${expected_text}'"
}

check_status "/catalog" "200"
check_json_contains "/api/ai/keywords" '"status"' "200"

echo "Smoke test completado correctamente contra ${BASE_URL}."

#!/usr/bin/env bash
#
# Startet den Web-Server (PHP built-in) und den WebSocket-Server gemeinsam.
# Beenden mit Strg+C beendet beide Prozesse.
#
set -euo pipefail

# Ins Projektwurzelverzeichnis wechseln (unabhaengig vom Aufrufort).
cd "$(dirname "$0")/.."

: "${WEB_HOST:=127.0.0.1}"
: "${WEB_PORT:=8000}"
: "${WS_HOST:=127.0.0.1}"
: "${WS_PORT:=8080}"
export WEB_HOST WEB_PORT WS_HOST WS_PORT

# Gemeinsames Secret fuer beide Prozesse (signiert/verifiziert die WS-Tokens).
# Einmal erzeugen und via Umgebung an beide Kinder vererben.
export APP_SECRET="${APP_SECRET:-$(php -r 'echo bin2hex(random_bytes(32));')}"

echo "PHP WebSocket Chat wird gestartet…"
echo "  Web-App:          http://${WEB_HOST}:${WEB_PORT}"
echo "  WebSocket-Server: ws://${WS_HOST}:${WS_PORT}"
echo "  (Strg+C beendet beide)"
echo

# Web-Server mit Router (saubere URLs).
php -S "${WEB_HOST}:${WEB_PORT}" -t public public/router.php >/dev/null 2>&1 &
WEB_PID=$!

# WebSocket-Server.
php bin/chat-server.php &
WS_PID=$!

cleanup() {
    echo
    echo "Beende Prozesse…"
    kill "${WEB_PID}" "${WS_PID}" 2>/dev/null || true
}
trap cleanup INT TERM EXIT

# Auf beide warten; endet einer, wird aufgeraeumt.
wait -n "${WEB_PID}" "${WS_PID}" 2>/dev/null || wait

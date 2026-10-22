#!/usr/bin/env bash
# Startet bei jedem Start des Codespaces Datenbank und Anwendung (postStartCommand).
cd "$(dirname "$0")/.."

sudo service mariadb start > /dev/null

if ! pgrep -f "php .*-S 0.0.0.0:8000" > /dev/null; then
    setsid nohup php -d upload_max_filesize=5M -d post_max_size=6M -S 0.0.0.0:8000 -t code/app/public \
        > /tmp/antragsverwaltung.log 2>&1 &
fi

# Port öffentlich machen, damit auch Personen ohne Zugriff auf dieses Repository die Seite öffnen können
sleep 2
if [ -n "${CODESPACE_NAME:-}" ] && command -v gh > /dev/null; then
    gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" > /dev/null 2>&1 \
        || echo "Hinweis: Im Reiter PORTS Rechtsklick auf Port 8000 > Port Visibility > Public."
fi

if [ -n "${CODESPACE_NAME:-}" ]; then
    echo "Antragsverwaltung: https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}/"
fi

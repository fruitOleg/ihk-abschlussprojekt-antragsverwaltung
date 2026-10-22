#!/usr/bin/env bash
# Ersteinrichtung im Codespace: MariaDB, PHP-Erweiterung, Datenbank, Benutzer und Beispieldaten.
# Läuft einmal beim Anlegen des Codespaces (postCreateCommand).
set -euo pipefail
cd "$(dirname "$0")/.."

echo "== MariaDB installieren"
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server > /dev/null

echo "== PHP-Erweiterung pdo_mysql"
if ! php -m | grep -qi '^pdo_mysql$'; then
    sudo docker-php-ext-install pdo_mysql > /dev/null
fi
php -v | head -n 1

echo "== Datenbankserver starten"
sudo service mariadb start

echo "== Zugangsdaten (code/config.local.php)"
if [ ! -f code/config.local.php ]; then
    db_passwort=$(php -r 'echo bin2hex(random_bytes(14));')
    sed -e "s/HIER_DATENBANK_PASSWORT/$db_passwort/" \
        -e "s/HIER_ANMELDE_PASSWORT/1234/" \
        code/config.example.php > code/config.local.php
    echo "neu angelegt"
else
    db_passwort=$(sed -n "s/.*'pass' *=> *'\([^']*\)'.*/\1/p" code/config.local.php | head -n 1)
    echo "vorhanden"
fi

echo "== Datenbank und Benutzer anlegen"
sed "s/HIER_PASSWORT_EINSETZEN/$db_passwort/g" code/sql/setup.example.sql > code/sql/setup.local.sql
# root meldet sich in MariaDB unter Debian über den Systembenutzer an, daher sudo statt Passwort
sudo mysql < code/sql/setup.local.sql

echo "== Tabellen, Benutzer und Beispieldaten"
php code/app/install.php demo passwort

echo "== Einrichtung fertig"

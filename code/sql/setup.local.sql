-- Lokale Einrichtung, enthält das echte Passwort und bleibt außerhalb des Repositorys.
-- Als Administrator ausführen:  mysql -u root -p -e "source code/sql/setup.local.sql"

CREATE DATABASE IF NOT EXISTS antragsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'antrag_app'@'localhost' IDENTIFIED BY 'Mvt6k3CcQkhR2YG58D9LoPyHgkS7';
CREATE USER IF NOT EXISTS 'antrag_app'@'127.0.0.1' IDENTIFIED BY 'Mvt6k3CcQkhR2YG58D9LoPyHgkS7';
ALTER USER 'antrag_app'@'localhost' IDENTIFIED BY 'Mvt6k3CcQkhR2YG58D9LoPyHgkS7';
ALTER USER 'antrag_app'@'127.0.0.1' IDENTIFIED BY 'Mvt6k3CcQkhR2YG58D9LoPyHgkS7';

-- Grundrechte für Anwendung und install.php; bewusst ohne UPDATE/DELETE,
-- damit niemand die Logdatei ändern kann.
GRANT SELECT, INSERT, CREATE, DROP, INDEX, ALTER, REFERENCES, TRIGGER ON antragsverwaltung.* TO 'antrag_app'@'localhost';
GRANT SELECT, INSERT, CREATE, DROP, INDEX, ALTER, REFERENCES, TRIGGER ON antragsverwaltung.* TO 'antrag_app'@'127.0.0.1';

GRANT UPDATE, DELETE ON antragsverwaltung.antrag         TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer       TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer_rolle TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.al_abteilung   TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.nachricht      TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.abteilung      TO 'antrag_app'@'localhost';

GRANT UPDATE, DELETE ON antragsverwaltung.antrag         TO 'antrag_app'@'127.0.0.1';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer       TO 'antrag_app'@'127.0.0.1';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer_rolle TO 'antrag_app'@'127.0.0.1';
GRANT UPDATE, DELETE ON antragsverwaltung.al_abteilung   TO 'antrag_app'@'127.0.0.1';
GRANT UPDATE, DELETE ON antragsverwaltung.nachricht      TO 'antrag_app'@'127.0.0.1';
GRANT UPDATE, DELETE ON antragsverwaltung.abteilung      TO 'antrag_app'@'127.0.0.1';

-- nötig, damit install.php die Schutz-Trigger anlegen darf, solange das Binärlog läuft
SET GLOBAL log_bin_trust_function_creators = 1;

FLUSH PRIVILEGES;

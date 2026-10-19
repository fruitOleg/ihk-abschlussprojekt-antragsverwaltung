-- Einmalige Einrichtung der Datenbank. Als Administrator ausführen (MySQL: root, XAMPP: root).
--
--   mysql -u root -p < setup.example.sql        (vorher PASSWORT ersetzen)
--   oder in phpMyAdmin unter "SQL" einfügen
--
-- Diese Datei ist eine Vorlage ohne echtes Passwort. Die eigene Fassung
-- (sql/setup.local.sql) und das Passwort in config.local.php bleiben lokal.

CREATE DATABASE IF NOT EXISTS antragsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'antrag_app'@'localhost' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';
ALTER USER 'antrag_app'@'localhost' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';

-- Grundrechte für die Anwendung und für install.php.
-- Bewusst ohne UPDATE und DELETE: die Logdatei kann dadurch niemand ändern.
GRANT SELECT, INSERT, CREATE, DROP, INDEX, ALTER, REFERENCES, TRIGGER
  ON antragsverwaltung.* TO 'antrag_app'@'localhost';

-- Ändern und Löschen nur dort, wo es der Ablauf braucht
GRANT UPDATE, DELETE ON antragsverwaltung.antrag         TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer       TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.benutzer_rolle TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.al_abteilung   TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.nachricht      TO 'antrag_app'@'localhost';
GRANT UPDATE, DELETE ON antragsverwaltung.abteilung      TO 'antrag_app'@'localhost';

-- Nötig, damit install.php die Schutz-Trigger für die Logdatei anlegen darf,
-- solange das Binärlog eingeschaltet ist (bei XAMPP meist nicht nötig).
SET GLOBAL log_bin_trust_function_creators = 1;

FLUSH PRIVILEGES;

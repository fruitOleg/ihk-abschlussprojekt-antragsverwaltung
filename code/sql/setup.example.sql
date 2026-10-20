-- Einmalige Einrichtung der Datenbank. Als Administrator ausführen (MySQL: root, XAMPP: root).
--
--   MySQL:  mysql -u root -p < setup.example.sql
--   XAMPP:  C:\xampp\mysql\bin\mysql -u root < setup.example.sql
--   oder in phpMyAdmin unter "SQL" einfügen
--
-- Vorlage ohne echtes Passwort: HIER_PASSWORT_EINSETZEN überall durch dasselbe
-- Passwort ersetzen wie in config.local.php. Die eigene Fassung heißt
-- sql/setup.local.sql und bleibt wie config.local.php außerhalb des Repositorys.
--
-- Danach: code/app/start.bat install (legt Tabellen, Schutz-Trigger und Benutzer an).

CREATE DATABASE IF NOT EXISTS antragsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- PHP verbindet sich über 127.0.0.1; je nach Server zählt das als "localhost" oder "127.0.0.1"
CREATE USER IF NOT EXISTS 'antrag_app'@'localhost' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';
CREATE USER IF NOT EXISTS 'antrag_app'@'127.0.0.1' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';
ALTER USER 'antrag_app'@'localhost' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';
ALTER USER 'antrag_app'@'127.0.0.1' IDENTIFIED BY 'HIER_PASSWORT_EINSETZEN';

-- Rechte nur auf diese eine Datenbank. Die Logdatei schützen die Trigger aus schema.sql:
-- sie weisen jedes UPDATE und DELETE auf log_eintrag ab.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, TRIGGER
  ON antragsverwaltung.* TO 'antrag_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, TRIGGER
  ON antragsverwaltung.* TO 'antrag_app'@'127.0.0.1';

-- Erlaubt install.php das Anlegen der Trigger, wenn das Binärlog eingeschaltet ist
-- (MySQL 8 standardmäßig, XAMPP/MariaDB meist nicht – schadet dort aber nicht).
SET GLOBAL log_bin_trust_function_creators = 1;

FLUSH PRIVILEGES;

-- Tabellen der Antragsverwaltung (MySQL 8)
-- Wird von install.php ausgeführt. Mehrfaches Ausführen ist unschädlich.
--
-- Abbildung des Geschäftsprozesses "Änderung der Unterrichtsorganisation":
--   Entwurf -> eingereicht -> abgezeichnet_al -> abgezeichnet_ssl -> genehmigt -> Sekretariat
--   AL kann zurückweisen (zurück zum Entwurf) oder in eine andere Abteilung umleiten.
--   SL lehnt ab bzw. Lehrkraft zieht zurück -> AL zeichnet ab -> Sekretariat.

CREATE TABLE IF NOT EXISTS abteilung (
  id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kuerzel VARCHAR(10)  NOT NULL UNIQUE,
  name    VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS benutzer (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login         VARCHAR(50)  NOT NULL UNIQUE,
  name          VARCHAR(100) NOT NULL,
  amt           VARCHAR(20)  NOT NULL DEFAULT '',
  passwort_hash VARCHAR(255) NOT NULL,
  aktiv         TINYINT(1)   NOT NULL DEFAULT 1,
  erstellt      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mehrere Rollen je Benutzer: AL, stellv. SL und SL sind zugleich Lehrer
CREATE TABLE IF NOT EXISTS benutzer_rolle (
  benutzer_id INT UNSIGNED NOT NULL,
  rolle       ENUM('lehrer', 'al', 'ssl', 'sl', 'sek', 'admin') NOT NULL,
  PRIMARY KEY (benutzer_id, rolle),
  CONSTRAINT fk_rolle_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jede Abteilung hat 1..n Abteilungsleiter; ein AL kann mehrere Abteilungen betreuen
CREATE TABLE IF NOT EXISTS al_abteilung (
  benutzer_id  INT UNSIGNED     NOT NULL,
  abteilung_id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (benutzer_id, abteilung_id),
  INDEX idx_al_abteilung (abteilung_id),
  CONSTRAINT fk_al_benutzer  FOREIGN KEY (benutzer_id)  REFERENCES benutzer (id)  ON DELETE CASCADE,
  CONSTRAINT fk_al_abteilung FOREIGN KEY (abteilung_id) REFERENCES abteilung (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- daten: die Felder des gewählten Anlasses als JSON, z. B. {"grund": "...", "beginn_datum": "2026-10-01"}
-- k: Feld "K" in Untis, wird vom Abteilungsleiter bei ganztägiger Abwesenheit gesetzt
CREATE TABLE IF NOT EXISTS antrag (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  antragsteller_id INT UNSIGNED     NOT NULL,
  abteilung_id     TINYINT UNSIGNED NULL,
  anlass           VARCHAR(30)      NOT NULL DEFAULT '',
  daten            JSON             NOT NULL,
  anlage_name      VARCHAR(255)     NULL,
  anlage_datei     VARCHAR(64)      NULL,
  status           ENUM('entwurf', 'eingereicht', 'abgezeichnet_al', 'abgezeichnet_ssl',
                        'genehmigt', 'abgelehnt', 'storniert') NOT NULL DEFAULT 'entwurf',
  k                TINYINT(1)       NOT NULL DEFAULT 0,
  al_abgezeichnet  TINYINT(1)       NOT NULL DEFAULT 0,
  sek_erledigt     TINYINT(1)       NOT NULL DEFAULT 0,
  ausgeblendet     TINYINT(1)       NOT NULL DEFAULT 0,
  erstellt         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  eingereicht      DATETIME         NULL,
  INDEX idx_antrag_status (status),
  INDEX idx_antrag_abteilung (abteilung_id, status),
  INDEX idx_antrag_steller (antragsteller_id),
  CONSTRAINT fk_antrag_benutzer  FOREIGN KEY (antragsteller_id) REFERENCES benutzer (id),
  CONSTRAINT fk_antrag_abteilung FOREIGN KEY (abteilung_id)     REFERENCES abteilung (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=231;

-- Logdatei: wird nur angehängt. antrag_id bewusst ohne Fremdschlüssel, damit Einträge
-- auch nach dem Löschen eines Entwurfs erhalten bleiben. benutzer_name ist eine Kopie,
-- damit der Eintrag auch nach einer Namensänderung lesbar bleibt.
CREATE TABLE IF NOT EXISTS log_eintrag (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zeit          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  benutzer_id   INT UNSIGNED NOT NULL,
  benutzer_name VARCHAR(100) NOT NULL,
  aktion        ENUM('eingereicht', 'zurueckgewiesen', 'umgeleitet', 'abgezeichnet_al', 'abgezeichnet_ssl',
                     'genehmigt', 'abgelehnt', 'storniert', 'ablehnung_abgezeichnet', 'stornierung_abgezeichnet',
                     'sek_abgezeichnet', 'ausgeblendet', 'eingeblendet', 'entwurf_geloescht',
                     'benutzer_angelegt', 'benutzer_geaendert', 'export') NOT NULL,
  antrag_id     INT UNSIGNED NULL,
  bemerkung     VARCHAR(500) NOT NULL DEFAULT '',
  INDEX idx_log_antrag (antrag_id),
  INDEX idx_log_zeit (zeit),
  CONSTRAINT fk_log_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benachrichtigung an AL/SSL/SL/Sekretariat/Lehrkraft; im Betrieb zusätzlich per E-Mail bzw. Untis.
-- popup = beim nächsten Seitenaufruf als Pop-up zeigen (z. B. Ablehnung durch den Schulleiter)
CREATE TABLE IF NOT EXISTS nachricht (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  benutzer_id INT UNSIGNED NOT NULL,
  antrag_id   INT UNSIGNED NULL,
  text        VARCHAR(500) NOT NULL,
  zeit        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  gelesen     TINYINT(1)   NOT NULL DEFAULT 0,
  popup       TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX idx_nachricht_empfaenger (benutzer_id, gelesen),
  CONSTRAINT fk_nachricht_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Verändern kann die Logdatei niemand": Änderungen und Löschungen werden in der
-- Datenbank selbst abgewiesen, zusätzlich fehlen dem Anwendungsbenutzer die Rechte dafür.
DROP TRIGGER IF EXISTS log_eintrag_kein_update;
CREATE TRIGGER log_eintrag_kein_update BEFORE UPDATE ON log_eintrag
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Log-Eintraege duerfen nicht geaendert werden.';

DROP TRIGGER IF EXISTS log_eintrag_kein_delete;
CREATE TRIGGER log_eintrag_kein_delete BEFORE DELETE ON log_eintrag
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Log-Eintraege duerfen nicht geloescht werden.';

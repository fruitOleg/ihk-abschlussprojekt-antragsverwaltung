# Antragsverwaltung – Änderung der Unterrichtsorganisation

IHK-Abschlussprojekt: Webanwendung für den Genehmigungsweg von Anträgen zur Unterrichtsorganisation
(Lehrkraft → Abteilungsleitung → stellv. Schulleitung → Schulleitung → Sekretariat).

## Anwendung

**https://curse-mulberry-rule.ngrok-free.dev/**

Die Anwendung läuft auf dem Rechner der Projektgruppe (XAMPP) und ist erreichbar, solange sie dort
online gestellt ist. Beim ersten Aufruf zeigt ngrok eine Hinweisseite – dort auf **Visit Site** klicken.

## Testzugänge

| Benutzer | Rolle |
|---|---|
| `keller`, `brandt` | Lehrer |
| `vogt`, `schulz` | Abteilungsleiter BBWQ |
| `lang` | Abteilungsleiter EMT |
| `becker` | Abteilungsleiter IPT |
| `roth` | stellv. Schulleiter |
| `rahali` | Schulleiter |
| `hoffmann` | Sekretariat |
| `admin` | Administrator |

Das Passwort teilt die Projektgruppe mit.

## Inhalt

| Ordner | Inhalt |
|---|---|
| `code/app/` | die Anwendung (PHP 8.4, MySQL/MariaDB) |
| `code/sql/` | Datenbankschema |
| `code/public/` | klickbarer Prototyp ohne Server (GitHub Pages) |
| `docs/` | Wireframe, Mockups, UML, Use-Case-Diagramm, Zeitplan, Netzplan |

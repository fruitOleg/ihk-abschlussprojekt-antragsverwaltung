# Antragsverwaltung – Änderung der Unterrichtsorganisation

IHK-Abschlussprojekt: Webanwendung für den Genehmigungsweg von Anträgen zur Unterrichtsorganisation
(Lehrkraft → Abteilungsleitung → stellv. Schulleitung → Schulleitung → Sekretariat).

## Anwendung starten

Die Anwendung läuft in GitHub Codespaces: oben **Code** → **Codespaces** → **Create codespace on main**
(beim nächsten Mal denselben Codespace unter https://github.com/codespaces starten).
Die Adresse der Anwendung steht danach im Terminal: `Antragsverwaltung: https://…-8000.app.github.dev/`.

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

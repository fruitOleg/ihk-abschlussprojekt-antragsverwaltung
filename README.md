# Antragsverwaltung – Änderung der Unterrichtsorganisation

IHK-Abschlussprojekt: Webanwendung für den Genehmigungsweg von Anträgen zur Unterrichtsorganisation
(Lehrkraft → Abteilungsleitung → stellv. Schulleitung → Schulleitung → Sekretariat).

## Inhalt

| Ordner / Datei | Inhalt |
|---|---|
| `code/app/` | die Anwendung: PHP 8.1+, MySQL/MariaDB (XAMPP) |
| `code/sql/schema.sql` | Datenbankschema: Tabellen und Schutz-Trigger für die Logdatei |
| `code/public/` | klickbarer Prototyp ohne Server (läuft auf GitHub Pages, Daten im Browser) |
| `docs/` | Wireframe, Mockups, Use-Case-Diagramm, Zeitplan, Installationsanleitung |
| `index.html` | Startseite für GitHub Pages |
| `.vscode/` | Start der Anwendung aus VS Code mit F5 |
| `.devcontainer/` | Start der Anwendung in GitHub Codespaces |

## Starten

Voraussetzung: XAMPP unter `C:\xampp` (PHP 8.1 oder neuer).

- **VS Code:** Ordner öffnen, **F5**. MySQL und Apache werden bei Bedarf gestartet,
  danach öffnet sich http://localhost/antragsverwaltung/.
- **Erster Start auf einem neuen Rechner:** in `code\app` einmal `.\start.bat einrichten`
  (legt Passwörter, Datenbank, Benutzer und Beispieldaten an).
- **Ohne Apache:** in `code\app` `.\start.bat`, dann http://127.0.0.1:8000.

Ausführlich, mit Fehlerbehebung: [docs/Installation_XAMPP.md](docs/Installation_XAMPP.md)

## Online testen (GitHub Codespaces)

Die Anwendung läuft samt Datenbank in der Cloud von GitHub, ohne eigenen Rechner und ohne XAMPP.

1. Auf dieser Seite oben **Code** → Reiter **Codespaces** → **Create codespace on main**.
2. Beim ersten Start richtet sich alles selbst ein (einige Minuten). Am Ende steht im Terminal
   `Antragsverwaltung: https://…-8000.app.github.dev/`.
3. Diese Adresse an die Testpersonen weitergeben. Im Reiter **PORTS** muss Port 8000 auf
   **Public** stehen (wird automatisch versucht; sonst Rechtsklick → Port Visibility → Public).
4. Später: unter https://github.com/codespaces denselben Codespace wieder starten – die Adresse bleibt gleich.
   Nach etwa 30 Minuten ohne Aktivität hält GitHub ihn an; vor dem Test also neu starten.
   Den Codespace nicht löschen, sonst entsteht eine neue Adresse.

## Anmeldung (Beispieldaten)

`keller`, `brandt` (Lehrer) · `vogt`, `schulz` (AL BBWQ) · `lang` (AL EMT) · `becker` (AL IPT) ·
`roth` (stellv. SL) · `rahali` (SL) · `hoffmann` (Sekretariat) · `admin`.
Das Passwort steht in `code\config.local.php` unter `startpasswort`.

## Wichtig

`code/config.local.php` und `code/sql/setup.local.sql` enthalten echte Passwörter.
Sie stehen in `.gitignore` und dürfen nie ins Repository.

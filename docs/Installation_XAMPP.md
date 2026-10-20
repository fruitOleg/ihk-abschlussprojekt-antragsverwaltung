# Installation auf einem Rechner mit XAMPP

Anleitung, um die Antragsverwaltung auf einem neuen Rechner mit XAMPP (Apache, PHP, MySQL/MariaDB) zu starten.
Voraussetzung: XAMPP mit PHP 8.1 oder neuer, Git.

Alle Befehle sind für **PowerShell** (Standard-Terminal unter Windows) geschrieben: jeden Befehl einzeln eingeben und mit Enter bestätigen.

## Kurzfassung

```
git clone https://github.com/fruitOleg/ihk-abschlussprojekt-antragsverwaltung.git
cd ihk-abschlussprojekt-antragsverwaltung\code\app
.\start.bat einrichten
.\start.bat
```

Danach im Browser **http://127.0.0.1:8000** öffnen. Das Anmeldepasswort zeigt `.\start.bat einrichten` am Ende an
(es steht außerdem in `code\config.local.php` unter `startpasswort`).

## Schritt für Schritt

### 1. XAMPP starten

XAMPP Control Panel öffnen und bei **MySQL** auf *Start* klicken. Apache wird nur für phpMyAdmin gebraucht.

### 2. Projekt holen

Neu klonen (siehe oben) oder ein vorhandenes Verzeichnis auf den Stand von GitHub bringen:

```
git fetch origin
git reset --hard origin/main
```

> `reset --hard` ist nötig, weil die Historie auf GitHub am 20.10.2026 bereinigt wurde. Ein normales `git pull` scheitert sonst.

### 3. Einrichten

Im Ordner `code\app`:

```
.\start.bat einrichten
```

Das Skript erledigt alles Folgende selbst:

| Was | Wo es landet | Woher der Inhalt kommt |
|---|---|---|
| Datenbank-Passwort und Anmeldepasswort | `code\config.local.php` | zufällig erzeugt aus der Vorlage `code\config.example.php` |
| Skript für Datenbank und Benutzer | `code\sql\setup.local.sql` | Vorlage `code\sql\setup.example.sql`, Platzhalter durch dasselbe Passwort ersetzt |
| Datenbank `antragsverwaltung`, Benutzer `antrag_app` | MySQL/MariaDB von XAMPP | `setup.local.sql`, ausgeführt als `root` |
| Tabellen, Schutz-Trigger für die Logdatei, 10 Benutzer, 12 Beispielanträge | Datenbank `antragsverwaltung` | `code\sql\schema.sql` und `code\app\install.php` |

Bei XAMPP hat `root` normalerweise kein Passwort, dann läuft alles ohne Rückfrage durch.
Hat `root` ein Passwort, fragt das Skript danach.

Die beiden Dateien `config.local.php` und `setup.local.sql` stehen in `.gitignore` und kommen **nie** ins Repository.

### 4. Starten

```
.\start.bat
```

Adresse: **http://127.0.0.1:8000**. Beenden mit Strg+C.

## Benutzer für die Vorführung

Alle mit dem Anmeldepasswort aus `config.local.php`:

| Benutzer | Rolle |
|---|---|
| `keller`, `brandt` | Lehrer |
| `vogt`, `schulz` | Abteilungsleiter BBWQ (und Lehrer) |
| `lang` | Abteilungsleiter EMT (und Lehrer) |
| `becker` | Abteilungsleiter IPT (und Lehrer) |
| `roth` | stellv. Schulleiter (und Lehrer) |
| `wenzel` | Schulleiter (und Lehrer) |
| `hoffmann` | Sekretariat |
| `admin` | Administrator (Benutzerverwaltung) |

## Von Hand einrichten (falls das Skript nicht durchläuft)

1. `code\config.example.php` kopieren nach `code\config.local.php`.
   Darin `HIER_DATENBANK_PASSWORT` durch ein selbst gewähltes Passwort ersetzen
   und `HIER_ANMELDE_PASSWORT` durch das gewünschte Anmeldepasswort.
2. `code\sql\setup.example.sql` kopieren nach `code\sql\setup.local.sql`.
   Darin `HIER_PASSWORT_EINSETZEN` an allen vier Stellen durch **dasselbe** Datenbank-Passwort ersetzen.
3. http://localhost/phpmyadmin öffnen (dafür in XAMPP auch Apache starten), oben auf **SQL** klicken,
   den Inhalt von `setup.local.sql` einfügen und auf **OK** klicken.
4. Im Ordner `code\app`: `.\start.bat install demo`
5. `.\start.bat`

## Häufige Fehler

| Meldung | Ursache und Lösung |
|---|---|
| *config.local.php fehlt* | Schritt 3 noch nicht ausgeführt: `.\start.bat einrichten` |
| *Keine Verbindung zur Datenbank* | MySQL in XAMPP läuft nicht, oder das Passwort in `config.local.php` passt nicht zu dem in der Datenbank. `setup.local.sql` erneut als root ausführen. |
| *PHP mit der Erweiterung pdo_mysql wurde nicht gefunden* | XAMPP liegt nicht unter `C:\xampp`. Pfad in `code\app\start.bat` (Zeile mit `C:\xampp\php\php.exe`) anpassen. |
| *Anmeldung schlägt fehl* | Anmeldepasswort steht in `config.local.php` unter `startpasswort`. Es gilt nur für Konten, die mit genau dieser Datei angelegt wurden; sonst `.\start.bat install neu demo`. |

> In PowerShell funktioniert die Umleitung `<` nicht. Statt `mysql -u root -p < datei.sql` dort
> `mysql -u root -p -e "source code/sql/setup.local.sql"` verwenden.

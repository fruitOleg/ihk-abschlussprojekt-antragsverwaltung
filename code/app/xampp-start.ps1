# Startet alles, was die Antragsverwaltung unter XAMPP braucht.
# Wird von VS Code vor dem Start aufgerufen (F5), lässt sich aber auch direkt ausführen:
#   powershell -ExecutionPolicy Bypass -File code\app\xampp-start.ps1
#
#   1. MySQL und Apache von XAMPP starten, falls sie nicht laufen
#   2. die Anwendung unter http://localhost/antragsverwaltung bereitstellen
#   3. beim ersten Mal Datenbank, Benutzer und Beispieldaten einrichten

$ErrorActionPreference = 'Stop'

$xampp   = 'C:\xampp'
$php     = Join-Path $xampp 'php\php.exe'
$app     = $PSScriptRoot
$code    = Split-Path $app -Parent
$public  = Join-Path $app 'public'
$link    = Join-Path $xampp 'htdocs\antragsverwaltung'
$adresse = 'http://localhost/antragsverwaltung/'

function Schritt([string] $text) { Write-Host "== $text" -ForegroundColor Cyan }

function Prozess-Auf-Port([int] $port) {
    $verbindung = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $verbindung) { return $null }
    return Get-Process -Id $verbindung.OwningProcess -ErrorAction SilentlyContinue
}

function Warten-Auf-Port([int] $port, [string] $name) {
    for ($i = 0; $i -lt 30; $i++) {
        if (Prozess-Auf-Port $port) { return }
        Start-Sleep -Milliseconds 500
    }
    throw "$name ist nach 15 Sekunden nicht erreichbar. Bitte im XAMPP Control Panel starten und die Logs ansehen."
}

if (-not (Test-Path $php)) {
    throw "XAMPP wurde nicht unter $xampp gefunden. Pfad oben in dieser Datei anpassen."
}

# ---------- 1. MySQL ----------
Schritt 'MySQL'
if (-not (Prozess-Auf-Port 3306)) {
    Start-Process -FilePath (Join-Path $xampp 'mysql_start.bat') -WorkingDirectory $xampp -WindowStyle Hidden
    Warten-Auf-Port 3306 'MySQL'
    Write-Host 'gestartet'
} else {
    Write-Host 'laeuft bereits'
}

# ---------- 2. Apache ----------
Schritt 'Apache'
if (-not (Prozess-Auf-Port 80)) {
    Start-Process -FilePath (Join-Path $xampp 'apache_start.bat') -WorkingDirectory $xampp -WindowStyle Hidden
    Warten-Auf-Port 80 'Apache'
    Write-Host 'gestartet'
} else {
    Write-Host 'laeuft bereits'
}

# ---------- 3. Verknüpfung in htdocs ----------
Schritt 'Verknuepfung htdocs\antragsverwaltung'
if (-not (Test-Path $link)) {
    # Verzeichnisverbindung: htdocs zeigt auf code\app\public, nichts wird kopiert
    cmd /c mklink /J "$link" "$public" | Out-Null
    Write-Host 'angelegt'
} else {
    Write-Host 'vorhanden'
}

# ---------- 4. Datenbank ----------
Schritt 'Datenbank'
Push-Location $app
try {
    if (-not (Test-Path (Join-Path $code 'config.local.php'))) {
        # erster Start auf diesem Rechner: Passwörter, Datenbank, Benutzer, Beispiele
        & $php einrichten.php
        if ($LASTEXITCODE -ne 0) { throw 'Einrichtung fehlgeschlagen (siehe Meldungen oben).' }
        & $php install.php demo passwort
    } else {
        # legt fehlende Tabellen an, vorhandene Daten bleiben unverändert
        & $php install.php | Select-Object -Last 2
    }
    if ($LASTEXITCODE -ne 0) { throw 'Datenbank nicht erreichbar (siehe Meldungen oben).' }
} finally {
    Pop-Location
}

Write-Host ''
Write-Host "Bereit: $adresse" -ForegroundColor Green

# Startet alles, was die Antragsverwaltung braucht.
# Wird von VS Code vor dem Start aufgerufen (F5), lässt sich aber auch direkt ausführen:
#   powershell -ExecutionPolicy Bypass -File code\app\xampp-start.ps1
#
#   1. MySQL von XAMPP starten, falls es nicht läuft
#   2. die Anwendung mit einem aktuellen PHP unter http://127.0.0.1:8000 starten
#      (das PHP von XAMPP ist veraltet und wird nur ersatzweise benutzt)
#   3. beim ersten Mal Datenbank, Benutzer und Beispieldaten einrichten

$ErrorActionPreference = 'Stop'

$xampp   = 'C:\xampp'
$app     = $PSScriptRoot
$code    = Split-Path $app -Parent
$adresse = 'http://127.0.0.1:8000/'

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
    throw "$name ist nach 15 Sekunden nicht erreichbar."
}

# ---------- PHP wählen: aktuelles PHP aus dem Suchpfad, sonst XAMPP ----------
$php = $null
$optionen = @()
foreach ($kandidat in (Get-Command php -All -ErrorAction SilentlyContinue)) {
    $ext = Join-Path (Split-Path $kandidat.Source) 'ext'
    if (Test-Path (Join-Path $ext 'php_pdo_mysql.dll')) {
        $php = $kandidat.Source
        # ohne php.ini: benötigte Erweiterungen nur für diesen Aufruf einschalten
        $optionen = @('-d', "extension_dir=$ext", '-d', 'extension=pdo_mysql', '-d', 'extension=fileinfo', '-d', 'extension=mbstring')
        break
    }
}
if (-not $php) {
    $php = Join-Path $xampp 'php\php.exe'
    if (-not (Test-Path $php)) { throw 'Kein PHP gefunden. Aktuelles PHP installieren: winget install PHP.PHP.8.4' }
    Write-Host 'Hinweis: es wird das veraltete PHP von XAMPP benutzt (besser: winget install PHP.PHP.8.4)' -ForegroundColor Yellow
}
$optionen += @('-d', 'upload_max_filesize=5M', '-d', 'post_max_size=6M', '-d', 'expose_php=0')
# Die Anwendung kann öffentlich erreichbar sein (online.ps1): keine Fehlerdetails an Besucher, nur ins Protokoll
$optionen += @('-d', 'display_errors=0', '-d', 'log_errors=1', '-d', "error_log=$env:TEMP\antragsverwaltung-php.log")
Schritt ('PHP ' + ((& $php @optionen -r 'echo PHP_VERSION;')))

# ---------- 1. MySQL ----------
Schritt 'MySQL'
if (-not (Prozess-Auf-Port 3306)) {
    Start-Process -FilePath (Join-Path $xampp 'mysql_start.bat') -WorkingDirectory $xampp -WindowStyle Hidden
    Warten-Auf-Port 3306 'MySQL'
    Write-Host 'gestartet'
} else {
    Write-Host 'laeuft bereits'
}

# ---------- 2. Datenbank ----------
Schritt 'Datenbank'
Push-Location $app
try {
    if (-not (Test-Path (Join-Path $code 'config.local.php'))) {
        # erster Start auf diesem Rechner: Passwörter, Datenbank, Benutzer, Beispiele
        & $php @optionen einrichten.php
        if ($LASTEXITCODE -ne 0) { throw 'Einrichtung fehlgeschlagen (siehe Meldungen oben).' }
        & $php @optionen install.php demo passwort
    } else {
        # legt fehlende Tabellen an, vorhandene Daten bleiben unverändert
        & $php @optionen install.php | Select-Object -Last 2
    }
    if ($LASTEXITCODE -ne 0) { throw 'Datenbank nicht erreichbar (siehe Meldungen oben).' }
} finally {
    Pop-Location
}

# ---------- 3. Anwendung ----------
Schritt 'Anwendung'
if (-not (Prozess-Auf-Port 8000)) {
    # Werte mit Leerzeichen in Anführungszeichen, weil Start-Process die Liste nur zusammenfügt
    $argumente = ($optionen + @('-S', '127.0.0.1:8000', '-t', 'public')) | ForEach-Object { if ($_ -match '\s') { "`"$_`"" } else { $_ } }
    Start-Process -FilePath $php -ArgumentList $argumente -WorkingDirectory $app -WindowStyle Hidden
    Warten-Auf-Port 8000 'Die Anwendung'
    Write-Host 'gestartet'
} else {
    Write-Host 'laeuft bereits'
}

Write-Host ''
Write-Host "Bereit: $adresse" -ForegroundColor Green

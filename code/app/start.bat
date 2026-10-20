@echo off
rem Antragsverwaltung lokal starten
rem   start.bat                 startet den Server auf http://127.0.0.1:8000
rem   start.bat install         legt Tabellen und Grunddaten an
rem   start.bat install demo    zusaetzlich Beispielantraege
rem   start.bat install neu     Tabellen loeschen und neu aufbauen
rem   start.bat einrichten      Ersteinrichtung auf neuem Rechner (XAMPP): Passwoerter,
rem                             Datenbank, Benutzer, Tabellen und Beispieldaten

setlocal enabledelayedexpansion
cd /d "%~dp0"

rem Zuerst XAMPP, sonst ein PHP aus dem Suchpfad, das pdo_mysql mitbringt
set "PHP="
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP (
  for /f "delims=" %%p in ('where php 2^>nul') do (
    if not defined PHP if exist "%%~dppext\php_pdo_mysql.dll" set "PHP=%%p"
  )
)
if not defined PHP (
  echo PHP mit der Erweiterung pdo_mysql wurde nicht gefunden.
  exit /b 1
)

rem Bei XAMPP stehen die Erweiterungen schon in der php.ini
set "OPTIONEN="
echo %PHP% | find /i "xampp" >nul
if errorlevel 1 (
  for %%p in ("%PHP%") do set "EXT=%%~dppext"
  set OPTIONEN=-d "extension_dir=!EXT!" -d extension=pdo_mysql -d extension=fileinfo -d extension=mbstring
)
set "OPTIONEN=%OPTIONEN% -d upload_max_filesize=5M -d post_max_size=6M"

if /i "%~1"=="einrichten" (
  "%PHP%" %OPTIONEN% einrichten.php || exit /b 1
  "%PHP%" %OPTIONEN% install.php demo
  echo.
  echo Fertig. Starten mit:  start.bat   danach http://127.0.0.1:8000 im Browser
  exit /b
)

if /i "%~1"=="install" (
  "%PHP%" %OPTIONEN% install.php %2 %3
  exit /b
)

echo Antragsverwaltung laeuft auf http://127.0.0.1:8000
echo Beenden mit Strg+C
"%PHP%" %OPTIONEN% -S 127.0.0.1:8000 -t public

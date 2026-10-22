<?php
declare(strict_types=1);

/*
 * Ersteinrichtung auf einem neuen Rechner (gedacht für XAMPP). Aufruf: start.bat einrichten
 *
 *   1. legt code/config.local.php an, falls sie fehlt (zufällige Passwörter)
 *   2. schreibt code/sql/setup.local.sql mit demselben Datenbank-Passwort
 *   3. führt setup.local.sql als MySQL-root aus (legt Datenbank und Benutzer an)
 * Danach ruft start.bat "install.php demo" auf (Tabellen, Trigger, Benutzer, Beispiele).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$code = dirname(__DIR__);
$konfigDatei = "$code/config.local.php";
$vorlageKonfig = "$code/config.example.php";
$setupVorlage = "$code/sql/setup.example.sql";
$setupDatei = "$code/sql/setup.local.sql";

function zufall(int $laenge): string
{
    // ohne verwechselbare Zeichen und ohne Zeichen, die in SQL oder PHP stören
    $zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $wort = '';
    for ($i = 0; $i < $laenge; $i++) {
        $wort .= $zeichen[random_int(0, strlen($zeichen) - 1)];
    }
    return $wort;
}

function schritt(string $text): void
{
    echo PHP_EOL . "== $text" . PHP_EOL;
}

/* ---------- 1. config.local.php ---------- */

schritt('Zugangsdaten (code/config.local.php)');
if (is_file($konfigDatei)) {
    $konfig = require $konfigDatei;
    $dbPasswort = (string) ($konfig['db']['pass'] ?? '');
    if ($dbPasswort === '' || str_contains($dbPasswort, 'HIER_')) {
        fwrite(STDERR, "config.local.php enthält kein gültiges Datenbank-Passwort. Datei löschen und erneut ausführen.\n");
        exit(1);
    }
    echo "vorhanden, wird weiterverwendet\n";
} else {
    $dbPasswort = zufall(28);
    // einfaches Anmeldepasswort für die Vorführung; in config.local.php jederzeit änderbar
    $startPasswort = '1234';
    $inhalt = str_replace(
        ['HIER_DATENBANK_PASSWORT', 'HIER_ANMELDE_PASSWORT'],
        [$dbPasswort, $startPasswort],
        file_get_contents($vorlageKonfig)
    );
    file_put_contents($konfigDatei, $inhalt);
    $konfig = require $konfigDatei;
    echo "neu angelegt mit zufälligen Passwörtern\n";
}

/* ---------- 2. setup.local.sql ---------- */

schritt('Datenbank-Skript (code/sql/setup.local.sql)');
file_put_contents($setupDatei, str_replace('HIER_PASSWORT_EINSETZEN', $dbPasswort, file_get_contents($setupVorlage)));
echo "geschrieben, Passwort passt zu config.local.php\n";

/* ---------- 3. als root ausführen ---------- */

schritt('Datenbank und Benutzer anlegen (als MySQL-root)');
$mysql = null;
foreach (['C:\\xampp\\mysql\\bin\\mysql.exe', 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe'] as $pfad) {
    if (is_file($pfad)) {
        $mysql = $pfad;
        break;
    }
}
if (!$mysql) {
    exec('where mysql 2>NUL', $treffer);
    $mysql = $treffer[0] ?? null;
}

$ok = false;
if ($mysql) {
    // XAMPP: root hat standardmäßig kein Passwort, also zuerst ohne versuchen
    $befehl = '"' . $mysql . '" -u root < "' . $setupDatei . '"';
    passthru($befehl . ' 2>NUL', $rueckgabe);
    if ($rueckgabe === 0) {
        $ok = true;
        echo "erledigt (root ohne Passwort)\n";
    } else {
        echo "root braucht ein Passwort – bitte jetzt eingeben:\n";
        passthru('"' . $mysql . '" -u root -p < "' . $setupDatei . '"', $rueckgabe);
        $ok = $rueckgabe === 0;
        echo $ok ? "erledigt\n" : "";
    }
}

if (!$ok) {
    fwrite(STDERR, "\nDas Skript konnte nicht automatisch als root laufen.\n"
        . "So geht es von Hand:\n"
        . "  1. http://localhost/phpmyadmin öffnen (XAMPP: Apache und MySQL gestartet)\n"
        . "  2. Oben auf \"SQL\" klicken\n"
        . "  3. Den Inhalt dieser Datei einfügen und auf \"OK\" klicken:\n"
        . "     $setupDatei\n"
        . "  4. Danach: start.bat install demo\n");
    exit(1);
}

echo PHP_EOL . 'Anmeldepasswort für alle Konten: ' . ($konfig['app']['startpasswort'] ?? '?') . PHP_EOL;
echo '(steht auch in code/config.local.php unter startpasswort)' . PHP_EOL;

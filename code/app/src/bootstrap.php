<?php
declare(strict_types=1);

/*
 * Wird von jeder Seite als Erstes eingebunden:
 * Konfiguration, Datenbankverbindung, Sitzung und Fehlerbehandlung.
 */

// Ordner code/ – dort liegen config.local.php und sql/
const PROJEKT = __DIR__ . '/../..';

// Hochgeladene Anlagen, nicht im Repository
const ANLAGEN_ORDNER = __DIR__ . '/../uploads';

require __DIR__ . '/hilfen.php';
require __DIR__ . '/daten.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/anzeige.php';

set_exception_handler(function (Throwable $fehler): void {
    error_log((string) $fehler);

    $text = $fehler instanceof PDOException
        ? 'Keine Verbindung zur Datenbank. Läuft MySQL, und stimmen die Angaben in config.local.php?'
        : 'Es ist ein unerwarteter Fehler aufgetreten. Details stehen im Server-Log.';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $text . PHP_EOL . $fehler->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="de"><meta charset="utf-8"><title>Fehler</title>';
    echo '<p style="font-family: sans-serif; padding: 2rem">' . e($text) . '</p></html>';
});

function konfig(): array
{
    static $konfig = null;
    if ($konfig === null) {
        $datei = PROJEKT . '/config.local.php';
        if (!is_file($datei)) {
            throw new RuntimeException('config.local.php fehlt im Ordner code/.');
        }
        $konfig = require $datei;
    }
    return $konfig;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $k = konfig()['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $k['host'], $k['port'], $k['name']);
        $pdo = new PDO($dsn, $k['user'], $k['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

if (PHP_SAPI !== 'cli') {
    session_start([
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Lax',
        'use_strict_mode'  => true,
    ]);
}

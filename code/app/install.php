<?php
declare(strict_types=1);

/*
 * Einrichtung der Datenbank.
 *
 *   start.bat install        Tabellen anlegen und Grunddaten einspielen
 *   start.bat install demo   zusätzlich Beispielanträge anlegen
 *   start.bat install neu    alle Tabellen löschen und neu aufbauen (Daten gehen verloren)
 *   start.bat install passwort  allen Konten das Startpasswort aus config.local.php geben
 *
 * Das Startpasswort steht in config.local.php und gelangt nicht ins Repository.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/src/bootstrap.php';

$befehle = array_slice($argv, 1);
$neu = in_array('neu', $befehle, true);
$demo = in_array('demo', $befehle, true);
$passwortSetzen = in_array('passwort', $befehle, true);

$pdo = db();

const TABELLEN = ['nachricht', 'log_eintrag', 'antrag', 'al_abteilung', 'benutzer_rolle', 'benutzer', 'abteilung'];

const ABTEILUNGEN = [
    'EMT'  => 'Elektro- und Metalltechnik',
    'IPT'  => 'Informations- und Produktionstechnik',
    'BBWQ' => 'Berufliche Bildung, Wirtschaft und Qualifizierung',
];

// login => [Name, Amtsbezeichnung, Rollen, Abteilungen (nur für AL)]
const BENUTZER = [
    'keller'   => ['M. Keller',         'StR',  ['lehrer'],       []],
    'brandt'   => ['P. Brandt',         'OStR', ['lehrer'],       []],
    'vogt'     => ['S. Vogt',           'StD',  ['lehrer', 'al'], ['BBWQ']],
    'schulz'   => ['T. Schulz',         'StD',  ['lehrer', 'al'], ['BBWQ']],
    'lang'     => ['J. Lang',           'StD',  ['lehrer', 'al'], ['EMT']],
    'becker'   => ['R. Becker',         'StD',  ['lehrer', 'al'], ['IPT']],
    'roth'     => ['K. Roth',           'StD',  ['lehrer', 'ssl'], []],
    'rahali'   => ['Mamut Rahali',        'OStD', ['lehrer', 'sl'],  []],
    'hoffmann' => ['A. Hoffmann',       '',     ['sek'],           []],
    'admin'    => ['IT-Administration', '',     ['admin'],         []],
];

/* ---------- Alte Tabellen ---------- */

function tabellen(PDO $pdo): array
{
    return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
}

function spalteFehlt(PDO $pdo, string $tabelle, string $spalte): bool
{
    if (!in_array($tabelle, tabellen($pdo), true)) {
        return false;
    }
    return $pdo->query("SHOW COLUMNS FROM `$tabelle` LIKE " . $pdo->quote($spalte))->fetch() === false;
}

if ($neu) {
    echo "Alte Tabellen entfernen ...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (TABELLEN as $tabelle) {
        $pdo->exec("DROP TABLE IF EXISTS `$tabelle`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} elseif (spalteFehlt($pdo, 'benutzer', 'aktiv')) {
    fwrite(STDERR, "In der Datenbank stehen noch die Tabellen der alten Fassung.\n"
        . "Mit  start.bat install neu  werden sie gelöscht und neu aufgebaut.\n");
    exit(1);
}

/* ---------- Tabellen ---------- */

echo "Tabellen anlegen ...\n";
$sql = file_get_contents(PROJEKT . '/sql/schema.sql');
foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $anweisung) {
    // Kommentarzeilen entfernen, leere Reste überspringen
    $anweisung = trim(preg_replace('/^\s*--.*$/m', '', $anweisung));
    if ($anweisung === '') {
        continue;
    }
    // Die Trigger brauchen zusätzliche Rechte. Fehlen sie, schützen die
    // Rechte des Anwendungsbenutzers die Logdatei (kein UPDATE, kein DELETE).
    if (str_starts_with($anweisung, 'CREATE TRIGGER') || str_starts_with($anweisung, 'DROP TRIGGER')) {
        try {
            $pdo->exec($anweisung);
        } catch (PDOException $fehler) {
            echo "  Hinweis: Trigger zum Schutz der Logdatei nicht angelegt ({$fehler->getCode()}).\n"
                . "  Siehe sql/setup.example.sql – der Schutz hängt dann allein an den Rechten.\n";
        }
        continue;
    }
    $pdo->exec($anweisung);
}

/* ---------- Grunddaten ---------- */

$abteilungIds = [];
$abteilungEinfuegen = $pdo->prepare('INSERT IGNORE INTO abteilung (kuerzel, name) VALUES (?, ?)');
foreach (ABTEILUNGEN as $kuerzel => $name) {
    $abteilungEinfuegen->execute([$kuerzel, $name]);
}
foreach ($pdo->query('SELECT id, kuerzel FROM abteilung') as $zeile) {
    $abteilungIds[$zeile['kuerzel']] = (int) $zeile['id'];
}
echo 'Abteilungen: ' . implode(', ', array_keys($abteilungIds)) . "\n";

$vorhanden = (int) $pdo->query('SELECT COUNT(*) FROM benutzer')->fetchColumn();
if ($vorhanden > 0) {
    if ($passwortSetzen) {
        $startpasswort = (string) (konfig()['app']['startpasswort'] ?? '');
        if ($startpasswort === '') {
            fwrite(STDERR, "In config.local.php fehlt 'startpasswort' unter 'app'.\n");
            exit(1);
        }
        $pdo->prepare('UPDATE benutzer SET passwort_hash = ?')->execute([password_hash($startpasswort, PASSWORD_DEFAULT)]);
        echo "Alle $vorhanden Konten haben jetzt das Startpasswort aus config.local.php.\n";
    } else {
        echo "Benutzer existieren bereits ($vorhanden), nichts geändert.\n";
    }
} else {
    // Passwort steht nur in config.local.php, damit es nicht ins Repository gelangt
    $startpasswort = (string) (konfig()['app']['startpasswort'] ?? '');
    if ($startpasswort === '') {
        fwrite(STDERR, "In config.local.php fehlt 'startpasswort' unter 'app'.\n");
        exit(1);
    }

    echo "Benutzer anlegen ...\n";
    $benutzerEinfuegen = $pdo->prepare('INSERT INTO benutzer (login, name, amt, passwort_hash) VALUES (?, ?, ?, ?)');
    $rolleEinfuegen = $pdo->prepare('INSERT INTO benutzer_rolle (benutzer_id, rolle) VALUES (?, ?)');
    $alEinfuegen = $pdo->prepare('INSERT INTO al_abteilung (benutzer_id, abteilung_id) VALUES (?, ?)');

    foreach (BENUTZER as $login => [$name, $amt, $rollen, $abteilungen]) {
        $benutzerEinfuegen->execute([$login, $name, $amt, password_hash($startpasswort, PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();
        foreach ($rollen as $rolle) {
            $rolleEinfuegen->execute([$id, $rolle]);
        }
        foreach ($abteilungen as $kuerzel) {
            $alEinfuegen->execute([$id, $abteilungIds[$kuerzel]]);
        }
        $zusatz = $abteilungen ? ' (' . implode(', ', $abteilungen) . ')' : '';
        printf("  %-9s %s%s\n", $login, implode(', ', $rollen), $zusatz);
    }
    echo "Passwort für alle: siehe 'startpasswort' in config.local.php\n";
}

/* ---------- Beispielanträge ---------- */

if ($demo) {
    $anzahl = (int) $pdo->query('SELECT COUNT(*) FROM antrag')->fetchColumn();
    if ($anzahl > 0) {
        echo "Es gibt bereits $anzahl Anträge, keine Beispieldaten angelegt.\n";
    } else {
        require __DIR__ . '/src/beispieldaten.php';
        echo 'Beispielanträge anlegen ... ' . beispieldatenAnlegen($pdo) . " Anträge\n";
    }
}

echo "\nStand: "
    . $pdo->query('SELECT COUNT(*) FROM benutzer')->fetchColumn() . ' Benutzer, '
    . $pdo->query('SELECT COUNT(*) FROM antrag')->fetchColumn() . ' Anträge, '
    . $pdo->query('SELECT COUNT(*) FROM log_eintrag')->fetchColumn() . " Log-Einträge.\n";
echo "Fertig.\n";

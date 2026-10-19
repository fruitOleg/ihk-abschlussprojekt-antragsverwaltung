<?php
declare(strict_types=1);

/*
 * Darstellung eines Antrags, die mehrere Seiten brauchen:
 * Angaben aus dem Formular, Bearbeitungsstand, Verlauf (Log) und Antragslisten.
 */

function feldWert(array $feld, mixed $roh): string
{
    if ($feld['typ'] === 'checkbox') {
        return $roh ? 'ja' : 'nein';
    }
    if ($roh === null || trim((string) $roh) === '') {
        return '–';
    }
    if ($feld['typ'] === 'date') {
        return datum((string) $roh);
    }
    if ($feld['typ'] === 'time') {
        return e((string) $roh) . ' Uhr';
    }
    return nl2br(e((string) $roh));
}

function zeitraumText(array $daten): string
{
    $teil = fn(?string $tag, ?string $zeit) => $tag ? datum($tag) . ($zeit ? ', ' . e($zeit) . ' Uhr' : '') : '–';
    return $teil($daten['beginn_datum'] ?? null, $daten['beginn_zeit'] ?? null)
        . ' bis ' . $teil($daten['ende_datum'] ?? null, $daten['ende_zeit'] ?? null);
}

function angabenTabelle(array $zeilen): string
{
    $html = '<div class="tabelle-rahmen"><table class="tabelle tabelle--angaben"><tbody>';
    foreach ($zeilen as [$titel, $inhalt]) {
        $html .= '<tr><th scope="row">' . $titel . '</th><td>' . $inhalt . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function taetigkeitenTabelle(array $liste): string
{
    if (!$liste) {
        return '<p class="leise">Keine Einträge.</p>';
    }
    $html = '<div class="tabelle-rahmen"><table class="tabelle"><thead><tr>'
        . '<th scope="col">Datum</th><th scope="col">U-Std</th><th scope="col">Anderweitige dienstliche Tätigkeit</th>'
        . '</tr></thead><tbody>';
    foreach ($liste as $zeile) {
        $html .= '<tr><td class="zahl">' . datum($zeile['datum'] ?? '') . '</td>'
            . '<td>' . e($zeile['stunden'] ?? '') . '</td>'
            . '<td>' . e($zeile['taetigkeit'] ?? '') . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

// Kopfdaten und je Formularabschnitt eine Tabelle
function angabenAusgeben(array $antrag): void
{
    $anlass = ANLAESSE[$antrag['anlass']] ?? null;
    $kopf = [
        ['Antragsteller', e($antrag['antragsteller_name']) . ($antrag['antragsteller_amt'] ? ', ' . e($antrag['antragsteller_amt']) : '')],
        ['Abteilung', e($antrag['abteilung'] ?? '–')],
        ['Anlass', $anlass ? e($anlass['titel']) : '–'],
        ['Eingereicht', $antrag['eingereicht'] ? zeitpunkt($antrag['eingereicht']) : 'noch nicht'],
        ['Status', statusMarke($antrag)],
    ];
    if (!in_array($antrag['status'], ['entwurf', 'eingereicht'], true)) {
        $kopf[] = ['Feld „K“ (Untis)', $antrag['k'] ? '<b>gesetzt</b> – ganztägige Abwesenheit' : 'nicht gesetzt'];
    }
    $kopf[] = ['Anlage', $antrag['anlage_datei']
        ? '<a href="anlage.php?id=' . $antrag['id'] . '">' . e($antrag['anlage_name']) . '</a>'
        : '–'];

    echo '<section class="karte"><h2>Antrag</h2>' . angabenTabelle($kopf) . '</section>';
    if (!$anlass) {
        return;
    }

    $daten = $antrag['daten'];
    foreach ($anlass['abschnitte'] as $schluessel) {
        $abschnitt = ABSCHNITTE[$schluessel];
        if ($schluessel === 'taetigkeit') {
            $inhalt = taetigkeitenTabelle($daten['taetigkeiten'] ?? []);
        } else {
            $zeilen = [];
            foreach ($abschnitt['felder'] as $feld) {
                if ($feld['name'] === 'beginn_datum') {
                    $zeilen[] = ['Zeitraum', zeitraumText($daten)];
                } elseif (!str_starts_with($feld['name'], 'beginn_') && !str_starts_with($feld['name'], 'ende_')) {
                    $zeilen[] = [e($feld['label']), feldWert($feld, $daten[$feld['name']] ?? null)];
                }
            }
            $inhalt = angabenTabelle($zeilen);
        }
        echo '<section class="karte"><h2>' . e($abschnitt['titel']) . '</h2>' . $inhalt . '</section>';
    }
}

// Bearbeitungsstand mit Hinweisen auf Zurückweisung, Ablehnung oder Stornierung
function standAusgeben(array $antrag): void
{
    echo '<ol class="stand stand--fuenf">';
    foreach (stufen($antrag) as $stufe) {
        $klasse = $stufe['zustand'] === 'offen' ? '' : ' class="' . $stufe['zustand'] . '"';
        if ($stufe['zeit']) {
            $zusatz = datum($stufe['zeit']) . ' · ' . e($stufe['name']);
        } else {
            $zusatz = $stufe['zustand'] === 'entfaellt' ? 'entfällt' : 'offen';
        }
        echo "<li$klasse>" . e($stufe['text']) . "<small>$zusatz</small></li>";
    }
    echo '</ol>';

    if ($hinweis = zurueckweisung($antrag)) {
        echo '<p class="begruendung begruendung--warn"><b>Zur Korrektur zurückgewiesen von ' . e($hinweis['benutzer_name'])
            . ' am ' . datum($hinweis['zeit']) . ':</b> ' . e($hinweis['bemerkung']) . '</p>';
    }
    if ($hinweis = ablehnung($antrag)) {
        echo '<p class="begruendung"><b>Abgelehnt von ' . e($hinweis['benutzer_name'])
            . ' am ' . datum($hinweis['zeit']) . ':</b> ' . e($hinweis['bemerkung']) . '</p>';
    }
    if ($hinweis = stornierung($antrag)) {
        echo '<p class="begruendung"><b>Zurückgezogen am ' . datum($hinweis['zeit']) . '</b> (' . e($hinweis['bemerkung']) . ').<br>'
            . 'Der Antrag bleibt archiviert und kann weiterhin eingesehen werden.</p>';
    }
    if (in_array($antrag['status'], ['abgelehnt', 'storniert'], true)) {
        echo '<p class="leise">' . ($antrag['al_abgezeichnet']
            ? 'Der Abteilungsleiter hat den Vorgang abgezeichnet.'
            : 'Der Abteilungsleiter wurde informiert und muss den Vorgang noch abzeichnen.') . '</p>';
    }
}

// Log-Einträge zu einem Antrag
function verlaufAusgeben(int $antragId): void
{
    $log = logZu($antragId);
    if (!$log) {
        echo '<p class="leise">Noch keine Einträge.</p>';
        return;
    }
    echo '<div class="tabelle-rahmen"><table class="tabelle"><thead><tr>'
        . '<th scope="col">Zeitpunkt</th><th scope="col">Person</th><th scope="col">Aktion</th><th scope="col">Bemerkung</th>'
        . '</tr></thead><tbody>';
    foreach ($log as $eintrag) {
        echo '<tr><td class="zahl">' . zeitpunkt($eintrag['zeit']) . '</td>'
            . '<td>' . e($eintrag['benutzer_name']) . '</td>'
            . '<td>' . e(AKTIONEN[$eintrag['aktion']] ?? $eintrag['aktion']) . '</td>'
            . '<td>' . e($eintrag['bemerkung']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

// Antragsliste für Eingang und Übersicht
function listeAusgeben(array $antraege, array $benutzer, bool $mitAufgabe = false, string $knopf = 'Öffnen'): void
{
    echo '<div class="tabelle-rahmen"><table class="tabelle"><thead><tr>'
        . '<th scope="col">Nr.</th><th scope="col">Anlass</th><th scope="col">Antragsteller</th>'
        . '<th scope="col">Abt.</th><th scope="col">Datum</th>'
        . '<th scope="col">' . ($mitAufgabe ? 'Aufgabe' : 'Status') . '</th>'
        . '<th scope="col"><span class="unsichtbar">Aktion</span></th></tr></thead><tbody>';

    foreach ($antraege as $antrag) {
        $aufgabe = $mitAufgabe ? aufgabe($benutzer, $antrag) : null;
        $vertretung = $aufgabe ? vertretungsHinweis($benutzer, $aufgabe) : '';
        echo '<tr' . ($antrag['ausgeblendet'] ? ' class="ausgeblendet"' : '') . '>'
            . '<td class="zahl">' . antragNr($antrag['id']) . '</td>'
            . '<td>' . e(kurzbeschreibung($antrag)) . '</td>'
            . '<td>' . e($antrag['antragsteller_name']) . '</td>'
            . '<td>' . e($antrag['abteilung'] ?? '–') . '</td>'
            . '<td class="zahl">' . datum($antrag['eingereicht'] ?? $antrag['erstellt']) . '</td>'
            . '<td>' . ($aufgabe
                ? '<span class="aufgabe">' . e(AUFGABEN[$aufgabe]) . '</span>'
                  . ($vertretung ? '<small class="leise block">' . e($vertretung) . '</small>' : '')
                : statusMarke($antrag))
            . ($antrag['k'] ? ' <span class="marke-k" title="Feld K gesetzt: ganztägige Abwesenheit">K</span>' : '')
            . '</td>'
            . '<td class="rechts"><a class="knopf ' . ($mitAufgabe ? '' : 'knopf--zweit ') . 'knopf--klein" '
            . 'href="antrag.php?id=' . $antrag['id'] . '">' . e($knopf) . '</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

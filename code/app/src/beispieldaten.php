<?php
declare(strict_types=1);

/*
 * Beispielanträge für Vorführung und Test (start.bat install demo).
 * Deckt alle Zustände ab: Entwurf, eingereicht, zurückgewiesen, umgeleitet,
 * abgezeichnet, genehmigt, abgelehnt, zurückgezogen, vom Sekretariat erledigt.
 */

function beispieldatenAnlegen(PDO $pdo): int
{
    $benutzer = [];
    foreach ($pdo->query('SELECT id, login, name FROM benutzer') as $zeile) {
        $benutzer[$zeile['login']] = ['id' => (int) $zeile['id'], 'name' => $zeile['name']];
    }
    $abteilungen = [];
    foreach ($pdo->query('SELECT id, kuerzel FROM abteilung') as $zeile) {
        $abteilungen[$zeile['kuerzel']] = (int) $zeile['id'];
    }

    $antragEinfuegen = $pdo->prepare(
        'INSERT INTO antrag (antragsteller_id, abteilung_id, anlass, daten, status, k, al_abgezeichnet,
                             sek_erledigt, ausgeblendet, erstellt, eingereicht)
         VALUES (:steller, :abteilung, :anlass, :daten, :status, :k, :al, :sek, :aus, :erstellt, :eingereicht)'
    );
    $logEinfuegen = $pdo->prepare(
        'INSERT INTO log_eintrag (zeit, benutzer_id, benutzer_name, aktion, antrag_id, bemerkung)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $nachrichtEinfuegen = $pdo->prepare(
        'INSERT INTO nachricht (benutzer_id, antrag_id, text, zeit, gelesen, popup) VALUES (?, ?, ?, ?, ?, ?)'
    );

    // Hilfsfunktionen für Zeitpunkte und Formulardaten
    $vorTagen = fn(int $tage, string $uhrzeit = '08:00') => date('Y-m-d ' . $uhrzeit, strtotime("-$tage days"));
    $inTagen = fn(int $tage) => date('Y-m-d', strtotime("+$tage days"));
    $lehrkraft = fn(int $tage, string $grund) => [
        'grund' => $grund,
        'beginn_datum' => $inTagen($tage), 'beginn_zeit' => '07:45',
        'ende_datum' => $inTagen($tage), 'ende_zeit' => '15:15',
        'material_plattform' => true, 'material_mail' => false,
    ];

    $faelle = [
        // abgeschlossen und vom Sekretariat ausgeblendet
        ['brandt', 'IPT', 'dienstlich', $lehrkraft(-20, 'Prüfungsausschuss IHK'), 'genehmigt',
            ['k' => 0, 'sek' => 1, 'aus' => 1], 30,
            [[30, 'brandt', 'eingereicht', 'Abteilung IPT'], [29, 'becker', 'abgezeichnet_al', ''],
             [28, 'roth', 'abgezeichnet_ssl', ''], [27, 'wenzel', 'genehmigt', ''],
             [26, 'hoffmann', 'sek_abgezeichnet', ''], [25, 'hoffmann', 'ausgeblendet', '']]],

        // abgeschlossen, mit Feld K
        ['brandt', 'IPT', 'dienstbefreiung', $lehrkraft(3, 'Hochzeit der Tochter'), 'genehmigt',
            ['k' => 1, 'sek' => 1, 'aus' => 0], 12,
            [[12, 'brandt', 'eingereicht', 'Abteilung IPT'], [11, 'becker', 'abgezeichnet_al', 'Feld „K“ gesetzt (ganztägige Abwesenheit)'],
             [10, 'roth', 'abgezeichnet_ssl', ''], [9, 'wenzel', 'genehmigt', ''], [8, 'hoffmann', 'sek_abgezeichnet', 'Feld „K“ berücksichtigt']]],

        // genehmigt, liegt beim Sekretariat
        ['keller', 'BBWQ', 'fortbildung', $lehrkraft(9, 'Fortbildung „Digitale Medien im Unterricht“, LPM Saarbrücken'), 'genehmigt',
            ['k' => 1, 'sek' => 0, 'aus' => 0], 9,
            [[9, 'keller', 'eingereicht', 'Abteilung BBWQ'], [8, 'vogt', 'abgezeichnet_al', 'Feld „K“ gesetzt (ganztägige Abwesenheit)'],
             [7, 'roth', 'abgezeichnet_ssl', ''], [6, 'wenzel', 'genehmigt', '']]],

        // vom Schulleiter abgelehnt, AL muss abzeichnen
        ['brandt', 'BBWQ', 'verlegung', $lehrkraft(6, 'Arzttermin, nicht verschiebbar'), 'abgelehnt',
            ['k' => 0, 'sek' => 0, 'aus' => 0], 7,
            [[7, 'brandt', 'eingereicht', 'Abteilung BBWQ'], [6, 'schulz', 'abgezeichnet_al', ''], [5, 'roth', 'abgezeichnet_ssl', ''],
             [4, 'wenzel', 'abgelehnt', 'Am Termin finden Abschlussprüfungen statt, bitte Termin verlegen.']]],

        // vom Lehrer zurückgezogen, AL muss abzeichnen
        ['keller', 'EMT', 'dienstlich', $lehrkraft(14, 'Messebesuch mit Ausbildungsbetrieben'), 'storniert',
            ['k' => 0, 'sek' => 0, 'aus' => 0], 6,
            [[6, 'keller', 'eingereicht', 'Abteilung EMT'], [5, 'lang', 'abgezeichnet_al', ''],
             [3, 'keller', 'storniert', 'vorher: abgezeichnet vom AL; Messe wurde abgesagt.']]],

        // liegt beim Schulleiter
        ['keller', 'BBWQ', 'klasse', [
            'klasse' => 'BBW 11a', 'grund' => 'Abschlussprüfung',
            'beginn_datum' => $inTagen(4), 'beginn_zeit' => '07:45', 'ende_datum' => $inTagen(4), 'ende_zeit' => '12:00',
            'info_freigesetzt' => true,
            'taetigkeiten' => [['datum' => $inTagen(4), 'stunden' => '1.–4.', 'taetigkeit' => 'Prüfungsaufsicht Raum 1A405']],
        ], 'abgezeichnet_ssl', ['k' => 0, 'sek' => 0, 'aus' => 0], 4,
            [[4, 'keller', 'eingereicht', 'Abteilung BBWQ'], [3, 'vogt', 'abgezeichnet_al', ''], [2, 'roth', 'abgezeichnet_ssl', '']]],

        // zuerst an BBWQ gestellt, vom AL nach EMT umgeleitet, liegt beim stellv. Schulleiter
        ['brandt', 'EMT', 'veranstaltung', [
            'art' => 'Schulfahrt', 'ziel' => 'Berlin, Bundestag', 'klassen' => 'EMT 12b', 'lehrkraefte' => 'L. Sommer',
            'beginn_datum' => $inTagen(20), 'beginn_zeit' => '07:00', 'ende_datum' => $inTagen(23), 'ende_zeit' => '18:00',
            'verkehrsmittel' => 'Bahn', 'info_freigesetzt' => true, 'taetigkeiten' => [],
        ], 'abgezeichnet_al', ['k' => 1, 'sek' => 0, 'aus' => 0], 3,
            [[3, 'brandt', 'eingereicht', 'Abteilung BBWQ'], [2, 'vogt', 'umgeleitet', 'BBWQ → EMT'],
             [1, 'lang', 'abgezeichnet_al', 'Feld „K“ gesetzt (ganztägige Abwesenheit)']]],

        // Antrag eines Abteilungsleiters: bearbeitet der zweite AL der Abteilung
        ['vogt', 'BBWQ', 'fortbildung', $lehrkraft(12, 'Tagung der Abteilungsleitungen'), 'eingereicht',
            ['k' => 0, 'sek' => 0, 'aus' => 0], 2, [[2, 'vogt', 'eingereicht', 'Abteilung BBWQ']]],

        // vom AL zur Korrektur zurückgewiesen: wieder Entwurf
        ['brandt', 'BBWQ', 'dienstlich', $lehrkraft(5, 'Sitzung Prüfungsausschuss'), 'entwurf',
            ['k' => 0, 'sek' => 0, 'aus' => 0], 2,
            [[2, 'brandt', 'eingereicht', 'Abteilung BBWQ'],
             [1, 'schulz', 'zurueckgewiesen', 'Bitte die Uhrzeit korrigieren, die Sitzung beginnt erst um 13 Uhr.']]],

        // frisch eingereicht, liegt beim AL
        ['keller', 'BBWQ', 'fortbildung', $lehrkraft(15, 'Fortbildung Prüfungsvorbereitung'), 'eingereicht',
            ['k' => 0, 'sek' => 0, 'aus' => 0], 1, [[1, 'keller', 'eingereicht', 'Abteilung BBWQ']]],

        // Entwurf, noch nie abgesendet
        ['keller', 'BBWQ', 'veranstaltung', [
            'art' => 'Unterrichtsgang', 'ziel' => 'Landtag Saarbrücken', 'klassen' => 'BBW 10a', 'lehrkraefte' => '',
            'beginn_datum' => $inTagen(10), 'beginn_zeit' => '08:00', 'ende_datum' => $inTagen(10), 'ende_zeit' => '13:00',
            'verkehrsmittel' => 'Bus', 'info_freigesetzt' => false, 'taetigkeiten' => [],
        ], 'entwurf', ['k' => 0, 'sek' => 0, 'aus' => 0], 1, []],

        // Antrag des Schulleiters: der stellv. Schulleiter genehmigt in Vertretung
        ['wenzel', 'BBWQ', 'dienstlich', $lehrkraft(8, 'Schulleitertagung Ministerium'), 'abgezeichnet_al',
            ['k' => 1, 'sek' => 0, 'aus' => 0], 3,
            [[3, 'wenzel', 'eingereicht', 'Abteilung BBWQ'], [2, 'vogt', 'abgezeichnet_al', 'Feld „K“ gesetzt (ganztägige Abwesenheit)']]],
    ];

    $pdo->beginTransaction();
    $anzahl = 0;
    foreach ($faelle as [$login, $kuerzel, $anlass, $daten, $status, $flags, $alter, $schritte]) {
        $erstellt = $vorTagen($alter + 1, '07:30');
        $eingereicht = null;
        foreach ($schritte as [$tage, , $aktion]) {
            if ($aktion === 'eingereicht') {
                $eingereicht = $vorTagen($tage);
            }
        }

        $antragEinfuegen->execute([
            ':steller' => $benutzer[$login]['id'],
            ':abteilung' => $abteilungen[$kuerzel],
            ':anlass' => $anlass,
            ':daten' => json_encode($daten, JSON_UNESCAPED_UNICODE),
            ':status' => $status,
            ':k' => $flags['k'],
            ':al' => $flags['al'] ?? 0,
            ':sek' => $flags['sek'],
            ':aus' => $flags['aus'],
            ':erstellt' => $erstellt,
            ':eingereicht' => $eingereicht,
        ]);
        $antragId = (int) $pdo->lastInsertId();
        $anzahl++;

        foreach ($schritte as $i => [$tage, $wer, $aktion, $bemerkung]) {
            $logEinfuegen->execute([
                $vorTagen($tage, sprintf('%02d:%02d', 8 + $i, 15 * ($i % 4))),
                $benutzer[$wer]['id'], $benutzer[$wer]['name'], $aktion, $antragId, $bemerkung,
            ]);
        }

        // offene Aufgaben erzeugen ungelesene Benachrichtigungen samt Pop-up
        $letzte = end($schritte);
        if ($letzte && in_array($letzte[2], ['abgelehnt', 'storniert'], true)) {
            $empfaenger = $pdo->prepare(
                'SELECT b.id FROM benutzer b
                   JOIN al_abteilung a ON a.benutzer_id = b.id
                  WHERE a.abteilung_id = ? AND b.id <> ? AND b.aktiv = 1'
            );
            $empfaenger->execute([$abteilungen[$kuerzel], $benutzer[$login]['id']]);
            $text = $letzte[2] === 'abgelehnt'
                ? "Antrag A-$antragId wurde vom Schulleiter abgelehnt. Bitte die Ablehnung abzeichnen."
                : "Antrag A-$antragId wurde zurückgezogen. Bitte die Stornierung abzeichnen.";
            foreach ($empfaenger->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $nachrichtEinfuegen->execute([(int) $id, $antragId, $text, $vorTagen($letzte[0]), 0, 1]);
            }
        }
    }
    $pdo->commit();

    return $anzahl;
}

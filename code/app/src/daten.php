<?php
declare(strict_types=1);

/*
 * Datenzugriff und Ablauflogik: Benutzer, Rollen, Anträge, Logdatei, Benachrichtigungen.
 *
 * Ablauf laut UML-Zustandsdiagramm:
 *   Entwurf -> eingereicht -> abgezeichnet_al -> abgezeichnet_ssl -> genehmigt -> Sekretariat
 *   AL kann zur Korrektur zurückweisen oder in eine andere Abteilung umleiten.
 *   SL lehnt ab bzw. die Lehrkraft zieht zurück -> AL zeichnet ab -> Sekretariat.
 *   Jeder Vorgang endet beim Sekretariat, das ihn danach aus- und einblenden kann.
 */

// Fehler, die dem Benutzer angezeigt werden dürfen (keine technischen Details)
class EingabeFehler extends RuntimeException
{
}

const ROLLEN = [
    'lehrer' => 'Lehrer',
    'al'     => 'Abteilungsleiter',
    'ssl'    => 'stellv. Schulleiter',
    'sl'     => 'Schulleiter',
    'sek'    => 'Sekretariat',
    'admin'  => 'Administrator',
];

const STATUS_TEXT = [
    'entwurf'          => 'Entwurf',
    'eingereicht'      => 'eingereicht',
    'abgezeichnet_al'  => 'abgezeichnet vom AL',
    'abgezeichnet_ssl' => 'abgezeichnet vom stellv. SL',
    'genehmigt'        => 'genehmigt',
    'abgelehnt'        => 'abgelehnt',
    'storniert'        => 'zurückgezogen',
];

const STATUS_GRUPPEN = [
    'offen'     => 'In Bearbeitung',
    'genehmigt' => 'Genehmigt',
    'abgelehnt' => 'Abgelehnt',
    'storniert' => 'Zurückgezogen',
];

const AUFGABEN = [
    'al_pruefen'     => 'Prüfen und abzeichnen',
    'ssl_abzeichnen' => 'Abzeichnen (Kenntnis nehmen)',
    'sl_entscheiden' => 'Genehmigen oder ablehnen',
    'al_bestaetigen' => 'Ablehnung/Stornierung abzeichnen',
    'sek_abzeichnen' => 'Abschließend abzeichnen',
];

const AKTIONEN = [
    'eingereicht'              => 'Eingereicht',
    'zurueckgewiesen'          => 'Zur Korrektur zurückgewiesen',
    'umgeleitet'               => 'An andere Abteilung umgeleitet',
    'abgezeichnet_al'          => 'Abgezeichnet (AL)',
    'abgezeichnet_ssl'         => 'Abgezeichnet (stellv. SL)',
    'genehmigt'                => 'Genehmigt',
    'abgelehnt'                => 'Abgelehnt',
    'storniert'                => 'Zurückgezogen',
    'ablehnung_abgezeichnet'   => 'Ablehnung vom AL abgezeichnet',
    'stornierung_abgezeichnet' => 'Stornierung vom AL abgezeichnet',
    'sek_abgezeichnet'         => 'Vom Sekretariat abgezeichnet',
    'ausgeblendet'             => 'Ausgeblendet',
    'eingeblendet'             => 'Eingeblendet',
    'entwurf_geloescht'        => 'Entwurf gelöscht',
    'benutzer_angelegt'        => 'Benutzer angelegt',
    'benutzer_geaendert'       => 'Benutzer geändert',
    'export'                   => 'Datensicherung exportiert',
];

/* ---------- Formular "Änderung der Unterrichtsorganisation" ---------- */

const ZEITRAUM_FELDER = [
    ['name' => 'beginn_datum', 'label' => 'Beginn – Datum',   'typ' => 'date'],
    ['name' => 'beginn_zeit',  'label' => 'Beginn – Uhrzeit', 'typ' => 'time'],
    ['name' => 'ende_datum',   'label' => 'Ende – Datum',     'typ' => 'date'],
    ['name' => 'ende_zeit',    'label' => 'Ende – Uhrzeit',   'typ' => 'time'],
];

const VERANSTALTUNGSARTEN = [
    'Schulveranstaltung an außerschulischem Lernort (Schulfahrtenerlass)' =>
        ['Unterrichtsgang', 'Schulfahrt', 'Internationale Veranstaltung', 'Fahrt aus besonderem Anlass'],
    'Sonstige Veranstaltung' =>
        ['Projekt', 'Internationale Veranstaltung (sonstige)', 'Sonstiges (z. B. Prüfung)'],
];

// Abschnitte des Papierformulars; "Vor-/Nachholen/Tausch" entfällt laut Besprechung vom 17.09.26
const ABSCHNITTE = [
    'lehrkraft' => [
        'titel'  => 'Abwesenheit der Lehrkraft',
        'felder' => [
            ['name' => 'grund', 'label' => 'Grund der Verhinderung', 'typ' => 'textarea', 'breit' => true,
             'hilfe' => 'Bitte ggf. Anordnung des Dienstgeschäftes bzw. Einladungsschreiben als Anlage beifügen.'],
            ...ZEITRAUM_FELDER,
            ['name' => 'material_plattform', 'label' => 'Vertretungsmaterial auf Lernplattform', 'typ' => 'checkbox'],
            ['name' => 'material_mail', 'label' => 'Vertretungsmaterial nach Absprache per Mail', 'typ' => 'checkbox'],
        ],
    ],
    'klasse' => [
        'titel'  => 'Abwesenheit der Klasse',
        'felder' => [
            ['name' => 'klasse', 'label' => 'Klasse/Klassenteil', 'typ' => 'text'],
            ['name' => 'grund', 'label' => 'Grund (z. B. Prüfung, ÜLU)', 'typ' => 'text'],
            ...ZEITRAUM_FELDER,
            ['name' => 'info_freigesetzt', 'label' => 'Info an freigesetzte Lehrkräfte (per E-Mail oder persönlich durch Klassenlehrer/in)',
             'typ' => 'checkbox', 'breit' => true],
        ],
    ],
    'veranstaltung' => [
        'titel'  => 'Abwesenheit von Klasse(n) und Lehrkraft',
        'felder' => [
            ['name' => 'art', 'label' => 'Art der Veranstaltung', 'typ' => 'select', 'gruppen' => VERANSTALTUNGSARTEN],
            ['name' => 'ziel', 'label' => 'Ziel bzw. Thema/Ort', 'typ' => 'text'],
            ['name' => 'klassen', 'label' => 'Teilnehmende Klasse(n)/Klassenteil(e)', 'typ' => 'text'],
            ['name' => 'lehrkraefte', 'label' => 'Zusätzlich teilnehmende Lehrkräfte', 'typ' => 'text', 'optional' => true],
            ...ZEITRAUM_FELDER,
            ['name' => 'verkehrsmittel', 'label' => 'Verkehrsmittel', 'typ' => 'text', 'optional' => true],
            ['name' => 'info_freigesetzt', 'label' => 'Info an freigesetzte Lehrkräfte (per E-Mail oder persönlich durch antragstellende Lehrkraft)',
             'typ' => 'checkbox', 'breit' => true],
        ],
    ],
    'taetigkeit' => [
        'titel'   => 'Anderweitige dienstliche Tätigkeit bei Abwesenheit der SuS',
        'hinweis' => 'Die anderweitige dienstliche Tätigkeit ist mit der Abteilungsleitung zu besprechen. Vertretungen haben grundsätzlich Vorrang.',
        'felder'  => [
            ['name' => 'taetigkeiten', 'label' => 'Tätigkeiten', 'typ' => 'taetigkeiten', 'optional' => true, 'breit' => true],
        ],
    ],
];

// Punkt 1 des Formulars; "Unterrichtstausch" entfällt laut Kundentermin vom 17.09.26
const ANLAESSE = [
    'dienstlich'      => ['titel' => 'Unterrichtsvertretung aus dienstlichen Gründen', 'kurz' => 'Dienstliche Gründe', 'abschnitte' => ['lehrkraft']],
    'dienstbefreiung' => ['titel' => 'Dienstbefreiung nach § 14 Abs. 1 Urlaubsverordnung', 'kurz' => 'Dienstbefreiung § 14', 'abschnitte' => ['lehrkraft']],
    'fortbildung'     => ['titel' => 'Dienstbefreiung für Fortbildungsveranstaltung', 'kurz' => 'Fortbildung', 'abschnitte' => ['lehrkraft']],
    'verlegung'       => ['titel' => 'Unterrichtsverlegung aus privaten Gründen', 'kurz' => 'Verlegung (privat)', 'abschnitte' => ['lehrkraft']],
    'veranstaltung'   => ['titel' => 'Unterrichtsvertretung wegen Veranstaltung', 'kurz' => 'Veranstaltung', 'abschnitte' => ['veranstaltung', 'taetigkeit']],
    'klasse'          => ['titel' => 'Klasse abwesend ohne Lehrkraft', 'kurz' => 'Klasse abwesend', 'abschnitte' => ['klasse', 'taetigkeit']],
];

const ANLAGE_MAX_BYTES = 5 * 1024 * 1024;
const ANLAGE_TYPEN = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

function felderVon(string $anlass): array
{
    $felder = [];
    foreach (ANLAESSE[$anlass]['abschnitte'] as $abschnitt) {
        array_push($felder, ...ABSCHNITTE[$abschnitt]['felder']);
    }
    return $felder;
}

/* ---------- Abteilungen ---------- */

function abteilungen(): array
{
    static $abteilungen = null;
    if ($abteilungen === null) {
        $abteilungen = [];
        foreach (db()->query('SELECT id, kuerzel, name FROM abteilung ORDER BY kuerzel') as $zeile) {
            $abteilungen[$zeile['kuerzel']] = ['id' => (int) $zeile['id'], 'kuerzel' => $zeile['kuerzel'], 'name' => $zeile['name']];
        }
    }
    return $abteilungen;
}

function abteilungId(string $kuerzel): ?int
{
    return abteilungen()[$kuerzel]['id'] ?? null;
}

/* ---------- Benutzer und Rollen ---------- */

const BENUTZER_SQL = '
    SELECT b.id, b.login, b.name, b.amt, b.aktiv, b.passwort_hash,
           GROUP_CONCAT(DISTINCT r.rolle) AS rollen,
           GROUP_CONCAT(DISTINCT ab.kuerzel ORDER BY ab.kuerzel) AS abteilungen
      FROM benutzer b
      LEFT JOIN benutzer_rolle r  ON r.benutzer_id  = b.id
      LEFT JOIN al_abteilung  al  ON al.benutzer_id = b.id
      LEFT JOIN abteilung     ab  ON ab.id          = al.abteilung_id
';

function benutzerAufbereiten(?array $zeile, bool $mitHash = false): ?array
{
    if (!$zeile) {
        return null;
    }
    $benutzer = [
        'id'          => (int) $zeile['id'],
        'login'       => $zeile['login'],
        'name'        => $zeile['name'],
        'amt'         => $zeile['amt'],
        'aktiv'       => (bool) $zeile['aktiv'],
        'rollen'      => $zeile['rollen'] ? explode(',', $zeile['rollen']) : [],
        'abteilungen' => $zeile['abteilungen'] ? explode(',', $zeile['abteilungen']) : [],
    ];
    if ($mitHash) {
        $benutzer['passwort_hash'] = $zeile['passwort_hash'];
    }
    return $benutzer;
}

function benutzerNachId(int $id): ?array
{
    $abfrage = db()->prepare(BENUTZER_SQL . ' WHERE b.id = ? GROUP BY b.id');
    $abfrage->execute([$id]);
    return benutzerAufbereiten($abfrage->fetch() ?: null);
}

function benutzerNachLogin(string $login): ?array
{
    $abfrage = db()->prepare(BENUTZER_SQL . ' WHERE b.login = ? GROUP BY b.id');
    $abfrage->execute([mb_strtolower(trim($login))]);
    return benutzerAufbereiten($abfrage->fetch() ?: null);
}

function alleBenutzer(): array
{
    $liste = [];
    foreach (db()->query(BENUTZER_SQL . ' GROUP BY b.id ORDER BY b.login') as $zeile) {
        $liste[] = benutzerAufbereiten($zeile);
    }
    return $liste;
}

// Anmeldung; gibt null zurück, wenn Login, Passwort oder Konto nicht stimmen
function anmelden(string $login, string $passwort): ?array
{
    $abfrage = db()->prepare(BENUTZER_SQL . ' WHERE b.login = ? GROUP BY b.id');
    $abfrage->execute([mb_strtolower(trim($login))]);
    $benutzer = benutzerAufbereiten($abfrage->fetch() ?: null, true);

    if (!$benutzer || !$benutzer['aktiv'] || !password_verify($passwort, $benutzer['passwort_hash'])) {
        return null;
    }
    unset($benutzer['passwort_hash']);
    return $benutzer;
}

function hatRolle(?array $benutzer, string $rolle): bool
{
    return $benutzer !== null && in_array($rolle, $benutzer['rollen'], true);
}

function hatEineRolle(?array $benutzer, array $rollen): bool
{
    foreach ($rollen as $rolle) {
        if (hatRolle($benutzer, $rolle)) {
            return true;
        }
    }
    return false;
}

function istAlVon(array $benutzer, ?string $abteilung): bool
{
    return $abteilung !== null && hatRolle($benutzer, 'al') && in_array($abteilung, $benutzer['abteilungen'], true);
}

// stellv. Schulleiter und Schulleiter vertreten sich gegenseitig
function istSchulleitung(array $benutzer): bool
{
    return hatRolle($benutzer, 'ssl') || hatRolle($benutzer, 'sl');
}

function rollenText(array $benutzer): string
{
    $teile = [];
    foreach ($benutzer['rollen'] as $rolle) {
        $teile[] = $rolle === 'al' && $benutzer['abteilungen']
            ? ROLLEN['al'] . ' (' . implode(', ', $benutzer['abteilungen']) . ')'
            : ROLLEN[$rolle];
    }
    return implode(', ', $teile);
}

function empfaengerAl(string $abteilung, array $ausnahmen = []): array
{
    $abfrage = db()->prepare(BENUTZER_SQL . '
        WHERE b.aktiv = 1 AND b.id IN (SELECT benutzer_id FROM al_abteilung WHERE abteilung_id = ?)
        GROUP BY b.id');
    $abfrage->execute([abteilungId($abteilung)]);
    return empfaengerFiltern($abfrage->fetchAll(), $ausnahmen);
}

function empfaengerRolle(string $rolle, array $ausnahmen = []): array
{
    $abfrage = db()->prepare(BENUTZER_SQL . '
        WHERE b.aktiv = 1 AND b.id IN (SELECT benutzer_id FROM benutzer_rolle WHERE rolle = ?)
        GROUP BY b.id');
    $abfrage->execute([$rolle]);
    return empfaengerFiltern($abfrage->fetchAll(), $ausnahmen);
}

function empfaengerFiltern(array $zeilen, array $ausnahmen): array
{
    $ids = [];
    foreach ($zeilen as $zeile) {
        $id = (int) $zeile['id'];
        if (!in_array($id, $ausnahmen, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/* ---------- Anträge laden ---------- */

const ANTRAG_SQL = '
    SELECT a.*, b.name AS antragsteller_name, b.amt AS antragsteller_amt, ab.kuerzel AS abteilung
      FROM antrag a
      JOIN benutzer b  ON b.id  = a.antragsteller_id
      LEFT JOIN abteilung ab ON ab.id = a.abteilung_id
';

function antragAufbereiten(array $zeile): array
{
    $zeile['id'] = (int) $zeile['id'];
    $zeile['antragsteller_id'] = (int) $zeile['antragsteller_id'];
    $zeile['daten'] = json_decode((string) $zeile['daten'], true) ?: [];
    foreach (['k', 'al_abgezeichnet', 'sek_erledigt', 'ausgeblendet'] as $feld) {
        $zeile[$feld] = (bool) $zeile[$feld];
    }
    return $zeile;
}

function antragNachId(int $id): ?array
{
    $abfrage = db()->prepare(ANTRAG_SQL . ' WHERE a.id = ?');
    $abfrage->execute([$id]);
    $zeile = $abfrage->fetch();
    return $zeile ? antragAufbereiten($zeile) : null;
}

function antraegeLaden(string $bedingung, array $werte, string $sortierung = 'a.id DESC'): array
{
    $abfrage = db()->prepare(ANTRAG_SQL . " WHERE $bedingung ORDER BY $sortierung");
    $abfrage->execute($werte);
    return array_map('antragAufbereiten', $abfrage->fetchAll());
}

function antraegeVon(array $benutzer): array
{
    // Entwürfe zuerst, danach die neuesten
    return antraegeLaden('a.antragsteller_id = ?', [$benutzer['id']],
        "a.status = 'entwurf' DESC, COALESCE(a.eingereicht, a.erstellt) DESC");
}

// alles, was der Benutzer außer seinen eigenen Anträgen sehen darf
function sichtbarFuer(array $benutzer): array
{
    if (hatRolle($benutzer, 'sek') || istSchulleitung($benutzer)) {
        return antraegeLaden("a.status <> 'entwurf' AND a.antragsteller_id <> ?", [$benutzer['id']]);
    }
    if (hatRolle($benutzer, 'al') && $benutzer['abteilungen']) {
        $platz = implode(',', array_fill(0, count($benutzer['abteilungen']), '?'));
        return antraegeLaden(
            "a.status <> 'entwurf' AND a.antragsteller_id <> ? AND ab.kuerzel IN ($platz)",
            [$benutzer['id'], ...$benutzer['abteilungen']]
        );
    }
    return [];
}

function aufgabenFuer(array $benutzer): array
{
    $offen = [];
    foreach (sichtbarFuer($benutzer) as $antrag) {
        if (aufgabe($benutzer, $antrag)) {
            $offen[] = $antrag;
        }
    }
    // älteste zuerst, damit nichts liegen bleibt
    usort($offen, fn($a, $b) => ($a['eingereicht'] ?? $a['erstellt']) <=> ($b['eingereicht'] ?? $b['erstellt']));
    return $offen;
}

/* ---------- Zuständigkeit und Rechte ---------- */

function beimSekretariat(array $antrag): bool
{
    if ($antrag['sek_erledigt']) {
        return false;
    }
    return $antrag['status'] === 'genehmigt'
        || (in_array($antrag['status'], ['abgelehnt', 'storniert'], true) && $antrag['al_abgezeichnet']);
}

// liefert die Aufgabe des Benutzers für diesen Antrag oder null
function aufgabe(array $benutzer, array $antrag): ?string
{
    // den eigenen Antrag bearbeitet niemand selbst
    if ($antrag['antragsteller_id'] === $benutzer['id']) {
        return null;
    }
    return match ($antrag['status']) {
        'eingereicht'      => istAlVon($benutzer, $antrag['abteilung']) ? 'al_pruefen' : null,
        'abgezeichnet_al'  => istSchulleitung($benutzer) ? 'ssl_abzeichnen' : null,
        'abgezeichnet_ssl' => istSchulleitung($benutzer) ? 'sl_entscheiden' : null,
        'genehmigt'        => beimSekretariat($antrag) && hatRolle($benutzer, 'sek') ? 'sek_abzeichnen' : null,
        'abgelehnt', 'storniert' => !$antrag['al_abgezeichnet']
            ? (istAlVon($benutzer, $antrag['abteilung']) ? 'al_bestaetigen' : null)
            : (beimSekretariat($antrag) && hatRolle($benutzer, 'sek') ? 'sek_abzeichnen' : null),
        default => null,
    };
}

function vertretungsHinweis(array $benutzer, string $aufgabe): string
{
    if ($aufgabe === 'ssl_abzeichnen' && !hatRolle($benutzer, 'ssl')) {
        return 'in Vertretung des stellv. Schulleiters';
    }
    if ($aufgabe === 'sl_entscheiden' && !hatRolle($benutzer, 'sl')) {
        return 'in Vertretung des Schulleiters';
    }
    return '';
}

function darfAnsehen(array $benutzer, array $antrag): bool
{
    if ($antrag['antragsteller_id'] === $benutzer['id']) {
        return true;
    }
    if ($antrag['status'] === 'entwurf') {
        return false;
    }
    return hatRolle($benutzer, 'sek') || istSchulleitung($benutzer) || istAlVon($benutzer, $antrag['abteilung']);
}

// auch ein zur Korrektur zurückgewiesener Antrag kann zurückgezogen werden
function darfZurueckziehen(array $benutzer, array $antrag): bool
{
    if ($antrag['antragsteller_id'] !== $benutzer['id']) {
        return false;
    }
    if ($antrag['status'] === 'entwurf') {
        return $antrag['eingereicht'] !== null;
    }
    return in_array($antrag['status'], ['eingereicht', 'abgezeichnet_al', 'abgezeichnet_ssl', 'genehmigt'], true);
}

// Entwürfe, die nie eingereicht wurden, dürfen wirklich gelöscht werden
function darfEntwurfLoeschen(array $benutzer, array $antrag): bool
{
    return $antrag['antragsteller_id'] === $benutzer['id']
        && $antrag['status'] === 'entwurf'
        && $antrag['eingereicht'] === null;
}

function darfBearbeitenEntwurf(array $benutzer, array $antrag): bool
{
    return $antrag['antragsteller_id'] === $benutzer['id'] && $antrag['status'] === 'entwurf';
}

/* ---------- Anzeige ---------- */

function statusText(array $antrag): string
{
    if ($antrag['status'] === 'entwurf') {
        return zurueckweisung($antrag) ? 'zur Korrektur zurückgewiesen' : 'Entwurf';
    }
    $text = STATUS_TEXT[$antrag['status']];
    if ($antrag['sek_erledigt']) {
        return $text . ', abgeschlossen';
    }
    if ($antrag['status'] === 'genehmigt') {
        return $text . ', beim Sekretariat';
    }
    if (in_array($antrag['status'], ['abgelehnt', 'storniert'], true)) {
        return $text . ($antrag['al_abgezeichnet'] ? ', beim Sekretariat' : ', beim AL');
    }
    return $text;
}

function statusGruppe(array $antrag): string
{
    if (in_array($antrag['status'], ['genehmigt', 'abgelehnt', 'storniert'], true)) {
        return $antrag['status'];
    }
    return $antrag['status'] === 'entwurf' ? 'entwurf' : 'offen';
}

function statusFarbe(array $antrag): string
{
    if ($antrag['status'] === 'entwurf') {
        return zurueckweisung($antrag) ? 'abgezeichnet' : 'entwurf';
    }
    if ($antrag['status'] === 'eingereicht') {
        return 'eingereicht';
    }
    return str_starts_with($antrag['status'], 'abgezeichnet') ? 'abgezeichnet' : $antrag['status'];
}

function kurzbeschreibung(array $antrag): string
{
    $text = ANLAESSE[$antrag['anlass']]['kurz'] ?? 'ohne Anlass';
    $daten = $antrag['daten'];
    if (!empty($daten['beginn_datum'])) {
        $von = datum($daten['beginn_datum']);
        $bis = $daten['ende_datum'] ?? '';
        $text .= $bis && $bis !== $daten['beginn_datum'] ? ", $von bis " . datum($bis) : ", am $von";
    }
    return $text;
}

// letzter Log-Eintrag einer Aktion
function letzterLog(array $antrag, string $aktion): ?array
{
    $treffer = null;
    foreach (logZu($antrag['id']) as $eintrag) {
        if ($eintrag['aktion'] === $aktion) {
            $treffer = $eintrag;
        }
    }
    return $treffer;
}

function zurueckweisung(array $antrag): ?array
{
    return $antrag['status'] === 'entwurf' ? letzterLog($antrag, 'zurueckgewiesen') : null;
}

function ablehnung(array $antrag): ?array
{
    return $antrag['status'] === 'abgelehnt' ? letzterLog($antrag, 'abgelehnt') : null;
}

function stornierung(array $antrag): ?array
{
    return $antrag['status'] === 'storniert' ? letzterLog($antrag, 'storniert') : null;
}

// Bearbeitungsstand: je eine Station von "Eingereicht" bis "Sekretariat"
function stufen(array $antrag): array
{
    $log = logZu($antrag['id']);

    // nur der letzte Durchlauf seit dem letzten Einreichen zählt
    $start = 0;
    foreach ($log as $i => $eintrag) {
        if ($eintrag['aktion'] === 'eingereicht') {
            $start = $i;
        }
    }
    $lauf = array_slice($log, $start);
    $finde = function (array $aktionen) use ($lauf): ?array {
        foreach ($lauf as $eintrag) {
            if (in_array($eintrag['aktion'], $aktionen, true)) {
                return $eintrag;
            }
        }
        return null;
    };

    $eingereicht = $finde(['eingereicht']);
    $sl = $finde(['genehmigt', 'abgelehnt']);
    $sek = $finde(['sek_abgezeichnet']);
    $beendet = in_array($antrag['status'], ['abgelehnt', 'storniert'], true);

    $stufe = function (string $text, ?array $eintrag, ?string $zustand = null) use ($beendet): array {
        return [
            'text'    => $text,
            'zeit'    => $eintrag['zeit'] ?? null,
            'name'    => $eintrag['benutzer_name'] ?? null,
            'zustand' => $zustand ?? ($eintrag ? 'erledigt' : ($beendet ? 'entfaellt' : 'offen')),
        ];
    };

    return [
        $stufe('Eingereicht', $eingereicht, $eingereicht ? 'erledigt' : 'offen'),
        $stufe('Abgezeichnet vom AL', $finde(['abgezeichnet_al'])),
        $stufe('Abgezeichnet vom stellv. SL', $finde(['abgezeichnet_ssl'])),
        $sl && $sl['aktion'] === 'abgelehnt' ? $stufe('Abgelehnt vom SL', $sl, 'abgelehnt') : $stufe('Genehmigt vom SL', $sl),
        $stufe('Abgezeichnet vom Sekretariat', $sek, $sek ? 'erledigt' : 'offen'),
    ];
}

/* ---------- Prüfung der Formulardaten ---------- */

// liefert [feldname => Meldung]; leer, wenn alles passt
function antragPruefen(array $eingabe): array
{
    $fehler = [];
    if (!isset(abteilungen()[$eingabe['abteilung'] ?? ''])) {
        $fehler['abteilung'] = 'Bitte wählen Sie eine Abteilung.';
    }
    if (!isset(ANLAESSE[$eingabe['anlass'] ?? ''])) {
        $fehler['anlass'] = 'Bitte wählen Sie einen Anlass.';
        return $fehler;
    }

    $daten = $eingabe['daten'] ?? [];
    foreach (felderVon($eingabe['anlass']) as $feld) {
        if (!empty($feld['optional']) || $feld['typ'] === 'checkbox') {
            continue;
        }
        if (trim((string) ($daten[$feld['name']] ?? '')) === '') {
            $fehler[$feld['name']] = 'Bitte ausfüllen.';
            continue;
        }
        if ($feld['typ'] === 'date' && !gueltigesDatum((string) $daten[$feld['name']])) {
            $fehler[$feld['name']] = 'Bitte ein gültiges Datum angeben.';
        }
        if ($feld['typ'] === 'time' && !preg_match('/^\d{2}:\d{2}$/', (string) $daten[$feld['name']])) {
            $fehler[$feld['name']] = 'Bitte eine Uhrzeit im Format hh:mm angeben.';
        }
    }

    if (!isset($fehler['beginn_datum'], $fehler['ende_datum']) && !empty($daten['beginn_datum']) && !empty($daten['ende_datum'])) {
        $beginn = $daten['beginn_datum'] . 'T' . ($daten['beginn_zeit'] ?: '00:00');
        $ende = $daten['ende_datum'] . 'T' . ($daten['ende_zeit'] ?: '23:59');
        if ($ende < $beginn) {
            $fehler['ende_datum'] = 'Das Ende liegt vor dem Beginn.';
        }
    }

    foreach ($daten['taetigkeiten'] ?? [] as $i => $zeile) {
        if (!$zeile['datum'] || !$zeile['stunden'] || !$zeile['taetigkeit']) {
            $fehler['taetigkeiten'] = 'Zeile ' . ($i + 1) . ': bitte Datum, U-Std und Tätigkeit angeben oder die Zeile entfernen.';
        }
    }
    return $fehler;
}

/* ---------- Logdatei und Benachrichtigungen ---------- */

function logEintrag(array $benutzer, string $aktion, ?int $antragId = null, string $bemerkung = ''): void
{
    db()->prepare('INSERT INTO log_eintrag (benutzer_id, benutzer_name, aktion, antrag_id, bemerkung) VALUES (?, ?, ?, ?, ?)')
        ->execute([$benutzer['id'], $benutzer['name'], $aktion, $antragId, mb_substr($bemerkung, 0, 500)]);
}

function logZu(int $antragId): array
{
    $abfrage = db()->prepare('SELECT * FROM log_eintrag WHERE antrag_id = ? ORDER BY id');
    $abfrage->execute([$antragId]);
    return $abfrage->fetchAll();
}

function logAlle(array $benutzer): array
{
    if (!hatRolle($benutzer, 'sl')) {
        throw new EingabeFehler('Nur der Schulleiter darf die Logdatei ausgeben.');
    }
    return db()->query('SELECT * FROM log_eintrag ORDER BY id')->fetchAll();
}

// simuliert die Benachrichtigung per E-Mail bzw. Untis
function benachrichtigen(array $empfaenger, string $text, ?int $antragId, bool $popup = false): void
{
    if (!$empfaenger) {
        return;
    }
    $einfuegen = db()->prepare('INSERT INTO nachricht (benutzer_id, antrag_id, text, popup) VALUES (?, ?, ?, ?)');
    foreach ($empfaenger as $id) {
        $einfuegen->execute([$id, $antragId, mb_substr($text, 0, 500), $popup ? 1 : 0]);
    }
}

function nachrichtenFuer(array $benutzer): array
{
    $abfrage = db()->prepare('SELECT * FROM nachricht WHERE benutzer_id = ? ORDER BY id DESC');
    $abfrage->execute([$benutzer['id']]);
    return $abfrage->fetchAll();
}

function ungeleseneAnzahl(array $benutzer): int
{
    $abfrage = db()->prepare('SELECT COUNT(*) FROM nachricht WHERE benutzer_id = ? AND gelesen = 0');
    $abfrage->execute([$benutzer['id']]);
    return (int) $abfrage->fetchColumn();
}

// Pop-up-Nachrichten werden einmal angezeigt und dann als gelesen markiert
function popupsAbholen(array $benutzer): array
{
    $abfrage = db()->prepare('SELECT * FROM nachricht WHERE benutzer_id = ? AND popup = 1 AND gelesen = 0 ORDER BY id');
    $abfrage->execute([$benutzer['id']]);
    $offen = $abfrage->fetchAll();
    if ($offen) {
        db()->prepare('UPDATE nachricht SET gelesen = 1 WHERE benutzer_id = ? AND popup = 1 AND gelesen = 0')
            ->execute([$benutzer['id']]);
    }
    return $offen;
}

function nachrichtenGelesen(array $benutzer): void
{
    db()->prepare('UPDATE nachricht SET gelesen = 1 WHERE benutzer_id = ?')->execute([$benutzer['id']]);
}

/* ---------- Aktionen: Hilfsfunktionen ---------- */

// führt eine Änderung in einer Transaktion aus; der Antrag wird dabei gesperrt
function mitAntrag(int $antragId, callable $arbeit): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $abfrage = $pdo->prepare('SELECT id FROM antrag WHERE id = ? FOR UPDATE');
        $abfrage->execute([$antragId]);
        if (!$abfrage->fetch()) {
            throw new EingabeFehler('Diesen Antrag gibt es nicht mehr.');
        }
        $ergebnis = $arbeit(antragNachId($antragId));
        $pdo->commit();
        return $ergebnis;
    } catch (Throwable $fehler) {
        $pdo->rollBack();
        throw $fehler;
    }
}

function pflichtGrund(?string $grund): string
{
    $text = trim((string) $grund);
    if ($text === '') {
        throw new EingabeFehler('Bitte geben Sie eine Begründung an.');
    }
    return mb_substr($text, 0, 500);
}

function aufgabePruefen(array $benutzer, array $antrag, string $erwartet): void
{
    if (aufgabe($benutzer, $antrag) !== $erwartet) {
        throw new EingabeFehler('Dieser Antrag liegt nicht zur Bearbeitung bei Ihnen.');
    }
}

function antragSetzen(int $antragId, array $werte): void
{
    $spalten = implode(', ', array_map(fn($s) => "$s = ?", array_keys($werte)));
    db()->prepare("UPDATE antrag SET $spalten WHERE id = ?")->execute([...array_values($werte), $antragId]);
}

/* ---------- Aktionen der Lehrkraft ---------- */

// speichert einen neuen oder vorhandenen Entwurf; $einreichen schickt ihn gleich ab
function antragSpeichern(array $benutzer, array $eingabe, bool $einreichen): array
{
    if (!hatRolle($benutzer, 'lehrer')) {
        throw new EingabeFehler('Nur Lehrkräfte können Anträge stellen.');
    }
    if ($einreichen && antragPruefen($eingabe)) {
        throw new EingabeFehler('Der Antrag ist nicht vollständig.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $id = isset($eingabe['id']) ? (int) $eingabe['id'] : 0;
        if ($id) {
            $antrag = antragNachId($id);
            if (!$antrag || !darfBearbeitenEntwurf($benutzer, $antrag)) {
                throw new EingabeFehler('Dieser Entwurf kann nicht mehr bearbeitet werden.');
            }
        } else {
            $pdo->prepare('INSERT INTO antrag (antragsteller_id, daten) VALUES (?, ?)')->execute([$benutzer['id'], '{}']);
            $id = (int) $pdo->lastInsertId();
        }

        $felder = [
            'abteilung_id' => abteilungId((string) ($eingabe['abteilung'] ?? '')),
            'anlass'       => isset(ANLAESSE[$eingabe['anlass'] ?? '']) ? $eingabe['anlass'] : '',
            'daten'        => json_encode($eingabe['daten'] ?? [], JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($eingabe['anlage'])) {
            $alt = antragNachId($id);
            if ($alt && $alt['anlage_datei']) {
                anlageEntfernen($alt['anlage_datei']);
            }
            $felder['anlage_name'] = $eingabe['anlage']['name'];
            $felder['anlage_datei'] = $eingabe['anlage']['datei'];
        }
        antragSetzen($id, $felder);

        if ($einreichen) {
            $antrag = antragNachId($id);
            antragSetzen($id, ['status' => 'eingereicht', 'eingereicht' => date('Y-m-d H:i:s')]);
            logEintrag($benutzer, 'eingereicht', $id, 'Abteilung ' . $antrag['abteilung']);
            benachrichtigen(
                empfaengerAl($antrag['abteilung'], [$benutzer['id']]),
                'Neuer Antrag ' . antragNr($id) . ' (' . kurzbeschreibung($antrag) . ") von {$benutzer['name']}.",
                $id
            );
        }
        $pdo->commit();
    } catch (Throwable $fehler) {
        $pdo->rollBack();
        throw $fehler;
    }
    return antragNachId($id);
}

function entwurfLoeschen(array $benutzer, int $antragId): void
{
    $datei = mitAntrag($antragId, function (array $antrag) use ($benutzer): ?string {
        if (!darfEntwurfLoeschen($benutzer, $antrag)) {
            throw new EingabeFehler('Dieser Entwurf kann nicht gelöscht werden.');
        }
        db()->prepare('DELETE FROM antrag WHERE id = ?')->execute([$antrag['id']]);
        // der Log-Eintrag bleibt erhalten, auch wenn der Antrag verschwindet
        logEintrag($benutzer, 'entwurf_geloescht', $antrag['id']);
        return $antrag['anlage_datei'];
    });
    if ($datei) {
        anlageEntfernen($datei);
    }
}

function antragZurueckziehen(array $benutzer, int $antragId, string $grund = ''): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $grund): void {
        if (!darfZurueckziehen($benutzer, $antrag)) {
            throw new EingabeFehler('Dieser Antrag kann nicht zurückgezogen werden.');
        }
        $vorher = STATUS_TEXT[$antrag['status']];
        antragSetzen($antrag['id'], ['status' => 'storniert', 'al_abgezeichnet' => 0]);
        $grund = trim($grund);
        logEintrag($benutzer, 'storniert', $antrag['id'], $grund ? "vorher: $vorher; $grund" : "vorher: $vorher");
        // laut Besprechung der Schulleitung werden nur die zuständigen AL informiert
        benachrichtigen(
            empfaengerAl($antrag['abteilung'], [$benutzer['id']]),
            'Antrag ' . antragNr($antrag['id']) . " wurde von {$benutzer['name']} zurückgezogen. Bitte die Stornierung abzeichnen.",
            $antrag['id'],
            true
        );
    });
}

/* ---------- Aktionen der Bearbeiter ---------- */

function alAbzeichnen(array $benutzer, int $antragId, bool $k): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $k): void {
        aufgabePruefen($benutzer, $antrag, 'al_pruefen');
        antragSetzen($antrag['id'], ['status' => 'abgezeichnet_al', 'k' => $k ? 1 : 0]);
        logEintrag($benutzer, 'abgezeichnet_al', $antrag['id'], $k ? 'Feld „K“ gesetzt (ganztägige Abwesenheit)' : '');
        benachrichtigen(
            empfaengerRolle('ssl', [$antrag['antragsteller_id']]),
            'Antrag ' . antragNr($antrag['id']) . ' wurde vom AL abgezeichnet und liegt zur Kenntnisnahme vor.',
            $antrag['id']
        );
    });
}

function alZurueckweisen(array $benutzer, int $antragId, string $grund): void
{
    $grund = pflichtGrund($grund);
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $grund): void {
        aufgabePruefen($benutzer, $antrag, 'al_pruefen');
        antragSetzen($antrag['id'], ['status' => 'entwurf']);
        logEintrag($benutzer, 'zurueckgewiesen', $antrag['id'], $grund);
        benachrichtigen([$antrag['antragsteller_id']],
            'Ihr Antrag ' . antragNr($antrag['id']) . " wurde zur Korrektur zurückgewiesen: $grund", $antrag['id'], true);
    });
}

function alUmleiten(array $benutzer, int $antragId, string $abteilung): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $abteilung): void {
        aufgabePruefen($benutzer, $antrag, 'al_pruefen');
        if (!isset(abteilungen()[$abteilung]) || $abteilung === $antrag['abteilung']) {
            throw new EingabeFehler('Bitte eine andere Abteilung wählen.');
        }
        antragSetzen($antrag['id'], ['abteilung_id' => abteilungId($abteilung)]);
        logEintrag($benutzer, 'umgeleitet', $antrag['id'], $antrag['abteilung'] . ' → ' . $abteilung);
        benachrichtigen(
            empfaengerAl($abteilung, [$antrag['antragsteller_id']]),
            'Antrag ' . antragNr($antrag['id']) . " wurde von der Abteilung {$antrag['abteilung']} an Ihre Abteilung umgeleitet.",
            $antrag['id']
        );
    });
}

function sslAbzeichnen(array $benutzer, int $antragId): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer): void {
        aufgabePruefen($benutzer, $antrag, 'ssl_abzeichnen');
        antragSetzen($antrag['id'], ['status' => 'abgezeichnet_ssl']);
        logEintrag($benutzer, 'abgezeichnet_ssl', $antrag['id'], vertretungsHinweis($benutzer, 'ssl_abzeichnen'));
        benachrichtigen(empfaengerRolle('sl', [$antrag['antragsteller_id']]),
            'Antrag ' . antragNr($antrag['id']) . ' liegt zur Genehmigung vor.', $antrag['id']);
    });
}

function slGenehmigen(array $benutzer, int $antragId): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer): void {
        aufgabePruefen($benutzer, $antrag, 'sl_entscheiden');
        antragSetzen($antrag['id'], ['status' => 'genehmigt']);
        logEintrag($benutzer, 'genehmigt', $antrag['id'], vertretungsHinweis($benutzer, 'sl_entscheiden'));
        benachrichtigen([$antrag['antragsteller_id']],
            'Ihr Antrag ' . antragNr($antrag['id']) . ' wurde genehmigt.', $antrag['id']);
        benachrichtigen(empfaengerRolle('sek'),
            'Antrag ' . antragNr($antrag['id']) . ' wurde genehmigt und ist abzuzeichnen.', $antrag['id']);
    });
}

function slAblehnen(array $benutzer, int $antragId, string $grund): void
{
    $grund = pflichtGrund($grund);
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $grund): void {
        aufgabePruefen($benutzer, $antrag, 'sl_entscheiden');
        antragSetzen($antrag['id'], ['status' => 'abgelehnt', 'al_abgezeichnet' => 0]);
        $vertretung = vertretungsHinweis($benutzer, 'sl_entscheiden');
        logEintrag($benutzer, 'abgelehnt', $antrag['id'], $vertretung ? "$grund ($vertretung)" : $grund);
        // AL wird mit Pop-up informiert und muss die Ablehnung abzeichnen
        benachrichtigen(
            empfaengerAl($antrag['abteilung'], [$antrag['antragsteller_id']]),
            'Antrag ' . antragNr($antrag['id']) . " wurde vom Schulleiter abgelehnt. Bitte die Ablehnung abzeichnen. Grund: $grund",
            $antrag['id'],
            true
        );
        benachrichtigen([$antrag['antragsteller_id']],
            'Ihr Antrag ' . antragNr($antrag['id']) . " wurde abgelehnt. Grund: $grund", $antrag['id'], true);
    });
}

function alBestaetigen(array $benutzer, int $antragId): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer): void {
        aufgabePruefen($benutzer, $antrag, 'al_bestaetigen');
        antragSetzen($antrag['id'], ['al_abgezeichnet' => 1]);
        $aktion = $antrag['status'] === 'abgelehnt' ? 'ablehnung_abgezeichnet' : 'stornierung_abgezeichnet';
        logEintrag($benutzer, $aktion, $antrag['id']);
        benachrichtigen(empfaengerRolle('sek'),
            'Antrag ' . antragNr($antrag['id']) . ' (' . STATUS_TEXT[$antrag['status']] . ') ist abzuzeichnen.', $antrag['id']);
    });
}

function sekAbzeichnen(array $benutzer, int $antragId): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer): void {
        aufgabePruefen($benutzer, $antrag, 'sek_abzeichnen');
        antragSetzen($antrag['id'], ['sek_erledigt' => 1]);
        logEintrag($benutzer, 'sek_abgezeichnet', $antrag['id'], $antrag['k'] ? 'Feld „K“ berücksichtigt' : '');
    });
}

function sekAusblenden(array $benutzer, int $antragId, bool $aus): void
{
    mitAntrag($antragId, function (array $antrag) use ($benutzer, $aus): void {
        if (!hatRolle($benutzer, 'sek') || !$antrag['sek_erledigt']) {
            throw new EingabeFehler('Nur abgeschlossene Anträge können aus- oder eingeblendet werden.');
        }
        antragSetzen($antrag['id'], ['ausgeblendet' => $aus ? 1 : 0]);
        logEintrag($benutzer, $aus ? 'ausgeblendet' : 'eingeblendet', $antrag['id']);
    });
}

/* ---------- Schulleiter: Datensicherung ---------- */

function datensicherung(array $benutzer): array
{
    if (!hatRolle($benutzer, 'sl')) {
        throw new EingabeFehler('Nur der Schulleiter darf die Datensicherung erstellen.');
    }
    $antraege = antraegeLaden('1 = 1', [], 'a.id');
    $log = db()->query('SELECT * FROM log_eintrag ORDER BY id')->fetchAll();
    logEintrag($benutzer, 'export', null, count($antraege) . ' Anträge, ' . count($log) . ' Log-Einträge');

    return [
        'erstellt'    => date('c'),
        'erstelltVon' => $benutzer['name'],
        'benutzer'    => array_map(fn($b) => [
            'login' => $b['login'], 'name' => $b['name'], 'rollen' => $b['rollen'],
            'abteilungen' => $b['abteilungen'], 'aktiv' => $b['aktiv'],
        ], alleBenutzer()),
        'antraege'    => $antraege,
        'log'         => $log,
    ];
}

/* ---------- Administration ---------- */

function benutzerSpeichern(array $admin, array $daten): array
{
    if (!hatRolle($admin, 'admin')) {
        throw new EingabeFehler('Nur Administratoren dürfen Benutzer verwalten.');
    }

    $id = isset($daten['id']) ? (int) $daten['id'] : 0;
    $login = mb_strtolower(trim((string) ($daten['login'] ?? '')));
    $name = trim((string) ($daten['name'] ?? ''));
    $amt = mb_substr(trim((string) ($daten['amt'] ?? '')), 0, 20);
    $aktiv = !empty($daten['aktiv']);
    $rollen = array_values(array_intersect(array_keys(ROLLEN), (array) ($daten['rollen'] ?? [])));
    $abteilungen = in_array('al', $rollen, true)
        ? array_values(array_intersect(array_keys(abteilungen()), (array) ($daten['abteilungen'] ?? [])))
        : [];

    if (!preg_match('/^[a-z][a-z0-9._-]{1,29}$/', $login)) {
        throw new EingabeFehler('Der Benutzername darf nur Kleinbuchstaben, Ziffern, Punkt, Binde- und Unterstrich enthalten.');
    }
    if ($name === '') {
        throw new EingabeFehler('Bitte einen Namen angeben.');
    }
    if (!$rollen) {
        throw new EingabeFehler('Bitte mindestens eine Rolle wählen.');
    }
    if (in_array('al', $rollen, true) && !$abteilungen) {
        throw new EingabeFehler('Abteilungsleiter brauchen mindestens eine Abteilung.');
    }
    if ($id === $admin['id'] && (!in_array('admin', $rollen, true) || !$aktiv)) {
        throw new EingabeFehler('Sie können sich nicht selbst die Administratorrechte entziehen.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $vorhanden = $pdo->prepare('SELECT id FROM benutzer WHERE login = ? AND id <> ?');
        $vorhanden->execute([$login, $id]);
        if ($vorhanden->fetch()) {
            throw new EingabeFehler('Dieser Benutzername ist schon vergeben.');
        }

        $neu = $id === 0;
        if ($neu) {
            $passwort = (string) (konfig()['app']['startpasswort'] ?? '');
            if ($passwort === '') {
                throw new EingabeFehler("In config.local.php fehlt 'startpasswort' unter 'app'.");
            }
            $pdo->prepare('INSERT INTO benutzer (login, name, amt, passwort_hash, aktiv) VALUES (?, ?, ?, ?, ?)')
                ->execute([$login, $name, $amt, password_hash($passwort, PASSWORD_DEFAULT), $aktiv ? 1 : 0]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE benutzer SET login = ?, name = ?, amt = ?, aktiv = ? WHERE id = ?')
                ->execute([$login, $name, $amt, $aktiv ? 1 : 0, $id]);
        }

        $pdo->prepare('DELETE FROM benutzer_rolle WHERE benutzer_id = ?')->execute([$id]);
        $rolleEinfuegen = $pdo->prepare('INSERT INTO benutzer_rolle (benutzer_id, rolle) VALUES (?, ?)');
        foreach ($rollen as $rolle) {
            $rolleEinfuegen->execute([$id, $rolle]);
        }

        $pdo->prepare('DELETE FROM al_abteilung WHERE benutzer_id = ?')->execute([$id]);
        $alEinfuegen = $pdo->prepare('INSERT INTO al_abteilung (benutzer_id, abteilung_id) VALUES (?, ?)');
        foreach ($abteilungen as $kuerzel) {
            $alEinfuegen->execute([$id, abteilungId($kuerzel)]);
        }

        $benutzer = benutzerNachId($id);
        logEintrag($admin, $neu ? 'benutzer_angelegt' : 'benutzer_geaendert', null,
            $login . ': ' . rollenText($benutzer) . ($aktiv ? '' : ' (deaktiviert)'));
        $pdo->commit();
    } catch (Throwable $fehler) {
        $pdo->rollBack();
        throw $fehler;
    }
    return benutzerNachId($id);
}

/* ---------- Anlagen ---------- */

function anlagePfad(string $datei): string
{
    return ANLAGEN_ORDNER . '/' . basename($datei);
}

// Prüft und speichert eine hochgeladene Datei; null, wenn keine gewählt wurde
function anlageAnnehmen(?array $upload): ?array
{
    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $upload['size'] > ANLAGE_MAX_BYTES) {
        throw new EingabeFehler('Die Anlage ist zu groß. Erlaubt sind höchstens 5 MB.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new EingabeFehler('Die Anlage konnte nicht hochgeladen werden. Bitte wählen Sie die Datei erneut.');
    }

    // Dateityp am Inhalt prüfen, nicht an der Endung
    $typ = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!isset(ANLAGE_TYPEN[$typ])) {
        throw new EingabeFehler('Als Anlage sind nur PDF, JPG und PNG erlaubt.');
    }

    if (!is_dir(ANLAGEN_ORDNER)) {
        mkdir(ANLAGEN_ORDNER, 0770, true);
    }

    // zufälliger Name, damit niemand fremde Dateien erraten oder überschreiben kann
    $datei = bin2hex(random_bytes(16)) . '.' . ANLAGE_TYPEN[$typ];
    if (!move_uploaded_file($upload['tmp_name'], anlagePfad($datei))) {
        throw new EingabeFehler('Die Anlage konnte nicht gespeichert werden.');
    }

    $name = mb_substr(basename(str_replace('\\', '/', (string) $upload['name'])), 0, 200);
    return ['name' => $name, 'datei' => $datei];
}

function anlageEntfernen(string $datei): void
{
    $pfad = anlagePfad($datei);
    if (is_file($pfad)) {
        unlink($pfad);
    }
}

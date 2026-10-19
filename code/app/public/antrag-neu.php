<?php
declare(strict_types=1);

/*
 * Antrag stellen bzw. Entwurf bearbeiten.
 * Das Formular schickt sich selbst ab: beim Wechsel des Anlasses, beim Hinzufügen
 * einer Tätigkeitszeile, beim Speichern als Entwurf und beim Absenden.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen(['lehrer']);

$entwurf = null;
$id = (int) ($_POST['antrag_id'] ?? get('id'));
if ($id) {
    $entwurf = antragNachId($id);
    if (!$entwurf || !darfBearbeitenEntwurf($benutzer, $entwurf)) {
        hinweisMerken('Dieser Antrag kann nicht mehr bearbeitet werden.', 'fehler');
        weiterleiten('meine-antraege.php');
    }
}

// Startwerte: aus dem Entwurf oder leer
$werte = [
    'abteilung' => $entwurf['abteilung'] ?? '',
    'anlass'    => $entwurf['anlass'] ?? '',
    'daten'     => $entwurf['daten'] ?? [],
];
$fehler = [];
$tat = post('tat');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();

    $werte['abteilung'] = post('abteilung');
    $werte['anlass'] = post('anlass');
    $werte['daten'] = formulardatenLesen($werte['anlass']);

    if ($tat === 'zeile') {
        $werte['daten']['taetigkeiten'][] = ['datum' => '', 'stunden' => '', 'taetigkeit' => ''];
    }

    if ($tat === 'entwurf' || $tat === 'absenden') {
        try {
            $eingabe = $werte + ['id' => $entwurf['id'] ?? null, 'anlage' => anlageAnnehmen($_FILES['anlage'] ?? null)];
            if ($tat === 'absenden') {
                $fehler = antragPruefen($eingabe);
            }
            if (!$fehler) {
                $antrag = antragSpeichern($benutzer, $eingabe, $tat === 'absenden');
                hinweisMerken($tat === 'absenden'
                    ? 'Antrag ' . antragNr($antrag['id']) . ' wurde abgesendet. Die Abteilungsleitung ' . $antrag['abteilung'] . ' wurde benachrichtigt.'
                    : 'Entwurf ' . antragNr($antrag['id']) . ' wurde gespeichert.');
                weiterleiten('meine-antraege.php?id=' . $antrag['id']);
            }
        } catch (EingabeFehler $ausnahme) {
            $fehler['anlage'] = $ausnahme->getMessage();
        }
    }
}

// liest die Felder des gewählten Anlasses aus dem Formular
function formulardatenLesen(string $anlass): array
{
    if (!isset(ANLAESSE[$anlass])) {
        return [];
    }
    $daten = [];
    foreach (felderVon($anlass) as $feld) {
        if ($feld['typ'] === 'taetigkeiten') {
            $zeilen = [];
            foreach ((array) ($_POST['taet_datum'] ?? []) as $i => $wert) {
                $zeile = [
                    'datum'      => trim((string) $wert),
                    'stunden'    => trim((string) ($_POST['taet_stunden'][$i] ?? '')),
                    'taetigkeit' => trim((string) ($_POST['taet_taetigkeit'][$i] ?? '')),
                ];
                if ($zeile['datum'] || $zeile['stunden'] || $zeile['taetigkeit']) {
                    $zeilen[] = $zeile;
                }
            }
            $daten['taetigkeiten'] = $zeilen;
            continue;
        }
        $daten[$feld['name']] = $feld['typ'] === 'checkbox' ? isset($_POST[$feld['name']]) : post($feld['name']);
    }
    return $daten;
}

// ein einzelnes Eingabefeld
function feldAusgeben(array $feld, array $daten, array $fehler): void
{
    $id = 'feld-' . $feld['name'];
    $wert = $daten[$feld['name']] ?? '';
    $meldung = $fehler[$feld['name']] ?? null;
    $klasse = 'feld' . (!empty($feld['breit']) ? ' feld--breit' : '');
    $beschreibung = array_filter([
        !empty($feld['hilfe']) ? "$id-hilfe" : null,
        $meldung ? "$id-fehler" : null,
    ]);
    $zusatz = ($meldung ? ' aria-invalid="true"' : '')
        . ($beschreibung ? ' aria-describedby="' . implode(' ', $beschreibung) . '"' : '');
    $optional = !empty($feld['optional']) ? ' <span class="leise">(optional)</span>' : '';

    echo '<div class="' . $klasse . '">';
    if ($feld['typ'] === 'checkbox') {
        echo '<label class="haken"><input type="checkbox" id="' . $id . '" name="' . e($feld['name']) . '"'
            . ($wert ? ' checked' : '') . '> ' . e($feld['label']) . '</label>';
    } else {
        echo '<label for="' . $id . '">' . e($feld['label']) . $optional . '</label>';
        if ($feld['typ'] === 'textarea') {
            echo '<textarea id="' . $id . '" name="' . e($feld['name']) . '" rows="3"' . $zusatz . '>' . e((string) $wert) . '</textarea>';
        } elseif ($feld['typ'] === 'select') {
            echo '<select id="' . $id . '" name="' . e($feld['name']) . '"' . $zusatz . '><option value="">Bitte wählen …</option>';
            foreach ($feld['gruppen'] as $gruppe => $optionen) {
                echo '<optgroup label="' . e($gruppe) . '">';
                foreach ($optionen as $option) {
                    echo '<option' . ($option === $wert ? ' selected' : '') . '>' . e($option) . '</option>';
                }
                echo '</optgroup>';
            }
            echo '</select>';
        } else {
            echo '<input id="' . $id . '" name="' . e($feld['name']) . '" type="' . e($feld['typ'])
                . '" value="' . e((string) $wert) . '"' . $zusatz . '>';
        }
    }
    if (!empty($feld['hilfe'])) {
        echo '<small class="feld__hilfe" id="' . $id . '-hilfe">' . e($feld['hilfe']) . '</small>';
    }
    if ($meldung) {
        echo '<small class="feld__fehler" id="' . $id . '-fehler">' . e($meldung) . '</small>';
    }
    echo '</div>';
}

$anlass = ANLAESSE[$werte['anlass']] ?? null;
seitenkopf($entwurf ? 'Entwurf ' . antragNr($entwurf['id']) : 'Antrag stellen', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div>
    <h1><?= $entwurf ? 'Entwurf ' . e(antragNr($entwurf['id'])) . ' bearbeiten' : 'Antrag stellen' ?></h1>
    <p class="leise">Änderung der Unterrichtsorganisation</p>
  </div>

  <?php if ($entwurf && ($zurueck = zurueckweisung($entwurf))): ?>
    <p class="begruendung begruendung--warn">
      <b>Zur Korrektur zurückgewiesen von <?= e($zurueck['benutzer_name']) ?>:</b> <?= e($zurueck['bemerkung']) ?>
    </p>
  <?php endif ?>

  <?php if ($fehler): ?>
    <div class="hinweis hinweis--fehler" role="alert">Bitte prüfen Sie die rot markierten Felder.</div>
  <?php endif ?>

  <form method="post" enctype="multipart/form-data" class="stapel" novalidate>
    <?= csrfFeld() ?>
    <?php if ($entwurf): ?><input type="hidden" name="antrag_id" value="<?= $entwurf['id'] ?>"><?php endif ?>

    <div class="karte">
      <fieldset>
        <legend>1. Abteilung und Anlass</legend>
        <div class="formular-raster">
          <div class="feld">
            <span class="feld__titel">Antragsteller</span>
            <p><?= e($benutzer['name']) ?><?= $benutzer['amt'] ? ', ' . e($benutzer['amt']) : '' ?></p>
          </div>
          <div class="feld">
            <span class="feld__titel">Datum</span>
            <p><?= datum(date('Y-m-d')) ?></p>
          </div>
          <div class="feld feld--breit">
            <span class="feld__titel" id="abteilung-titel">Abteilung</span>
            <div class="auswahlreihe" role="radiogroup" aria-labelledby="abteilung-titel">
              <?php foreach (abteilungen() as $kuerzel => $abteilung): ?>
                <label class="haken" title="<?= e($abteilung['name']) ?>">
                  <input type="radio" name="abteilung" value="<?= e($kuerzel) ?>"<?= $werte['abteilung'] === $kuerzel ? ' checked' : '' ?>>
                  <?= e($kuerzel) ?>
                </label>
              <?php endforeach ?>
            </div>
            <?php if (isset($fehler['abteilung'])): ?>
              <small class="feld__fehler"><?= e($fehler['abteilung']) ?></small>
            <?php endif ?>
          </div>
          <div class="feld feld--breit">
            <label for="feld-anlass">Anlass (Unterrichtsorganisation bei Abwesenheit)</label>
            <select id="feld-anlass" name="anlass" data-auto-absenden>
              <option value="">Bitte wählen …</option>
              <?php foreach (ANLAESSE as $schluessel => $info): ?>
                <option value="<?= e($schluessel) ?>"<?= $schluessel === $werte['anlass'] ? ' selected' : '' ?>><?= e($info['titel']) ?></option>
              <?php endforeach ?>
            </select>
            <?php if (isset($fehler['anlass'])): ?>
              <small class="feld__fehler"><?= e($fehler['anlass']) ?></small>
            <?php endif ?>
            <noscript><div class="knopfzeile knopfzeile--links"><button type="submit" class="knopf knopf--zweit">Weiter</button></div></noscript>
          </div>
        </div>
      </fieldset>
    </div>

    <?php if ($anlass): ?>
      <?php foreach ($anlass['abschnitte'] as $nr => $schluessel):
          $abschnitt = ABSCHNITTE[$schluessel]; ?>
        <div class="karte">
          <fieldset>
            <legend><?= $nr + 2 ?>. <?= e($abschnitt['titel']) ?></legend>
            <?php if (!empty($abschnitt['hinweis'])): ?>
              <p class="leise abstand-unten"><?= e($abschnitt['hinweis']) ?></p>
            <?php endif ?>
            <div class="formular-raster">
              <?php foreach ($abschnitt['felder'] as $feld): ?>
                <?php if ($feld['typ'] !== 'taetigkeiten'): ?>
                  <?php feldAusgeben($feld, $werte['daten'], $fehler) ?>
                <?php else:
                    $zeilen = $werte['daten']['taetigkeiten'] ?? [];
                    if (!$zeilen) {
                        $zeilen = [['datum' => '', 'stunden' => '', 'taetigkeit' => '']];
                    } ?>
                  <div class="feld feld--breit">
                    <div class="tabelle-rahmen">
                      <table class="tabelle tabelle--eingabe">
                        <thead>
                          <tr><th scope="col">Datum</th><th scope="col">U-Std (1.–4. Std.)</th><th scope="col">Anderweitige dienstliche Tätigkeit</th></tr>
                        </thead>
                        <tbody>
                          <?php foreach ($zeilen as $i => $zeile): ?>
                            <tr>
                              <td><input type="date" name="taet_datum[]" aria-label="Datum Zeile <?= $i + 1 ?>" value="<?= e($zeile['datum']) ?>"></td>
                              <td><input type="text" name="taet_stunden[]" aria-label="U-Std Zeile <?= $i + 1 ?>" placeholder="z. B. 1.–4." value="<?= e($zeile['stunden']) ?>"></td>
                              <td><input type="text" name="taet_taetigkeit[]" aria-label="Tätigkeit Zeile <?= $i + 1 ?>" value="<?= e($zeile['taetigkeit']) ?>"></td>
                            </tr>
                          <?php endforeach ?>
                        </tbody>
                      </table>
                    </div>
                    <?php if (isset($fehler['taetigkeiten'])): ?>
                      <small class="feld__fehler"><?= e($fehler['taetigkeiten']) ?></small>
                    <?php endif ?>
                    <div class="knopfzeile knopfzeile--links">
                      <button type="submit" name="tat" value="zeile" class="knopf knopf--zweit knopf--klein">+ Zeile hinzufügen</button>
                    </div>
                    <small class="feld__hilfe">Leere Zeilen werden nicht gespeichert.</small>
                  </div>
                <?php endif ?>
              <?php endforeach ?>
            </div>
          </fieldset>
        </div>
      <?php endforeach ?>

      <div class="karte">
        <fieldset>
          <legend><?= count($anlass['abschnitte']) + 2 ?>. Anlage und Absenden</legend>
          <div class="formular-raster">
            <div class="feld">
              <label for="feld-anlage">Anlage <span class="leise">(optional)</span></label>
              <input id="feld-anlage" name="anlage" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="feld-anlage-hilfe">
              <small class="feld__hilfe" id="feld-anlage-hilfe">z. B. Einladungsschreiben; PDF, JPG oder PNG, höchstens 5 MB</small>
              <?php if (isset($fehler['anlage'])): ?>
                <small class="feld__fehler"><?= e($fehler['anlage']) ?></small>
              <?php endif ?>
              <?php if ($entwurf && $entwurf['anlage_name']): ?>
                <small class="feld__hilfe">Gespeichert: <?= e($entwurf['anlage_name']) ?></small>
              <?php endif ?>
            </div>
          </div>
        </fieldset>
        <div class="knopfzeile knopfzeile--links">
          <button type="submit" name="tat" value="absenden" class="knopf"
                  formnovalidate
                  data-titel1="Antrag absenden"
                  data-frage1="Der Antrag wird an die zuständige Abteilungsleitung weitergeleitet. Nach dem Absenden ist keine Änderung mehr möglich."
                  data-ja1="Absenden" data-nein1="Zurück">Antrag absenden</button>
          <button type="submit" name="tat" value="entwurf" class="knopf knopf--zweit">Als Entwurf speichern</button>
        </div>
      </div>
    <?php endif ?>
  </form>
</main>
<?php
seitenfuss();

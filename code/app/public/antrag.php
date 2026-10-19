<?php
declare(strict_types=1);

/*
 * Einzelner Antrag: Angaben, Bearbeitungsstand, Verlauf und die Aktionen,
 * die dem angemeldeten Benutzer gerade zustehen.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen();
$id = (int) ($_POST['antrag_id'] ?? get('id'));
$antrag = $id ? antragNachId($id) : null;

if (!$antrag || !darfAnsehen($benutzer, $antrag)) {
    seitenkopf('Antrag', $benutzer);
    echo '<main class="inhalt"><div class="karte stapel stapel--eng"><h1>Antrag nicht verfügbar</h1>'
        . '<p>Diesen Antrag gibt es nicht oder Sie dürfen ihn nicht einsehen.</p></div></main>';
    seitenfuss();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();
    $meldung = '';
    try {
        switch (post('tat')) {
            case 'al_abzeichnen':
                alAbzeichnen($benutzer, $id, isset($_POST['k']));
                $meldung = 'Antrag ' . antragNr($id) . ' wurde abgezeichnet.';
                break;
            case 'al_umleiten':
                alUmleiten($benutzer, $id, post('abteilung'));
                $meldung = 'Antrag ' . antragNr($id) . ' wurde an ' . post('abteilung') . ' umgeleitet.';
                break;
            case 'al_zurueckweisen':
                alZurueckweisen($benutzer, $id, post('grund'));
                $meldung = 'Antrag ' . antragNr($id) . ' wurde zur Korrektur zurückgewiesen.';
                break;
            case 'ssl_abzeichnen':
                sslAbzeichnen($benutzer, $id);
                $meldung = 'Antrag ' . antragNr($id) . ' wurde abgezeichnet.';
                break;
            case 'sl_genehmigen':
                slGenehmigen($benutzer, $id);
                $meldung = 'Antrag ' . antragNr($id) . ' wurde genehmigt.';
                break;
            case 'sl_ablehnen':
                slAblehnen($benutzer, $id, post('grund'));
                $meldung = 'Antrag ' . antragNr($id) . ' wurde abgelehnt.';
                break;
            case 'al_bestaetigen':
                $was = $antrag['status'] === 'abgelehnt' ? 'Ablehnung' : 'Stornierung';
                alBestaetigen($benutzer, $id);
                $meldung = "$was von " . antragNr($id) . ' wurde abgezeichnet.';
                break;
            case 'sek_abzeichnen':
                sekAbzeichnen($benutzer, $id);
                $meldung = 'Vorgang ' . antragNr($id) . ' wurde abgezeichnet.';
                break;
            case 'sek_blenden':
                $aus = !$antrag['ausgeblendet'];
                sekAusblenden($benutzer, $id, $aus);
                hinweisMerken('Antrag ' . antragNr($id) . ($aus ? ' wurde ausgeblendet.' : ' wurde eingeblendet.'));
                weiterleiten('antrag.php?id=' . $id);
                // no break
            default:
                throw new EingabeFehler('Unbekannte Aktion.');
        }
        hinweisMerken($meldung);
        weiterleiten('eingang.php');
    } catch (EingabeFehler $fehler) {
        hinweisMerken($fehler->getMessage(), 'fehler');
        weiterleiten('antrag.php?id=' . $id);
    }
}

$aufgabe = aufgabe($benutzer, $antrag);
$vertretung = $aufgabe ? vertretungsHinweis($benutzer, $aufgabe) : '';
$zurueck = $antrag['antragsteller_id'] === $benutzer['id']
    ? 'meine-antraege.php?id=' . $antrag['id']
    : (hatEineRolle($benutzer, ['al', 'ssl', 'sl', 'sek']) ? 'eingang.php' : startseite($benutzer));

seitenkopf('Antrag ' . antragNr($antrag['id']), $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <p class="zurueck"><a href="<?= e($zurueck) ?>">← Zurück</a></p>
  <h1>Antrag <?= e(antragNr($antrag['id'])) ?>: <?= e(kurzbeschreibung($antrag)) ?></h1>

  <?php if ($aufgabe): ?>
    <section class="karte karte--aufgabe" aria-labelledby="aufgabe-titel">
      <h2 id="aufgabe-titel">Ihre Aufgabe: <?= e(AUFGABEN[$aufgabe]) ?><?php
        if ($vertretung): ?> <small class="leise">(<?= e($vertretung) ?>)</small><?php endif ?></h2>

      <?php if ($aufgabe === 'al_pruefen'): ?>
        <p class="leise abstand-unten">Regeln Sie, was nötig ist (z. B. Vertretung), und zeichnen Sie den Antrag dann ab.
          Gehört der Antrag in eine andere Abteilung, leiten Sie ihn dorthin um.</p>

        <form method="post" class="stapel stapel--eng">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <div class="formular-raster">
            <div class="feld feld--unten">
              <label class="haken"><input type="checkbox" name="k" value="1"<?= $antrag['k'] ? ' checked' : '' ?>>
                Feld „K“ in Untis setzen (ganztägige Abwesenheit)</label>
            </div>
          </div>
          <div class="knopfzeile knopfzeile--links">
            <button type="submit" name="tat" value="al_abzeichnen" class="knopf"
                    data-titel1="Abzeichnen (1 von 2)"
                    data-frage1="Möchten Sie den Antrag <?= e(antragNr($antrag['id'])) ?> abzeichnen?"
                    data-ja1="Weiter"
                    data-titel2="Abzeichnen (2 von 2)"
                    data-frage2="Bitte bestätigen Sie: Antrag <?= e(antragNr($antrag['id'])) ?> wird verbindlich abgezeichnet und an den stellv. Schulleiter weitergeleitet."
                    data-ja2="Bestätigen">Abzeichnen</button>
            <button type="submit" name="tat" value="al_zurueckweisen" class="knopf knopf--zweit"
                    data-titel1="Zur Korrektur zurückweisen"
                    data-frage1="Der Antrag geht zurück an <?= e($antrag['antragsteller_name']) ?>, der ihn korrigieren und erneut absenden kann."
                    data-eingabe="Was ist zu korrigieren?" data-eingabe-name="grund"
                    data-ja1="Zurückweisen">Zur Korrektur zurückweisen</button>
          </div>
          <input type="hidden" name="grund" value="">
        </form>

        <form method="post" class="umleiten">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <div class="formular-raster">
            <div class="feld">
              <label for="abteilung">Andere Abteilung</label>
              <select id="abteilung" name="abteilung">
                <?php foreach (abteilungen() as $kuerzel => $abteilung): ?>
                  <option<?= $kuerzel === $antrag['abteilung'] ? ' selected' : '' ?>><?= e($kuerzel) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="feld feld--unten">
              <button type="submit" name="tat" value="al_umleiten" class="knopf knopf--zweit"
                      data-titel1="Antrag umleiten"
                      data-frage1="Der Antrag wird an die gewählte Abteilung umgeleitet. Deren Abteilungsleitung wird informiert."
                      data-ja1="Umleiten">Umleiten</button>
            </div>
          </div>
        </form>

      <?php elseif ($aufgabe === 'ssl_abzeichnen'): ?>
        <p class="leise">Der Abteilungsleiter hat den Antrag abgezeichnet. Mit Ihrer Abzeichnung nehmen Sie ihn zur Kenntnis;
          danach liegt er beim Schulleiter.</p>
        <form method="post" class="knopfzeile knopfzeile--links">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <button type="submit" name="tat" value="ssl_abzeichnen" class="knopf"
                  data-titel1="Abzeichnen (1 von 2)"
                  data-frage1="Möchten Sie den Antrag <?= e(antragNr($antrag['id'])) ?> abzeichnen?"
                  data-ja1="Weiter"
                  data-titel2="Abzeichnen (2 von 2)"
                  data-frage2="Bitte bestätigen Sie: Antrag <?= e(antragNr($antrag['id'])) ?> wird verbindlich abgezeichnet und an den Schulleiter weitergeleitet."
                  data-ja2="Bestätigen">Abzeichnen</button>
        </form>

      <?php elseif ($aufgabe === 'sl_entscheiden'): ?>
        <p class="leise">Genehmigen oder ablehnen. Bei einer Ablehnung werden die Abteilungsleitung und der Antragsteller informiert.</p>
        <form method="post" class="knopfzeile knopfzeile--links">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <input type="hidden" name="grund" value="">
          <button type="submit" name="tat" value="sl_genehmigen" class="knopf"
                  data-titel1="Genehmigen (1 von 2)"
                  data-frage1="Möchten Sie den Antrag <?= e(antragNr($antrag['id'])) ?> genehmigen?"
                  data-ja1="Weiter"
                  data-titel2="Genehmigen (2 von 2)"
                  data-frage2="Bitte bestätigen Sie: Antrag <?= e(antragNr($antrag['id'])) ?> wird verbindlich genehmigt."
                  data-ja2="Genehmigen">Genehmigen</button>
          <button type="submit" name="tat" value="sl_ablehnen" class="knopf knopf--gefahr-zweit"
                  data-titel1="Antrag ablehnen"
                  data-frage1="Der Antrag wird abgelehnt. Die Abteilungsleitung <?= e($antrag['abteilung']) ?> und der Antragsteller werden informiert."
                  data-eingabe="Begründung" data-eingabe-name="grund"
                  data-ja1="Ablehnen" data-gefahr1="1">Ablehnen</button>
        </form>

      <?php elseif ($aufgabe === 'al_bestaetigen'):
          $was = $antrag['status'] === 'abgelehnt' ? 'Ablehnung' : 'Stornierung'; ?>
        <p class="leise"><?= $antrag['status'] === 'abgelehnt'
            ? 'Der Schulleiter hat den Antrag abgelehnt.'
            : 'Der Antragsteller hat den Antrag zurückgezogen.' ?>
          Bitte leiten Sie das Nötige ein (z. B. Vertretung aufheben) und zeichnen Sie den Vorgang ab.
          Danach geht er an das Sekretariat.</p>
        <form method="post" class="knopfzeile knopfzeile--links">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <button type="submit" name="tat" value="al_bestaetigen" class="knopf"
                  data-titel1="<?= $was ?> abzeichnen (1 von 2)"
                  data-frage1="Haben Sie die <?= $was ?> von Antrag <?= e(antragNr($antrag['id'])) ?> zur Kenntnis genommen und das Nötige veranlasst?"
                  data-ja1="Weiter"
                  data-titel2="<?= $was ?> abzeichnen (2 von 2)"
                  data-frage2="Bitte bestätigen Sie: Die <?= $was ?> wird abgezeichnet und an das Sekretariat weitergeleitet."
                  data-ja2="Bestätigen"><?= $was ?> abzeichnen</button>
        </form>

      <?php elseif ($aufgabe === 'sek_abzeichnen'): ?>
        <p class="leise"><?= $antrag['k'] ? '<b>Feld „K“ ist gesetzt</b> (ganztägige Abwesenheit) – bitte Untis-Eintrag prüfen. ' : '' ?>
          Mit Ihrer Abzeichnung ist der Vorgang abgeschlossen. Danach können Sie ihn ausblenden.</p>
        <form method="post" class="knopfzeile knopfzeile--links">
          <?= csrfFeld() ?>
          <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
          <button type="submit" name="tat" value="sek_abzeichnen" class="knopf"
                  data-titel1="Abzeichnen (1 von 2)"
                  data-frage1="Möchten Sie den Vorgang <?= e(antragNr($antrag['id'])) ?> abzeichnen?"
                  data-ja1="Weiter"
                  data-titel2="Abzeichnen (2 von 2)"
                  data-frage2="Bitte bestätigen Sie: Der Vorgang <?= e(antragNr($antrag['id'])) ?> (<?= e(statusText($antrag)) ?>) wird abgeschlossen."
                  data-ja2="Bestätigen">Abzeichnen</button>
        </form>
      <?php endif ?>
    </section>
  <?php endif ?>

  <?php if (hatRolle($benutzer, 'sek') && $antrag['sek_erledigt']): ?>
    <section class="karte">
      <p><?= $antrag['ausgeblendet']
          ? 'Dieser Antrag ist ausgeblendet und erscheint nur, wenn „Ausgeblendete anzeigen“ gewählt ist.'
          : 'Der Vorgang ist abgeschlossen.' ?></p>
      <form method="post" class="knopfzeile knopfzeile--links">
        <?= csrfFeld() ?>
        <input type="hidden" name="antrag_id" value="<?= $antrag['id'] ?>">
        <button type="submit" name="tat" value="sek_blenden" class="knopf knopf--zweit">
          <?= $antrag['ausgeblendet'] ? 'Einblenden' : 'Ausblenden' ?>
        </button>
      </form>
    </section>
  <?php endif ?>

  <?php angabenAusgeben($antrag) ?>

  <section class="karte" aria-labelledby="stand-titel">
    <h2 id="stand-titel">Bearbeitungsstand</h2>
    <?php standAusgeben($antrag) ?>
  </section>

  <section class="karte" aria-labelledby="verlauf-titel">
    <h2 id="verlauf-titel">Verlauf (Log)</h2>
    <?php verlaufAusgeben($antrag['id']) ?>
  </section>
</main>
<?php
seitenfuss();

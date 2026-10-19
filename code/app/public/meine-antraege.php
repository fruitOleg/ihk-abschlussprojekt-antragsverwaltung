<?php
declare(strict_types=1);

/*
 * Eigene Anträge der Lehrkraft: Liste, Bearbeitungsstand, Entwurf löschen, Antrag zurückziehen.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen(['lehrer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();
    $id = (int) ($_POST['antrag_id'] ?? 0);
    try {
        if (post('tat') === 'loeschen') {
            entwurfLoeschen($benutzer, $id);
            hinweisMerken('Entwurf ' . antragNr($id) . ' wurde gelöscht.');
            weiterleiten('meine-antraege.php');
        }
        antragZurueckziehen($benutzer, $id, post('grund'));
        hinweisMerken('Antrag ' . antragNr($id) . ' wurde zurückgezogen. Die Abteilungsleitung wurde informiert.');
    } catch (EingabeFehler $fehler) {
        hinweisMerken($fehler->getMessage(), 'fehler');
    }
    weiterleiten('meine-antraege.php?id=' . $id);
}

$antraege = antraegeVon($benutzer);

// Stand wird für den gewählten Antrag angezeigt, sonst für den obersten
$gewuenscht = (int) get('id');
$gewaehlt = $antraege[0] ?? null;
foreach ($antraege as $antrag) {
    if ($antrag['id'] === $gewuenscht) {
        $gewaehlt = $antrag;
    }
}

seitenkopf('Meine Anträge', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div class="titelzeile">
    <h1>Meine Anträge</h1>
    <a class="knopf" href="antrag-neu.php">Neuer Antrag</a>
  </div>

  <?php if (!$antraege): ?>
    <div class="karte">
      <p class="leer">Sie haben noch keine Anträge gestellt. <a href="antrag-neu.php">Antrag stellen</a></p>
    </div>
  <?php else: ?>
    <div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th>
            <th scope="col">Anlass</th>
            <th scope="col">Abt.</th>
            <th scope="col">Datum</th>
            <th scope="col">Stand</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($antraege as $antrag):
              $aktiv = $antrag['id'] === $gewaehlt['id']; ?>
            <tr<?= $aktiv ? ' class="gewaehlt"' : '' ?>>
              <td class="zahl">
                <a href="meine-antraege.php?id=<?= $antrag['id'] ?>"<?= $aktiv ? ' aria-current="true"' : '' ?>><?= e(antragNr($antrag['id'])) ?></a>
              </td>
              <td><?= e(kurzbeschreibung($antrag)) ?></td>
              <td><?= e($antrag['abteilung'] ?? '–') ?></td>
              <td class="zahl"><?= datum($antrag['eingereicht'] ?? $antrag['erstellt']) ?></td>
              <td><?= statusMarke($antrag) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <section class="karte" aria-labelledby="stand-titel">
      <h2 id="stand-titel">Stand zu <?= e(antragNr($gewaehlt['id'])) ?>: <?= e(kurzbeschreibung($gewaehlt)) ?></h2>

      <?php if ($gewaehlt['status'] === 'entwurf' && !zurueckweisung($gewaehlt)): ?>
        <p class="leise">Dieser Entwurf ist nur für Sie sichtbar und wurde noch nicht abgesendet.</p>
      <?php else: ?>
        <?php standAusgeben($gewaehlt) ?>
      <?php endif ?>

      <div class="knopfzeile knopfzeile--links">
        <?php if ($gewaehlt['status'] === 'entwurf'): ?>
          <a class="knopf" href="antrag-neu.php?id=<?= $gewaehlt['id'] ?>">
            <?= zurueckweisung($gewaehlt) ? 'Korrigieren und erneut absenden' : 'Entwurf bearbeiten' ?>
          </a>
        <?php else: ?>
          <a class="knopf knopf--zweit" href="antrag.php?id=<?= $gewaehlt['id'] ?>">Details und Verlauf</a>
        <?php endif ?>

        <?php if (darfEntwurfLoeschen($benutzer, $gewaehlt)): ?>
          <form method="post"
                data-titel1="Entwurf löschen"
                data-frage1="Der Entwurf <?= e(antragNr($gewaehlt['id'])) ?> wird endgültig gelöscht."
                data-ja1="Löschen" data-nein1="Zurück" data-gefahr1="1">
            <?= csrfFeld() ?>
            <input type="hidden" name="tat" value="loeschen">
            <input type="hidden" name="antrag_id" value="<?= $gewaehlt['id'] ?>">
            <button type="submit" class="knopf knopf--gefahr-zweit">Entwurf löschen</button>
          </form>
        <?php endif ?>

        <?php if (darfZurueckziehen($benutzer, $gewaehlt)): ?>
          <form method="post"
                data-titel1="Antrag zurückziehen"
                data-frage1="Antrag <?= e(antragNr($gewaehlt['id'])) ?> wird storniert. Die Abteilungsleitung <?= e($gewaehlt['abteilung']) ?> wird informiert und muss die Stornierung abzeichnen. Der Antrag bleibt archiviert."
                data-eingabe="Grund" data-eingabe-name="grund" data-eingabe-pflicht="0"
                data-ja1="Zurückziehen" data-nein1="Abbrechen" data-gefahr1="1">
            <?= csrfFeld() ?>
            <input type="hidden" name="tat" value="zurueckziehen">
            <input type="hidden" name="antrag_id" value="<?= $gewaehlt['id'] ?>">
            <input type="hidden" name="grund" value="">
            <button type="submit" class="knopf knopf--gefahr-zweit">Antrag zurückziehen</button>
          </form>
        <?php endif ?>
      </div>
    </section>
  <?php endif ?>
</main>
<?php
seitenfuss();

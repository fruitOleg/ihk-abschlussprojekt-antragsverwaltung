<?php
declare(strict_types=1);

/*
 * Nur für den Schulleiter: Logdatei ansehen, als CSV ausgeben und
 * alle Antragsdaten samt Logdatei zur Datensicherung exportieren.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen(['sl']);

// CSV und JSON werden direkt ausgeliefert, nicht als Seite
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();
    $zeitstempel = date('Ymd-Hi');

    if (post('tat') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"logdatei-$zeitstempel.csv\"");
        $ausgabe = fopen('php://output', 'w');
        fwrite($ausgabe, "\xEF\xBB\xBF"); // damit Excel die Umlaute richtig liest
        fputcsv($ausgabe, ['Nr', 'Zeitpunkt', 'Person', 'Aktion', 'Antrag', 'Bemerkung'], ';');
        foreach (logAlle($benutzer) as $eintrag) {
            fputcsv($ausgabe, [
                $eintrag['id'], $eintrag['zeit'], $eintrag['benutzer_name'],
                AKTIONEN[$eintrag['aktion']] ?? $eintrag['aktion'],
                $eintrag['antrag_id'] ? antragNr((int) $eintrag['antrag_id']) : '',
                $eintrag['bemerkung'],
            ], ';');
        }
        exit;
    }

    if (post('tat') === 'export') {
        $daten = datensicherung($benutzer);
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"datensicherung-$zeitstempel.json\"");
        echo json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    weiterleiten('protokoll.php');
}

$aktion = get('aktion');
$suche = mb_strtolower(get('suche'));

$eintraege = array_filter(logAlle($benutzer), function (array $eintrag) use ($aktion, $suche) {
    if ($aktion !== '' && $eintrag['aktion'] !== $aktion) {
        return false;
    }
    if ($suche === '') {
        return true;
    }
    $text = mb_strtolower(($eintrag['antrag_id'] ? antragNr((int) $eintrag['antrag_id']) : '') . ' '
        . $eintrag['benutzer_name'] . ' ' . (AKTIONEN[$eintrag['aktion']] ?? '') . ' ' . $eintrag['bemerkung']);
    return str_contains($text, $suche);
});
$eintraege = array_reverse($eintraege);

seitenkopf('Log & Export', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div>
    <h1>Log &amp; Export</h1>
    <p class="leise">Die Logdatei wird bei jeder Aktion automatisch ergänzt. Sie kann ausgegeben,
      aber von niemandem verändert werden.</p>
  </div>

  <section class="karte" aria-labelledby="export-titel">
    <h2 id="export-titel">Datensicherung</h2>
    <p>Export aller Antragsdaten zusammen mit der Logdatei als JSON-Datei.</p>
    <form method="post" class="knopfzeile knopfzeile--links">
      <?= csrfFeld() ?>
      <button type="submit" name="tat" value="export" class="knopf"
              data-titel1="Datensicherung erstellen"
              data-frage1="Alle Antragsdaten und die Logdatei werden als JSON-Datei heruntergeladen. Der Export wird im Log vermerkt."
              data-ja1="Herunterladen">Datensicherung herunterladen</button>
    </form>
  </section>

  <section class="karte" aria-labelledby="log-titel">
    <div class="titelzeile">
      <h2 id="log-titel">Logdatei</h2>
      <form method="post" class="knopfzeile">
        <?= csrfFeld() ?>
        <button type="submit" name="tat" value="csv" class="knopf knopf--zweit knopf--klein">Als CSV ausgeben</button>
      </form>
    </div>

    <form class="filterleiste" method="get" role="search">
      <div class="feld">
        <label for="f-suche">Suche</label>
        <input id="f-suche" name="suche" type="search" value="<?= e(get('suche')) ?>" placeholder="Antrag, Person, Aktion">
      </div>
      <div class="feld">
        <label for="f-aktion">Aktion</label>
        <select id="f-aktion" name="aktion" data-auto-absenden>
          <option value="">Alle</option>
          <?php foreach (AKTIONEN as $schluessel => $text): ?>
            <option value="<?= e($schluessel) ?>"<?= $aktion === $schluessel ? ' selected' : '' ?>><?= e($text) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <noscript><button type="submit" class="knopf knopf--zweit knopf--klein">Filtern</button></noscript>
    </form>

    <p class="leise"><?= count($eintraege) ?> Einträge, neueste zuerst.</p>
    <div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th><th scope="col">Zeitpunkt</th><th scope="col">Person</th>
            <th scope="col">Aktion</th><th scope="col">Antrag</th><th scope="col">Bemerkung</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eintraege as $eintrag): ?>
            <tr>
              <td class="zahl"><?= (int) $eintrag['id'] ?></td>
              <td class="zahl"><?= zeitpunkt($eintrag['zeit']) ?></td>
              <td><?= e($eintrag['benutzer_name']) ?></td>
              <td><?= e(AKTIONEN[$eintrag['aktion']] ?? $eintrag['aktion']) ?></td>
              <td><?= $eintrag['antrag_id']
                  ? '<a href="antrag.php?id=' . (int) $eintrag['antrag_id'] . '">' . e(antragNr((int) $eintrag['antrag_id'])) . '</a>'
                  : '–' ?></td>
              <td><?= e($eintrag['bemerkung']) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php
seitenfuss();

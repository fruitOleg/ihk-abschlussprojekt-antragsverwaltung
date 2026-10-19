<?php
declare(strict_types=1);

/*
 * Arbeitsvorrat: alle Anträge, die auf eine Entscheidung des Benutzers warten.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen(['al', 'ssl', 'sl', 'sek']);
$antraege = aufgabenFuer($benutzer);

$teile = [];
if (hatRolle($benutzer, 'al')) {
    $teile[] = 'als Abteilungsleiter (' . implode(', ', $benutzer['abteilungen'])
        . '): Anträge prüfen, abzeichnen, zurückweisen oder umleiten';
}
if (istSchulleitung($benutzer)) {
    $teile[] = 'als Schulleitung: abzeichnen bzw. genehmigen oder ablehnen – stellv. SL und SL vertreten sich gegenseitig';
}
if (hatRolle($benutzer, 'sek')) {
    $teile[] = 'als Sekretariat: alle abgeschlossenen Vorgänge abzeichnen';
}

seitenkopf('Zur Bearbeitung', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div>
    <h1>Zur Bearbeitung</h1>
    <p class="leise">Anträge, die auf Sie warten – <?= e(implode('; ', $teile)) ?>.</p>
  </div>

  <?php if (!$antraege): ?>
    <div class="karte">
      <p class="leer">Zurzeit liegen keine Anträge zur Bearbeitung vor.
        <a href="uebersicht.php">Alle Anträge ansehen</a></p>
    </div>
  <?php else: ?>
    <?php listeAusgeben($antraege, $benutzer, true, 'Bearbeiten') ?>
  <?php endif ?>
</main>
<?php
seitenfuss();

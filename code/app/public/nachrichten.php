<?php
declare(strict_types=1);

/*
 * Benachrichtigungen des angemeldeten Benutzers.
 * Im Betrieb kommen sie zusätzlich per E-Mail bzw. Untis.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen();
$nachrichten = nachrichtenFuer($benutzer);

seitenkopf('Nachrichten', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div>
    <h1>Nachrichten</h1>
    <p class="leise">Hier sammelt das System alle Benachrichtigungen zu Ihren Anträgen und Aufgaben.</p>
  </div>

  <?php if (!$nachrichten): ?>
    <div class="karte"><p class="leer">Keine Nachrichten.</p></div>
  <?php else: ?>
    <ul class="nachrichten">
      <?php foreach ($nachrichten as $nachricht):
          $antrag = $nachricht['antrag_id'] ? antragNachId((int) $nachricht['antrag_id']) : null;
          $ziel = $antrag && darfAnsehen($benutzer, $antrag)
              ? ($antrag['status'] === 'entwurf' ? 'meine-antraege.php?id=' : 'antrag.php?id=') . $antrag['id']
              : null; ?>
        <li class="karte<?= $nachricht['gelesen'] ? '' : ' nachricht--neu' ?>">
          <p><?= $nachricht['gelesen'] ? '' : '<span class="neu">Neu</span> ' ?><?= e($nachricht['text']) ?></p>
          <p class="leise">
            <?= zeitpunkt($nachricht['zeit']) ?>
            <?php if ($ziel): ?>
              · <a href="<?= e($ziel) ?>">Antrag <?= e(antragNr((int) $nachricht['antrag_id'])) ?> öffnen</a>
            <?php endif ?>
          </p>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</main>
<?php
// beim Ansehen gelten alle als gelesen; der Zähler oben wird beim nächsten Seitenaufruf kleiner
nachrichtenGelesen($benutzer);
seitenfuss();

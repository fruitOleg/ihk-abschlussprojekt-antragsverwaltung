<?php
declare(strict_types=1);

/*
 * Übersicht aller Anträge, die der Benutzer sehen darf, mit Filter und Sortierung.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen(['al', 'ssl', 'sl', 'sek']);
$istSek = hatRolle($benutzer, 'sek');
$alle = sichtbarFuer($benutzer);

$status = get('status');
$abteilung = get('abteilung');
$suche = mb_strtolower(get('suche'));
$sortierung = get('sortierung', 'neu');
$mitAusgeblendeten = get('ausgeblendet') === '1';

$sichtbar = array_values(array_filter($alle, function (array $antrag) use ($status, $abteilung, $suche, $mitAusgeblendeten) {
    if ($antrag['ausgeblendet'] && !$mitAusgeblendeten) {
        return false;
    }
    if ($status !== '' && statusGruppe($antrag) !== $status) {
        return false;
    }
    if ($abteilung !== '' && $antrag['abteilung'] !== $abteilung) {
        return false;
    }
    if ($suche !== '') {
        $text = mb_strtolower(antragNr($antrag['id']) . ' ' . $antrag['antragsteller_name'] . ' ' . kurzbeschreibung($antrag));
        if (!str_contains($text, $suche)) {
            return false;
        }
    }
    return true;
}));

$datumVon = fn(array $a) => $a['eingereicht'] ?? $a['erstellt'];
usort($sichtbar, match ($sortierung) {
    'alt'    => fn($a, $b) => $datumVon($a) <=> $datumVon($b),
    'nr'     => fn($a, $b) => $a['id'] <=> $b['id'],
    'name'   => fn($a, $b) => strcoll($a['antragsteller_name'], $b['antragsteller_name']),
    'beginn' => fn($a, $b) => ($a['daten']['beginn_datum'] ?? '') <=> ($b['daten']['beginn_datum'] ?? ''),
    default  => fn($a, $b) => $datumVon($b) <=> $datumVon($a),
});

$anzahl = function (string $gruppe) use ($alle): int {
    return count(array_filter($alle, fn($a) => statusGruppe($a) === $gruppe));
};

seitenkopf('Alle Anträge', $benutzer);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div>
    <h1>Alle Anträge</h1>
    <p class="leise"><?= $istSek || istSchulleitung($benutzer)
        ? 'Alle eingereichten Anträge der Schule mit ihrem Bearbeitungsstand.'
        : 'Alle Anträge Ihrer Abteilung (' . e(implode(', ', $benutzer['abteilungen'])) . ') mit ihrem Bearbeitungsstand.' ?></p>
  </div>

  <form class="filterleiste karte" method="get" role="search">
    <div class="feld">
      <label for="f-status">Status</label>
      <select id="f-status" name="status" data-auto-absenden>
        <option value="">Alle (<?= count($alle) ?>)</option>
        <?php foreach (STATUS_GRUPPEN as $schluessel => $text): ?>
          <option value="<?= e($schluessel) ?>"<?= $status === $schluessel ? ' selected' : '' ?>>
            <?= e($text) ?> (<?= $anzahl($schluessel) ?>)
          </option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="feld">
      <label for="f-abteilung">Abteilung</label>
      <select id="f-abteilung" name="abteilung" data-auto-absenden>
        <option value="">Alle</option>
        <?php foreach (abteilungen() as $kuerzel => $info): ?>
          <option<?= $abteilung === $kuerzel ? ' selected' : '' ?>><?= e($kuerzel) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="feld">
      <label for="f-suche">Suche</label>
      <input id="f-suche" name="suche" type="search" value="<?= e(get('suche')) ?>" placeholder="Nr., Name, Anlass">
    </div>
    <div class="feld">
      <label for="f-sortierung">Sortierung</label>
      <select id="f-sortierung" name="sortierung" data-auto-absenden>
        <?php foreach (['neu' => 'Neueste zuerst', 'alt' => 'Älteste zuerst', 'nr' => 'Nach Nummer',
                        'name' => 'Nach Antragsteller', 'beginn' => 'Nach Beginn der Abwesenheit'] as $wert => $text): ?>
          <option value="<?= $wert ?>"<?= $sortierung === $wert ? ' selected' : '' ?>><?= $text ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <?php if ($istSek): ?>
      <label class="haken"><input type="checkbox" name="ausgeblendet" value="1" data-auto-absenden<?= $mitAusgeblendeten ? ' checked' : '' ?>>
        Ausgeblendete anzeigen</label>
    <?php endif ?>
    <noscript><button type="submit" class="knopf knopf--zweit knopf--klein">Filtern</button></noscript>
  </form>

  <?php if (!$sichtbar): ?>
    <div class="karte"><p class="leer">Keine Anträge für diese Auswahl.</p></div>
  <?php else: ?>
    <?php listeAusgeben($sichtbar, $benutzer, false, 'Ansehen') ?>
  <?php endif ?>
</main>
<?php
seitenfuss();

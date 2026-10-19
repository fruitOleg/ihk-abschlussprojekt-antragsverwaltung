<?php
declare(strict_types=1);

/*
 * Administration: Benutzer anlegen, Rollen und Abteilungen vergeben, Konten deaktivieren.
 */

require __DIR__ . '/../src/bootstrap.php';

$admin = seiteSchuetzen(['admin']);

$bearbeitet = null;
$formularOffen = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();
    try {
        $benutzer = benutzerSpeichern($admin, [
            'id'          => (int) ($_POST['id'] ?? 0),
            'login'       => post('login'),
            'name'        => post('name'),
            'amt'         => post('amt'),
            'aktiv'       => isset($_POST['aktiv']),
            'rollen'      => (array) ($_POST['rollen'] ?? []),
            'abteilungen' => (array) ($_POST['abteilungen'] ?? []),
        ]);
        hinweisMerken('Benutzer „' . $benutzer['login'] . '“ wurde gespeichert.');
        weiterleiten('benutzer.php');
    } catch (EingabeFehler $fehler) {
        hinweisMerken($fehler->getMessage(), 'fehler');
        $formularOffen = true;
        $bearbeitet = [
            'id'          => (int) ($_POST['id'] ?? 0),
            'login'       => post('login'),
            'name'        => post('name'),
            'amt'         => post('amt'),
            'aktiv'       => isset($_POST['aktiv']),
            'rollen'      => (array) ($_POST['rollen'] ?? []),
            'abteilungen' => (array) ($_POST['abteilungen'] ?? []),
        ];
    }
}

if (!$bearbeitet && get('id')) {
    $bearbeitet = benutzerNachId((int) get('id'));
    $formularOffen = (bool) $bearbeitet;
}
if (!$bearbeitet && get('neu') === '1') {
    $bearbeitet = ['id' => 0, 'login' => '', 'name' => '', 'amt' => '', 'aktiv' => true, 'rollen' => ['lehrer'], 'abteilungen' => []];
    $formularOffen = true;
}

seitenkopf('Benutzer', $admin);
?>
<main class="inhalt">
  <?php hinweisAusgeben() ?>
  <div class="titelzeile">
    <div>
      <h1>Benutzer und Rollen</h1>
      <p class="leise">Abteilungsleiter, stellv. Schulleiter und Schulleiter sind zugleich Lehrer.
        Jede Abteilung hat einen oder mehrere Abteilungsleiter.</p>
    </div>
    <a class="knopf" href="benutzer.php?neu=1">Neuer Benutzer</a>
  </div>

  <div class="tabelle-rahmen">
    <table class="tabelle">
      <thead>
        <tr>
          <th scope="col">Benutzer</th><th scope="col">Name</th><th scope="col">Amt</th>
          <th scope="col">Rollen</th><th scope="col">Status</th>
          <th scope="col"><span class="unsichtbar">Aktion</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (alleBenutzer() as $b): ?>
          <tr<?= $b['aktiv'] ? '' : ' class="ausgeblendet"' ?>>
            <td><?= e($b['login']) ?></td>
            <td><?= e($b['name']) ?></td>
            <td><?= e($b['amt'] ?: '–') ?></td>
            <td><?= e(rollenText($b)) ?></td>
            <td><?= $b['aktiv'] ? 'aktiv' : 'deaktiviert' ?></td>
            <td class="rechts">
              <a class="knopf knopf--zweit knopf--klein" href="benutzer.php?id=<?= $b['id'] ?>">Bearbeiten</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <?php if ($formularOffen): ?>
    <form method="post" class="karte" novalidate>
      <?= csrfFeld() ?>
      <input type="hidden" name="id" value="<?= (int) $bearbeitet['id'] ?>">
      <fieldset>
        <legend><?= $bearbeitet['id'] ? 'Benutzer „' . e($bearbeitet['login']) . '“ bearbeiten' : 'Neuer Benutzer' ?></legend>
        <div class="formular-raster">
          <div class="feld">
            <label for="b-login">Benutzername</label>
            <input id="b-login" name="login" type="text" value="<?= e($bearbeitet['login']) ?>" autocomplete="off">
          </div>
          <div class="feld">
            <label for="b-name">Name</label>
            <input id="b-name" name="name" type="text" value="<?= e($bearbeitet['name']) ?>" placeholder="z. B. M. Keller">
          </div>
          <div class="feld">
            <label for="b-amt">Amtsbezeichnung <span class="leise">(optional)</span></label>
            <input id="b-amt" name="amt" type="text" value="<?= e($bearbeitet['amt']) ?>" placeholder="z. B. StR, OStR, StD">
          </div>
          <div class="feld feld--unten">
            <label class="haken"><input type="checkbox" name="aktiv" value="1"<?= $bearbeitet['aktiv'] ? ' checked' : '' ?>> Konto aktiv</label>
          </div>
          <div class="feld feld--breit">
            <span class="feld__titel" id="rollen-titel">Rollen</span>
            <div class="auswahlreihe" role="group" aria-labelledby="rollen-titel">
              <?php foreach (ROLLEN as $rolle => $text): ?>
                <label class="haken">
                  <input type="checkbox" name="rollen[]" value="<?= e($rolle) ?>"<?= in_array($rolle, $bearbeitet['rollen'], true) ? ' checked' : '' ?>>
                  <?= e($text) ?>
                </label>
              <?php endforeach ?>
            </div>
          </div>
          <div class="feld feld--breit">
            <span class="feld__titel" id="abteilungen-titel">Abteilungen (als Abteilungsleiter)</span>
            <div class="auswahlreihe" role="group" aria-labelledby="abteilungen-titel">
              <?php foreach (abteilungen() as $kuerzel => $abteilung): ?>
                <label class="haken" title="<?= e($abteilung['name']) ?>">
                  <input type="checkbox" name="abteilungen[]" value="<?= e($kuerzel) ?>"<?= in_array($kuerzel, $bearbeitet['abteilungen'], true) ? ' checked' : '' ?>>
                  <?= e($kuerzel) ?>
                </label>
              <?php endforeach ?>
            </div>
          </div>
        </div>
        <p class="leise abstand-oben">Neue Konten bekommen das Startpasswort aus config.local.php und sollten es nach der
          ersten Anmeldung ändern.</p>
      </fieldset>
      <div class="knopfzeile knopfzeile--links">
        <button type="submit" class="knopf">Speichern</button>
        <a class="knopf knopf--zweit" href="benutzer.php">Abbrechen</a>
      </div>
    </form>
  <?php endif ?>
</main>
<?php
seitenfuss();

<?php
declare(strict_types=1);

/*
 * Anmeldung. Wer schon angemeldet ist, kommt direkt auf seine Startseite.
 */

require __DIR__ . '/../src/bootstrap.php';

$angemeldet = aktuellerBenutzer();
if ($angemeldet) {
    weiterleiten(startseite($angemeldet));
}

$login = '';
$fehler = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfPruefen();
    $login = post('login');
    $benutzer = anmelden($login, (string) ($_POST['passwort'] ?? ''));

    if ($benutzer) {
        // neue Sitzungskennung nach der Anmeldung (Schutz gegen Session Fixation)
        session_regenerate_id(true);
        $_SESSION['benutzer_id'] = $benutzer['id'];
        weiterleiten(startseite($benutzer));
    }
    // bewusst keine Auskunft darüber, was falsch war
    $fehler = 'Benutzername oder Passwort stimmen nicht, oder das Konto ist deaktiviert.';
}

seitenkopf('Anmeldung', null);
?>
<main class="anmeldung">
  <div class="anmeldung__box">
    <?php hinweisAusgeben() ?>
    <section class="karte">
      <h1>Antragsverwaltung</h1>
      <p class="leise">Änderung der Unterrichtsorganisation</p>
      <form method="post" class="anmeldung__formular">
        <?= csrfFeld() ?>
        <div class="feld">
          <label for="login">Benutzername</label>
          <input id="login" name="login" type="text" value="<?= e($login) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="feld">
          <label for="passwort">Passwort</label>
          <input id="passwort" name="passwort" type="password" autocomplete="current-password" required>
        </div>
        <?php if ($fehler): ?>
          <p class="feld__fehler" role="alert"><?= e($fehler) ?></p>
        <?php endif ?>
        <button type="submit" class="knopf knopf--voll">Anmelden</button>
      </form>
    </section>

    <!-- Testzugänge für die Vorführung; das Passwort wird mündlich mitgeteilt -->
    <section class="karte demo" aria-labelledby="zugaenge-titel">
      <h2 id="zugaenge-titel">Zugänge zum Testen</h2>
      <p class="leise">Benutzername anklicken, um ihn einzutragen. Das Passwort erhalten Sie von der Projektgruppe.</p>
      <div class="tabelle-rahmen">
        <table class="tabelle">
          <thead>
            <tr><th scope="col">Benutzer</th><th scope="col">Name</th><th scope="col">Rolle</th></tr>
          </thead>
          <tbody>
            <?php
            // in der Reihenfolge des Genehmigungswegs: Lehrer, AL, stellv. SL, SL, Sekretariat, Admin
            $rang = fn(array $b) => max(array_map(fn($r) => array_search($r, array_keys(ROLLEN), true), $b['rollen'] ?: ['lehrer']));
            $zugaenge = alleBenutzer();
            usort($zugaenge, fn($a, $b) => [$rang($a), $a['login']] <=> [$rang($b), $b['login']]);
            foreach ($zugaenge as $b): ?>
              <?php if ($b['aktiv']): ?>
                <tr>
                  <td><button type="button" class="link" data-login="<?= e($b['login']) ?>"><?= e($b['login']) ?></button></td>
                  <td><?= e($b['name']) ?></td>
                  <td><?= e(rollenText($b)) ?></td>
                </tr>
              <?php endif ?>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</main>
<?php
seitenfuss();

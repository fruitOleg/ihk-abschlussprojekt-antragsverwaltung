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
  </div>
</main>
<?php
seitenfuss();

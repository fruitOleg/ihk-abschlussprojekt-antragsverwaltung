<?php
declare(strict_types=1);

/*
 * Gemeinsamer Seitenrahmen: Kopf mit Navigation, Hinweise, Pop-ups, Fuß.
 */

// Menüpunkt => [Beschriftung, Rollen, die ihn sehen]
const NAVIGATION = [
    'eingang.php'        => ['Zur Bearbeitung', ['al', 'ssl', 'sl', 'sek']],
    'meine-antraege.php' => ['Meine Anträge',   ['lehrer']],
    'antrag-neu.php'     => ['Antrag stellen',  ['lehrer']],
    'uebersicht.php'     => ['Alle Anträge',    ['al', 'ssl', 'sl', 'sek']],
    'protokoll.php'      => ['Log & Export',    ['sl']],
    'benutzer.php'       => ['Benutzer',        ['admin']],
];

function seitenkopf(string $titel, ?array $benutzer): void
{
    $seite = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titel) ?> – Antragsverwaltung</title>
<link rel="stylesheet" href="css/style.css?v=<?= dateiVersion('css/style.css') ?>">
<script src="js/dialog.js?v=<?= dateiVersion('js/dialog.js') ?>" defer></script>
</head>
<body>
<?php if ($benutzer): ?>
<header class="kopf">
  <div class="kopf__innen">
    <a class="kopf__marke" href="<?= e(startseite($benutzer)) ?>">Antragsverwaltung</a>
    <nav class="kopf__nav" aria-label="Hauptnavigation">
      <?php foreach (NAVIGATION as $ziel => [$text, $rollen]): ?>
        <?php if (hatEineRolle($benutzer, $rollen)): ?>
          <a href="<?= e($ziel) ?>"<?= $ziel === $seite ? ' aria-current="page"' : '' ?>><?= e($text) ?></a>
        <?php endif ?>
      <?php endforeach ?>
      <?php $ungelesen = ungeleseneAnzahl($benutzer); ?>
      <a href="nachrichten.php"<?= $seite === 'nachrichten.php' ? ' aria-current="page"' : '' ?>>Nachrichten<?php
        if ($ungelesen): ?> <span class="zaehler" aria-label="<?= $ungelesen ?> ungelesen"><?= $ungelesen ?></span><?php endif ?></a>
    </nav>
    <div class="kopf__benutzer">
      <span title="<?= e(rollenText($benutzer)) ?>"><?= e($benutzer['name']) ?></span>
      <form method="post" action="abmelden.php">
        <?= csrfFeld() ?>
        <button type="submit" class="knopf knopf--leise">Abmelden</button>
      </form>
    </div>
  </div>
</header>
<?php
    popupsAusgeben($benutzer);
endif;
}

// Änderungszeit als Versionsnummer, damit der Browser nach einer Änderung nicht die alte Datei nimmt
function dateiVersion(string $datei): int
{
    return (int) @filemtime(__DIR__ . '/../public/' . $datei);
}

function hinweisAusgeben(): void
{
    $hinweis = hinweisHolen();
    if (!$hinweis) {
        return;
    }
    $rolle = $hinweis['art'] === 'fehler' ? 'alert' : 'status';
    echo '<div class="hinweis hinweis--' . e($hinweis['art']) . '" role="' . $rolle . '">' . e($hinweis['text']) . '</div>';
}

// wichtige Benachrichtigungen (z. B. Ablehnung durch den SL) erscheinen einmal als Pop-up
function popupsAusgeben(array $benutzer): void
{
    foreach (popupsAbholen($benutzer) as $nachricht) {
        ?>
<dialog class="dialog" data-sofort>
  <form method="dialog">
    <h2 class="dialog__titel">Neue Benachrichtigung</h2>
    <p class="dialog__text"><?= e($nachricht['text']) ?></p>
    <div class="knopfzeile"><button value="ja" class="knopf">Verstanden</button></div>
  </form>
</dialog>
        <?php
    }
}

function statusMarke(array $antrag): string
{
    return '<span class="status status--' . e(statusFarbe($antrag)) . '">' . e(statusText($antrag)) . '</span>';
}

function seitenfuss(): void
{
    echo "</body>\n</html>\n";
}

<?php
declare(strict_types=1);

/*
 * Anlage eines Antrags herunterladen. Die Dateien liegen außerhalb des
 * öffentlichen Ordners und werden nur an Berechtigte ausgeliefert.
 */

require __DIR__ . '/../src/bootstrap.php';

$benutzer = seiteSchuetzen();
$antrag = antragNachId((int) get('id'));

if (!$antrag || !$antrag['anlage_datei'] || !darfAnsehen($benutzer, $antrag)) {
    http_response_code(404);
    exit('Die Anlage wurde nicht gefunden.');
}

$pfad = anlagePfad($antrag['anlage_datei']);
if (!is_file($pfad)) {
    http_response_code(404);
    exit('Die Anlage wurde nicht gefunden.');
}

$typ = (new finfo(FILEINFO_MIME_TYPE))->file($pfad) ?: 'application/octet-stream';
header('Content-Type: ' . $typ);
header('Content-Length: ' . filesize($pfad));
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) $antrag['anlage_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($pfad);

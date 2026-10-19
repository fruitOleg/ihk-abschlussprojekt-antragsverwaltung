<?php
declare(strict_types=1);

/*
 * Kleine Hilfsfunktionen für alle Seiten.
 */

// Text sicher in HTML ausgeben (schützt vor eingeschleustem HTML/JavaScript)
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function weiterleiten(string $ziel): never
{
    header('Location: ' . $ziel);
    exit;
}

function antragNr(int $id): string
{
    return 'A-' . $id;
}

// "2026-09-14" oder "2026-09-14 10:55:00" -> "14.09.2026"
function datum(?string $wert): string
{
    if (!$wert) {
        return '';
    }
    try {
        return (new DateTimeImmutable($wert))->format('d.m.Y');
    } catch (Exception) {
        return $wert;
    }
}

// "2026-09-14 10:55:00" -> "14.09.2026, 10:55"
function zeitpunkt(?string $wert): string
{
    if (!$wert) {
        return '';
    }
    try {
        return (new DateTimeImmutable($wert))->format('d.m.Y, H:i');
    } catch (Exception) {
        return $wert;
    }
}

function gueltigesDatum(string $wert): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $wert);
    return $d !== false && $d->format('Y-m-d') === $wert;
}

/* ---------- Hinweise für die nächste Seite ---------- */

function hinweisMerken(string $text, string $art = 'erfolg'): void
{
    $_SESSION['hinweis'] = ['text' => $text, 'art' => $art];
}

function hinweisHolen(): ?array
{
    $hinweis = $_SESSION['hinweis'] ?? null;
    unset($_SESSION['hinweis']);
    return $hinweis;
}

/* ---------- Schutz gegen gefälschte Formulare (CSRF) ---------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfFeld(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfPruefen(): void
{
    if (!hash_equals(csrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.');
    }
}

/* ---------- Anmeldung ---------- */

function aktuellerBenutzer(): ?array
{
    $id = $_SESSION['benutzer_id'] ?? null;
    $benutzer = $id ? benutzerNachId((int) $id) : null;
    return $benutzer && $benutzer['aktiv'] ? $benutzer : null;
}

function startseite(array $benutzer): string
{
    if (hatEineRolle($benutzer, ['al', 'ssl', 'sl', 'sek'])) {
        return 'eingang.php';
    }
    if (hatRolle($benutzer, 'lehrer')) {
        return 'meine-antraege.php';
    }
    return 'benutzer.php';
}

// Nur angemeldete Benutzer mit einer der Rollen dürfen die Seite sehen
function seiteSchuetzen(?array $rollen = null): array
{
    $benutzer = aktuellerBenutzer();
    if (!$benutzer) {
        weiterleiten('index.php');
    }
    if ($rollen !== null && !hatEineRolle($benutzer, $rollen)) {
        weiterleiten(startseite($benutzer));
    }
    return $benutzer;
}

/* ---------- Eingaben aus dem Formular ---------- */

function post(string $name, string $standard = ''): string
{
    $wert = $_POST[$name] ?? $standard;
    return is_string($wert) ? trim($wert) : $standard;
}

function get(string $name, string $standard = ''): string
{
    $wert = $_GET[$name] ?? $standard;
    return is_string($wert) ? trim($wert) : $standard;
}

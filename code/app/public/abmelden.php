<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Abmelden nur per Formular (POST), damit ein fremder Link niemanden abmelden kann
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    weiterleiten('index.php');
}
csrfPruefen();

$_SESSION = [];
session_regenerate_id(true);

hinweisMerken('Sie wurden abgemeldet.');
weiterleiten('index.php');

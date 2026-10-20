<?php
// Vorlage für die lokalen Zugangsdaten.
//
// Kopieren nach code/config.local.php und die zwei HIER_-Werte ersetzen.
// "start.bat einrichten" erledigt das automatisch mit zufälligen Passwörtern.
// config.local.php steht in .gitignore und darf nie ins Repository.

return [
    'db' => [
        'host' => '127.0.0.1',          // XAMPP und MySQL laufen auf demselben Rechner
        'port' => 3306,                 // Standardport von MySQL/MariaDB in XAMPP
        'name' => 'antragsverwaltung',  // wird von sql/setup.local.sql angelegt
        'user' => 'antrag_app',         // wird von sql/setup.local.sql angelegt
        // dasselbe Passwort wie in sql/setup.local.sql (dort viermal)
        'pass' => 'HIER_DATENBANK_PASSWORT',
    ],
    'app' => [
        'name' => 'Antragsverwaltung',
        // Anmeldepasswort für alle Konten, die install.php und die Benutzerverwaltung anlegen
        'startpasswort' => 'HIER_ANMELDE_PASSWORT',
    ],
];

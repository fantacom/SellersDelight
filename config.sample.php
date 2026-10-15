<?php
// Kopieren Sie diese Datei nach config.php und tragen Sie Ihre Daten ein.
// Die MySQL-Zugangsdaten finden Sie bei IONOS unter: Hosting → Datenbanken.
return [
    'db' => [
        'host' => 'db12345678.hosting-data.io',   // „Hostname“ bei IONOS
        'port' => 3306,
        'name' => 'dbs12345678',                  // „Datenbankname“
        'user' => 'dbu12345678',                  // „Benutzername“
        'pass' => 'HIER-PASSWORT',
    ],

    // Öffentliche Adresse (für Links in E-Mails)
    'base_url' => 'https://www.sellersdelight.de',

    // E-Mail-Versand über PHP mail(). Absender muss eine Adresse Ihrer Domain sein.
    'mail_enabled' => true,
    'mail_from' => 'no-reply@sellersdelight.de',
    'mail_from_name' => "Seller's Delight",

    // Optional: Kontaktadresse für „Sind Sie dieser Anbieter?“ auf Profilseiten
    'contact_email' => 'info@sellersdelight.de',
    // Optional: Benachrichtigung über neue Bewertungen, Einträge und Änderungen
    'admin_notify_email' => '',

    // Nur zur Fehlersuche auf true setzen – zeigt technische Fehlermeldungen an.
    'debug' => false,
];

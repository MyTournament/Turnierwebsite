<?php
/**
 * Mail-Transport-Konfiguration für das Kontaktformular.
 * Tipp: Kopiere diese Datei lokal und halte Zugangsdaten aus dem Repo heraus.
 */
return [
    // transport: sendgrid | mailgun | smtp | mail (Fallback über PHP mail()).
    'transport'      => 'mail',

    // Absender/Empfänger
    'from_email'     => 'kummerkasten@blankiball.de',
    'from_name'      => 'Blankiball Kontaktformular',
    'to_email'       => 'kummerkasten@blankiball.de',

    // SendGrid
    'sendgrid_api_key' => '',

    // Mailgun
    'mailgun_domain'   => '',
    'mailgun_api_key'  => '',

    // SMTP (falls eigener Relay vorhanden)
    'smtp_host'        => '',
    'smtp_port'        => 587,
    'smtp_username'    => '',
    'smtp_password'    => '',
    'smtp_encryption'  => 'tls', // tls | ssl | none
];

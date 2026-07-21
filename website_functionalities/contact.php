<?php
// SICHERHEIT: MUSS vor dem ersten session_start() der Anfrage eingebunden werden.
include_once __DIR__ . '/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

require_once __DIR__ . '/captcha_blanki.php';
require_once __DIR__ . '/mail_transport.php';

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Captcha prüfen
if (!CaptchaBlanki::passed('contact')) {
    $_SESSION['flash_error_contact'] = 'Bitte zuerst das Captcha bestätigen.';
    header('Location: /#kontakt');
    exit;
}

// Basale Validierung
if ($name === '' || $email === '' || $message === '') {
    $_SESSION['flash_error_contact'] = 'Bitte alle Felder ausfüllen.';
    header('Location: /#kontakt');
    exit;
}

// E-Mail grob prüfen, Telefonnummer erlauben (dann keine klassische Mail-Validierung)
$emailValid = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$emailValid && !preg_match('/^[+0-9 ()-]{6,}$/', $email)) {
    $_SESSION['flash_error_contact'] = 'Bitte eine gültige E-Mail-Adresse oder Telefonnummer angeben.';
    header('Location: /#kontakt');
    exit;
}

// Nachricht vorbereiten
$subject = 'Neue Kontaktanfrage (Website)';
$body = "Es gab eine neue Nachricht über das Kontaktformular.\n\n";
$body .= "Name: {$name}\n";
$body .= "Email/Tel: {$email}\n";
$body .= "Nachricht:\n{$message}\n\n";
$body .= "Gesendet am: " . date('Y-m-d H:i:s');

$result = send_contact_message([
    'subject'    => $subject,
    'body_text'  => $body,
    'reply_to'   => $email,
    'reply_name' => $name,
]);

if ($result['success']) {
    header('Location: /#kontakt_success');
    exit;
}

$_SESSION['flash_error_contact'] = 'Senden fehlgeschlagen: ' . ($result['error'] ?? 'Unbekannter Fehler');
header('Location: /#kontakt');
exit;

<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$name = isset($_POST['name']) ? $_POST['name'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';

include_once 'send_mail.php';
require_once __DIR__ . '/captcha_blanki.php';

$isHuman = CaptchaBlanki::validate($_POST);

// ALT: hCaptcha-Validierung (deaktiviert)
/*
$SECRET_KEY = "REDACTED";    # replace with your secret key
$VERIFY_URL = "https://hcaptcha.com/siteverify";
# Retrieve token from post data with key 'h-captcha-response'.
$token = $_POST['h-captcha-response'] ?? '';
# Build payload with secret key and token.
$data = [ 'secret' => $SECRET_KEY, 'response' => $token ];
# Make POST request with data payload to hCaptcha API endpoint.
# ...
# $success = $response_json['success'] ?? false;
*/

if ($isHuman) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'send_message') {
        mail_att("kummerkasten@REDACTED.de", $email, $name . " hat das Kontaktformular benutzt", $message);
    }
}
else {
    // Flash error and attempts left for inline display on the same page
    $token = isset($_POST['cb_token']) ? (string)$_POST['cb_token'] : '';
    $remaining = 0;
    if ($token !== '' && isset($_SESSION['captcha_blanki'][$token]['attempts'])) {
        $att = (int)$_SESSION['captcha_blanki'][$token]['attempts'];
        $remaining = max(0, 3 - $att);
    }
    $_SESSION['flash_error_contact'] = $remaining > 0
        ? "Captcha falsch. Verbleibende Versuche: $remaining"
        : "Captcha fehlgeschlagen. Bitte neu laden und erneut versuchen.";
    header("Location: /#kontakt");
    exit;
}

header("Location: /#kontakt_success");
?>

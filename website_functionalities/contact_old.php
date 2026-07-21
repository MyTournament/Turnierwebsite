<?php
// SICHERHEIT: MUSS vor dem ersten session_start() der Anfrage eingebunden werden.
include_once __DIR__ . '/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$name = isset($_POST['name']) ? $_POST['name'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';

include_once 'send_mail.php';
require_once __DIR__ . '/captcha_blanki.php';

// Nur Absenden erlauben, wenn vorher über den Captcha-Button bestätigt wurde
$isHuman = CaptchaBlanki::passed('contact');

// ALT: hCaptcha-Validierung (deaktiviert)
/*
$hcaptcha_cfg = [];
$hcaptcha_cfg_path = __DIR__ . '/../local_secrets/hcaptcha.local.php';
if (file_exists($hcaptcha_cfg_path)) {
    $hcaptcha_cfg = include $hcaptcha_cfg_path;
}
$SECRET_KEY = $hcaptcha_cfg['secret_key'] ?? '';    # replace with your secret key
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
        mail_att("kummerkasten@blankiball.de", $email, $name . " hat das Kontaktformular benutzt", $message);
    }
}
else {
    // Flash error and attempts left for inline display on the same page
    $_SESSION['flash_error_contact'] = 'Bitte zuerst das Captcha bestätigen.';
    header("Location: /#kontakt");
    exit;
}

header("Location: /#kontakt_success");
?>

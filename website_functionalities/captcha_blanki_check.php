<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/captcha_blanki.php';

try {
    $res = CaptchaBlanki::preverify($_POST);
    $out = [
        'ok' => !empty($res['ok']),
        'remaining' => isset($res['remaining']) ? (int)$res['remaining'] : 0,
        'reload' => !empty($res['reload']),
        'attempts' => isset($res['attempts']) ? (int)$res['attempts'] : 0,
    ];
    $formKey = isset($_POST['cb_formkey']) ? (string)$_POST['cb_formkey'] : 'default';
    if ($out['ok']) {
        $out['message'] = 'Captcha bestätigt. Du kannst jetzt absenden.';
    } else {
        $out['message'] = $out['remaining'] > 0
            ? 'Captcha falsch. Verbleibende Versuche: ' . $out['remaining']
            : 'Captcha fehlgeschlagen. Die Seite wird neu geladen.';
    }
    if ($formKey !== '') {
        $_SESSION['captcha_attempted_' . $formKey] = 1;
        $_SESSION['captcha_remaining_' . $formKey] = $out['remaining'];
        $_SESSION['flash_error_' . $formKey] = $out['message'];
    }
    if ($formKey === 'register') {
        $_SESSION['flash_error_register'] = $out['message'];
    }
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok'=>false,'remaining'=>0,'reload'=>false,'message'=>'Interner Fehler']);
}
?>


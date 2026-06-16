<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/captcha_blanki.php';

if (!CaptchaBlanki::passed('contact')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'captcha_required']);
    exit;
}

echo json_encode([
    'ok' => true,
    'email' => 'kummerkasten@blankiball.de',
]);

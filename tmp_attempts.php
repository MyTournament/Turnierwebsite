<?php
session_start();
require 'website_functionalities/captcha_blanki.php';
ob_start();
CaptchaBlanki::render('register', 4);
ob_end_clean();
$tokens = $_SESSION['captcha_blanki'];
$token = array_key_last($tokens);
function attempt($label, $token, $post) {
    $res = CaptchaBlanki::preverify($post);
    echo $label, ' => ', json_encode($res), "\n";
}
$postBase = [
    'cb_token' => $token,
    'cb_formkey' => 'register',
    'cb_rendered_at' => time() - 5,
];
// Wrong attempt 1 (no selections)
attempt('attempt1', $token, $postBase);
// Wrong attempt 2
attempt('attempt2', $token, $postBase);
// Wrong attempt 3
attempt('attempt3', $token, $postBase);
?>

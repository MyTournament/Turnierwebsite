<?php
session_start();
require 'website_functionalities/captcha_blanki.php';
ob_start();
CaptchaBlanki::render('register', 4);
ob_end_clean();
$tokens = $_SESSION['captcha_blanki'];
$token = array_key_last($tokens);
echo "token=$token\n";
var_export($tokens[$token]);
?>

<?php
$name = $_POST['name'];
$email = $_POST['email'];
$message = $_POST['message'];

include_once 'send_mail.php';

$action = $_POST['action'];
if($action == "send_message"){
    //PER MAIL VERSENDEN
    mail_att("kummerkasten@REDACTED.de", $email, $name." hat das Kontaktformular benutzt", $message);
}

header("Location: /#kontakt_success");
?>
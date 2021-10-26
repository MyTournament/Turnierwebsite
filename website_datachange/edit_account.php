<?php
include_once '../database/db_connection.php';
include_once 'edit_interface.php';

$action = $_POST['action'];
$bn = $_POST['bn'];
$pw = $_POST['pw'];
$fk_rechte = '30';

if($action == 'register'){
    $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort, fk_rechte) VALUES (?, ?, ?)";
    $accountId = myDb_execute($conn, $TurnierID, $bn, $sql, array($bn, $pw, $fk_rechte));
    //accountId könnte jetzt natürlich noch zurück zur index gegeben werden, damit man direkt eingeloggt ist
    //weiß aber leider nicht wie das geht ohne es im Klartext an die uri zu hängen
}

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    header("Location: ../#pausenraum");
}else{
    header("Location: ../#pausenraum?test_turnier_id=$test_turnier_id");
}
?>
<?php
//########################
include_once '../database/db_connection.php';
include_once '../website_functionalities/test_turnier_mode.php'; //Test-Modus
//########################

$NextSec = $_POST["NextSection"];
$TurnierID = $_POST["TurnierID"];
$Passwort = $_POST["turnier_pw"];

// checken ob turnierID und pw zusammenpassen per DB abfrage
$res = $conn->query("SELECT * FROM Turnier_Main WHERE id = '$TurnierID'");
$TurnierLoggedIn = false;
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if ($Passwort == $row["Teilnehmenden_Passwort"]) {
        $TurnierLoggedIn = true;
    }
}

$expiration = time() + 86400; // 24h in Sekunden
setcookie('turnier-loggedin', $TurnierLoggedIn, $expiration, '/');

$conn->close();

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
if($test_turnier_id==0){ //Fall: normales Turnier
    header("Location: /#" . $NextSec);
}else{ //Testturniere
    header("Location: /?test_turnier_id=$test_turnier_id#" . $NextSec);
}

/*$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    $test_turnier_id = $_POST['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#" . $NextSec);
    }
}else{
    header("Location: /?test_turnier_id=$test_turnier_id#" . $NextSec);
}*/

// Perform the redirection
//header("Location: /#" . $NextSec);




exit();
?>

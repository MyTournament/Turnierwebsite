<?php
//########################
include_once '../database/db_connection.php';
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

// Perform the redirection
header("Location: /#" . $NextSec);
exit();
?>

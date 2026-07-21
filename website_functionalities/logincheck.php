<?php
//########################
include_once '../database/db_connection.php';
//########################

// SICHERHEIT: (int)-Cast schliesst SQL-Injection ueber dieses Feld - diese Datei ist der
// oeffentliche, unauthentifizierte Team-Login-Check, also ohne jede Vorbedingung erreichbar.
$TurnierID = (int)$_POST["TurnierID"];

//Anmeldung
$Benutzername = $_POST["bn"];
$Passwort = $_POST["pw"];

$LoggedIn = False;
foreach ($conn->query("SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID'") as $row) {
    if ($Benutzername == $row["kuerzel"] && $Passwort == $row["password"]) {
        $LoggedIn = True;
    }
}
//Gucken ob es in der Warteliste ist
    $warteliste_ID = 6; //Auffangbecken
    $sqlWarteliste = 'SELECT * FROM `Turnier_Warteliste` WHERE fk_turnier = '. $TurnierID .' ORDER BY ID';
    $resultWarteliste = $conn->query($sqlWarteliste);
    while ($rowWarteliste = $resultWarteliste->fetch_assoc()) {
        $warteliste_ID = $rowWarteliste['id'];
    }
    foreach ($conn->query("SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_warteliste = '$warteliste_ID'") as $row) {
        if ($Benutzername == $row["kuerzel"] && $Passwort == $row["password"]) {
            $LoggedIn = True;
        }
    }

    
if ($LoggedIn) {
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#logincheck_success");   
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#logincheck_success");  
    }	
}else{
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#logincheck_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#logincheck_failure");
    }
}
$conn->close();
?>           
                
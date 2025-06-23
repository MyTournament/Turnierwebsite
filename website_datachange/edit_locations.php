<?php
include_once '../database/db_connection.php';
include_once 'edit_interface.php';

//LOGIN
$bn = $_POST['bn'];
$pw = $_POST['pw'];
$TurnierID = $_POST['TurnierID'];
$successfulLogin = 0; //false
  
//FALL: Account-Login -> Bearbeitungsrechte für alle Begegnungen

//VERALTET: MÜSSTE AN NEUES LOGIN_INTERFACE.PHP ANGEPASST WERDEN

/*$sqlLoginAccount = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' AND fk_rechte <= 30 ORDER BY ID"; //
$resultLoginAccount = $conn->query($sqlLoginAccount);
while ( !empty( $rowLoginAccount = $resultLoginAccount->fetch_assoc() ) ){
    $successfulLogin = 1;
}*/
/*
if($successfulLogin == 1){
    $action = $_POST['action'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $accountId = $_POST['accountId'];

    if($action == 'new_rating'){
        $sterne = $_POST['sterne'];
        $fk_location = $_POST['fk_location'];
        $location_name = $_POST['location_name'];

        $sql = "INSERT INTO Pausenraum_Location_Bewertung (name, description, sterne, fk_location, autor) VALUES (?, ?, ?, ?, ?)";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_locations.php",$sql, array($name, $description, $sterne, $fk_location, $accountId));

        //STATISTIK
        $typeId = 2;
        $add_text = "für $location_name: $sterne &#9733;";
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type, add_text) VALUES (?, ?, ?)";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_locations.php 2",$sql, array($accountId, $typeId, $add_text));
    }

    if($action == 'new_location'){
        echo "<script>console.log($accountId)</script>";
        $sql = "INSERT INTO Pausenraum_Location (name, description, autor) VALUES (?, ?, ?)";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_locations.php 3",$sql, array($name, $description, $accountId));

        //STATISTIK
        $typeId = 6;
        $add_text = ": $name";
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type, add_text) VALUES (?, ?, ?)";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_locations.php 4",$sql, array($accountId, $typeId, $add_text));
    }

    header("Location: ../#bierball_locations");
}else{
    header("Location: ../#bierball_locations_bewertungen_hinzufuegen_failure");
}
*/
?>
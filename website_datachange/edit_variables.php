<?php
header("Location: /#login");

//########################
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
//########################

$TurnierID = $_POST['TurnierID'];
//LOGIN
$bn = $_POST['bn'];
$pw = $_POST['pw'];
$sqlLogin = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' ORDER BY ID";
$resultLogin = $conn->query($sqlLogin);
$successfulLogin = 0; //false
while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){
    $successfulLogin = 1;
    $rechte = $rowLogin['fk_rechte'];
}
if ($successfulLogin == 0){ //fehlerhafter Login
  $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
  echo "<script type='text/javascript'>alert('$message');</script>";
}else{
    $action = $_POST['action'];
    echo "<script>console.log('Action: $action')</script>";

    if ($action == 'Tunierphase ändern') {
      if($rechte == 1){ //Aktuell nur für Rechtegruppe 1 zugelassen
        //Variablen speichern
        $phaseID = $_POST['Phase'];
        echo "<script>console.log('Neue Phase: $phaseID')</script>";

        $sql = "UPDATE `Turnier_Main` SET `fk_turnier_phase` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php",$sql, array($phaseID, $TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um diese Variable zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";

        $content = "<h5 style='color: red'>Gescheitert wegen fehlenden Rechten:</h5> UPDATE `Turnier_Main` SET `fk_turnier_phase` = '$phaseID' WHERE `id` = '$TurnierID';";
        $sql = "INSERT INTO System_Data_DB_Verlauf (fk_who, content) VALUES (?, ?)";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 2",$sql, array($bn, $content));
      }
      
      
    }else if ($action == 'Abbrechen'){
      // Nix tun
    }
    else{
    }
}
?> 

<?php
//########################
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
//########################

$TurnierID = $_POST['TurnierID'];

//LOGIN
include_once 'login_interface.php';
$bn = $_POST['bn'];
$pw = $_POST['pw'];

//Benutzer
$benutzerliste = getBenutzerListe($conn);
$successfulLogin = 0; //false
while ($row = $benutzerliste->fetch_assoc()) {
  if(
    $row['Benutzername'] == $bn and
    $row['Passwort'] == $pw
  ){
    $successfulLogin = 1;
    $rechte = $row['fk_rechte'];
  }
}



$action = isset($_POST['action']) ? $_POST['action'] : null;

if ($successfulLogin == 0){ //fehlerhafter Login
  $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
  echo "<script type='text/javascript'>alert('$message');</script>";
}else{
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
      
      
    }else if ($action == 'Turnier_Settings_Aendern') {
      if($rechte == 1 || $rechte == 2){ //Admin oder Co-Admin
        $anzahlGruppen = (int)$_POST['anzahl_gruppen'];
        $startKoFinallevel = (int)$_POST['start_ko_finallevel'];
        $einzugKoManuellAnlegen = isset($_POST['einzug_ko_manuell_anlegen']) ? 1 : 0;

        $sql = "UPDATE `Turnier_Main` SET `anzahl_gruppen` = ?, `start_ko_finallevel` = ?, `einzug_ko_manuell_anlegen` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 3", $sql, array($anzahlGruppen, $startKoFinallevel, $einzugKoManuellAnlegen, $TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";

        $content = "<h5 style='color: red'>Gescheitert wegen fehlenden Rechten:</h5> UPDATE `Turnier_Main` SET anzahl_gruppen/start_ko_finallevel/einzug_ko_manuell_anlegen WHERE `id` = '$TurnierID';";
        $sql = "INSERT INTO System_Data_DB_Verlauf (fk_who, content) VALUES (?, ?)";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 4",$sql, array($bn, $content));
      }

    }else if ($action == 'Einzug_KO_Fertig_Umschalten') {
      if($rechte == 1 || $rechte == 2){ //Admin oder Co-Admin
        $sql = "UPDATE `Turnier_Main` SET `einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei` = CASE WHEN `einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei` = 1 THEN 0 ELSE 1 END WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 5", $sql, array($TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um die Gruppenphase für beendet zu erklären. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Abbrechen'){
      // Nix tun
    }
    else{
    }
}

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$rueckAnker = ($action == 'Einzug_KO_Fertig_Umschalten') ? 'kophase' : 'backstage_daten_bearbeiten';
$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    header("Location: /#$rueckAnker");
}else{
    header("Location: /?test_turnier_id=$test_turnier_id#$rueckAnker");
}
?>

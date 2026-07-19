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

//Benutzer - Rollen-System: alle strukturellen Turnier-Einstellungen brauchen mindestens Co-Admin,
//die Turnierphase (inkl. "Turnier abgeschlossen") ausschließlich Admin
$rollenInfoVariables = getUserRollenInfo($conn, $bn, $pw);
$successfulLogin = ($rollenInfoVariables !== null) ? 1 : 0;
$istAdminVariables = $rollenInfoVariables !== null && $rollenInfoVariables['ist_admin'];
$istAdminOderCoAdminVariables = $rollenInfoVariables !== null && ($rollenInfoVariables['ist_admin'] || $rollenInfoVariables['ist_co_admin']);

$action = isset($_POST['action']) ? $_POST['action'] : null;

if ($successfulLogin == 0){ //fehlerhafter Login
  $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
  echo "<script type='text/javascript'>alert('$message');</script>";
}else{
    echo "<script>console.log('Action: $action')</script>";

    if ($action == 'Tunierphase ändern') {
      if($istAdminVariables){ //Nur Admin
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

    }else if ($action == 'Turnier_Abschliessen') {
      if($istAdminOderCoAdminVariables){
        // Phase 9 = "Turnier vorbei" (siehe Turnier_Setting_Phasen)
        $sql = "UPDATE `Turnier_Main` SET `fk_turnier_phase` = 9 WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 6", $sql, array($TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um das Turnier abzuschließen. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_AnzahlGruppen_Aendern') {
      if($istAdminOderCoAdminVariables){
        $anzahlGruppen = (int)$_POST['anzahl_gruppen'];
        $sql = "UPDATE `Turnier_Main` SET `anzahl_gruppen` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 3", $sql, array($anzahlGruppen, $TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_StartKoFinallevel_Aendern') {
      if($istAdminOderCoAdminVariables){
        $startKoFinallevel = (int)$_POST['start_ko_finallevel'];
        $sql = "UPDATE `Turnier_Main` SET `start_ko_finallevel` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 7", $sql, array($startKoFinallevel, $TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_EinzugKoManuell_Aendern') {
      if($istAdminOderCoAdminVariables){
        $einzugKoManuellAnlegen = isset($_POST['einzug_ko_manuell_anlegen']) ? 1 : 0;
        $sql = "UPDATE `Turnier_Main` SET `einzug_ko_manuell_anlegen` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 8", $sql, array($einzugKoManuellAnlegen, $TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Einzug_KO_Fertig_Umschalten') {
      if($istAdminOderCoAdminVariables){
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
$rueckAnkerMap = [
    'Einzug_KO_Fertig_Umschalten' => 'kophase',
    'Turnier_Abschliessen' => 'kophase',
];
$rueckAnker = $rueckAnkerMap[$action] ?? 'backstage_daten_bearbeiten';
$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    header("Location: /#$rueckAnker");
}else{
    header("Location: /?test_turnier_id=$test_turnier_id#$rueckAnker");
}
?>

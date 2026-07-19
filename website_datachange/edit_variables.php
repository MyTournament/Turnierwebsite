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

    }else if ($action == 'Turnier_Neu_Anlegen') {
      if($istAdminOderCoAdminVariables){
        // Aktuelle Turnier-Zeile komplett laden und als Basis für die Kopie nutzen - so ist die
        // Kopie unabhängig davon, ob wir hier jede einzelne Spalte kennen.
        $stmtAlt = $conn->prepare("SELECT * FROM Turnier_Main WHERE id = ?");
        $stmtAlt->bind_param("i", $TurnierID);
        $stmtAlt->execute();
        $alteZeile = $stmtAlt->get_result()->fetch_assoc();

        if ($alteZeile !== null) {
          unset($alteZeile['id']); // AUTO_INCREMENT - neue ID wird beim Insert vergeben

          // Werte aus dem Formular übernehmen, wo vorhanden
          $textFelder = ['name', 'anzeige_titel', 'anzeige_subtitel', 'anzeige_datum', 'jahr',
              'startdatum', 'startzeit', 'countdown_start', 'enddatum', 'max_anzahl_teams',
              'teilnahmebeitrag', 'order_on_website', 'fk_turnier_phase', 'anzahl_gruppen',
              'start_ko_finallevel', 'excel_link'];
          foreach ($textFelder as $feld) {
            if (isset($_POST[$feld]) && array_key_exists($feld, $alteZeile)) {
              $alteZeile[$feld] = $_POST[$feld];
            }
          }
          // Checkboxen: nicht gesendet = 0
          $checkboxFelder = ['einzug_ko_manuell_anlegen', 'einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei',
              'nurOberesDreieckInGruppenphase', 'loescheErsteZeileUndSpalte', 'losingbracket_open_for_ko_losers',
              'use_excel', 'schnee'];
          foreach ($checkboxFelder as $feld) {
            if (array_key_exists($feld, $alteZeile)) {
              $alteZeile[$feld] = isset($_POST[$feld]) ? 1 : 0;
            }
          }
          // Systemfelder fix: neue Kopie wird das reale Turnier auf dieser Website
          if (array_key_exists('type', $alteZeile)) { $alteZeile['type'] = 1; }
          if (array_key_exists('fk_website', $alteZeile)) { $alteZeile['fk_website'] = 1; }

          $spalten = array_keys($alteZeile);
          $platzhalter = implode(',', array_fill(0, count($spalten), '?'));
          $spaltenListeSql = implode(',', array_map(function($s) { return "`$s`"; }, $spalten));
          $sqlNeu = "INSERT INTO Turnier_Main ($spaltenListeSql) VALUES ($platzhalter)";
          $neueTurnierId = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Turnier_Neu_Anlegen", $sqlNeu, array_values($alteZeile));

          // Bisheriges Turnier wird zu "History" (type = 3)
          $sqlHistory = "UPDATE Turnier_Main SET type = 3 WHERE id = ?";
          myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Turnier_Neu_Anlegen history", $sqlHistory, array($TurnierID));
        }
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um ein neues Turnier anzulegen. Wende dich an Richard, um mehr Rechte zu erhalten.";
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

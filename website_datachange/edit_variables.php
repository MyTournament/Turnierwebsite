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

// ============================================================================================
// RECHTE-AUDIT: ALLE TURNIER-EINSTELLUNGEN (inkl. TURNIERPHASE) NUR NOCH ÜBER "turnier_settings"-FLAG
// ============================================================================================
// Vorher war die Turnierphase Admin-only (per Rollen-Identität) und der Rest Admin/Co-Admin-only.
// Laut Nutzer gehört Turnierphase inhaltlich mit zu "Turniersettings bearbeiten" - alles hier hängt
// jetzt einheitlich am rechte_turnier_settings-Flag, kein Admin/Co-Admin-Shortcut mehr. Admin und
// Co-Admin haben dieses Flag in der Rollentabelle ohnehin gesetzt und bleiben damit berechtigt.
$rollenInfoVariables = getUserRollenInfo($conn, $bn, $pw);
$successfulLogin = ($rollenInfoVariables !== null) ? 1 : 0;
$rechteFlagsVariables = $rollenInfoVariables['flags'] ?? array_fill_keys(['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'], false);
$darfTurnierSettingsAendern = $rollenInfoVariables !== null && $rechteFlagsVariables['turnier_settings'];

$action = isset($_POST['action']) ? $_POST['action'] : null;

if ($successfulLogin == 0){ //fehlerhafter Login
  $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
  echo "<script type='text/javascript'>alert('$message');</script>";
}else{
    echo "<script>console.log('Action: $action')</script>";

    if ($action == 'Tunierphase ändern') {
      if($darfTurnierSettingsAendern){ //turnier_settings-Flag (z.B. Admin, Co-Admin)
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
      if($darfTurnierSettingsAendern){
        // Phase 9 = "Turnier vorbei" (siehe Turnier_Setting_Phasen)
        $sql = "UPDATE `Turnier_Main` SET `fk_turnier_phase` = 9 WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 6", $sql, array($TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um das Turnier abzuschließen. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_AnzahlGruppen_Aendern') {
      if($darfTurnierSettingsAendern){
        $anzahlGruppen = (int)$_POST['anzahl_gruppen'];
        $sql = "UPDATE `Turnier_Main` SET `anzahl_gruppen` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 3", $sql, array($anzahlGruppen, $TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_StartKoFinallevel_Aendern') {
      if($darfTurnierSettingsAendern){
        $startKoFinallevel = (int)$_POST['start_ko_finallevel'];
        $sql = "UPDATE `Turnier_Main` SET `start_ko_finallevel` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 7", $sql, array($startKoFinallevel, $TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Turnier_Settings_EinzugKoManuell_Aendern') {
      if($darfTurnierSettingsAendern){
        $einzugKoManuellAnlegen = isset($_POST['einzug_ko_manuell_anlegen']) ? 1 : 0;
        $sql = "UPDATE `Turnier_Main` SET `einzug_ko_manuell_anlegen` = ? WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 8", $sql, array($einzugKoManuellAnlegen, $TurnierID));
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    // ============================================================================================
    // TURNIER SETTINGS ERWEITERUNG: GENERISCHE AKTION STATT ~20 EINZELNER AKTIONEN
    // ============================================================================================
    // Damit nicht für jedes der ~20 weiteren Turnier_Main-Felder eine eigene Aktion dupliziert
    // werden muss, wird das zu ändernde Feld über einen fest verdrahteten Whitelist-Namen (nicht
    // direkt aus der DB) ausgewählt - $feld landet NUR dann in der SQL-Query, wenn es exakt in einer
    // der beiden Whitelists steht, ein SQL-Injection-Risiko über den Spaltennamen besteht also nicht.
    }else if ($action == 'Turnier_Settings_Feld_Aendern') {
      if($darfTurnierSettingsAendern){
        $erlaubteTextFelder = ['name', 'anzeige_titel', 'anzeige_subtitel', 'anzeige_datum', 'jahr',
            'startdatum', 'startzeit', 'countdown_start', 'enddatum', 'max_anzahl_teams',
            'teilnahmebeitrag', 'order_on_website', 'fk_turnier_phase', 'excel_link'];
        $erlaubteCheckboxFelder = ['nurOberesDreieckInGruppenphase', 'loescheErsteZeileUndSpalte',
            'losingbracket_open_for_ko_losers', 'use_excel', 'schnee'];
        $feld = isset($_POST['feld']) ? $_POST['feld'] : '';
        if (in_array($feld, $erlaubteTextFelder, true)) {
          $wert = isset($_POST['wert']) ? $_POST['wert'] : '';
          $sql = "UPDATE `Turnier_Main` SET `$feld` = ? WHERE `id` = ?;";
          $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Feld_Aendern text", $sql, array($wert, $TurnierID));
        } else if (in_array($feld, $erlaubteCheckboxFelder, true)) {
          $wert = isset($_POST['wert']) ? 1 : 0;
          $sql = "UPDATE `Turnier_Main` SET `$feld` = ? WHERE `id` = ?;";
          $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Feld_Aendern checkbox", $sql, array($wert, $TurnierID));
        }
      }else{
        $message = "Leider hast du nicht die nötigen Rechte, um die Turnier Settings zu bearbeiten. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    }else if ($action == 'Einzug_KO_Fertig_Umschalten') {
      if($darfTurnierSettingsAendern){
        $sql = "UPDATE `Turnier_Main` SET `einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei` = CASE WHEN `einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei` = 1 THEN 0 ELSE 1 END WHERE `id` = ?;";
        $insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php 5", $sql, array($TurnierID));
      }else{ //Nicht genug Rechte
        $message = "Leider hast du nicht die nötigen Rechte, um die Gruppenphase für beendet zu erklären. Wende dich an Richard, um mehr Rechte zu erhalten.";
        echo "<script type='text/javascript'>alert('$message');</script>";
      }

    // ============================================================================================
    // NEUES TURNIER ANLEGEN: SCHEMA-UNABHÄNGIGES KOPIEREN ÜBER SELECT * STATT HARDCODED SPALTENLISTE
    // ============================================================================================
    // Statt jede Turnier_Main-Spalte einzeln aufzuzählen, wird die komplette alte Zeile per SELECT *
    // geladen, die vom Formular übergebenen Felder werden darin überschrieben, und daraus wird
    // dynamisch (array_keys/array_values) ein passendes INSERT gebaut. Dadurch bleibt die Kopie auch
    // dann korrekt, wenn Turnier_Main später um weitere Spalten erweitert wird. type=2 (Testturnier)
    // lässt das aktuelle Turnier unangetastet, type=1 (reales Turnier) setzt das alte Turnier auf
    // type=3 (History).
    }else if ($action == 'Turnier_Neu_Anlegen') {
      if($darfTurnierSettingsAendern){
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
          // Typ: 1 = reales Turnier (löst das aktuelle ab), 2 = Testturnier (aktuelles bleibt unangetastet)
          $neuerTurnierTyp = isset($_POST['neuer_turnier_type']) ? (int)$_POST['neuer_turnier_type'] : 1;
          if ($neuerTurnierTyp !== 1 && $neuerTurnierTyp !== 2) { $neuerTurnierTyp = 1; }
          if (array_key_exists('type', $alteZeile)) { $alteZeile['type'] = $neuerTurnierTyp; }
          if (array_key_exists('fk_website', $alteZeile)) { $alteZeile['fk_website'] = 1; }

          $spalten = array_keys($alteZeile);
          $platzhalter = implode(',', array_fill(0, count($spalten), '?'));
          $spaltenListeSql = implode(',', array_map(function($s) { return "`$s`"; }, $spalten));
          $sqlNeu = "INSERT INTO Turnier_Main ($spaltenListeSql) VALUES ($platzhalter)";
          $neueTurnierId = myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Turnier_Neu_Anlegen", $sqlNeu, array_values($alteZeile));

          if ($neuerTurnierTyp === 1) {
            // Bisheriges Turnier wird zu "History" (type = 3) - nur beim Anlegen eines realen Turniers,
            // ein Testturnier darf das aktuelle Turnier nicht verändern.
            $sqlHistory = "UPDATE Turnier_Main SET type = 3 WHERE id = ?";
            myDb_execute($conn, $TurnierID, $bn, "edit_variables.php Turnier_Neu_Anlegen history", $sqlHistory, array($TurnierID));
          }
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
if ($action == 'Turnier_Neu_Anlegen' && isset($neuerTurnierTyp) && $neuerTurnierTyp === 2) {
    // Neu angelegtes Testturnier hat immer die höchste id unter type=2 und landet damit in
    // variables.php (ORDER BY id DESC, Index ab 1) automatisch auf test_turnier_id = 1.
    header("Location: /?test_turnier_id=1#backstage_daten_bearbeiten");
    exit;
}

$rueckAnkerMap = [
    'Einzug_KO_Fertig_Umschalten' => 'kophase',
    'Turnier_Abschliessen' => 'kophase',
    'Turnier_Settings_AnzahlGruppen_Aendern' => 'backstage_turnier_settings',
    'Turnier_Settings_StartKoFinallevel_Aendern' => 'backstage_turnier_settings',
    'Turnier_Settings_EinzugKoManuell_Aendern' => 'backstage_turnier_settings',
    'Turnier_Settings_Feld_Aendern' => 'backstage_turnier_settings',
    'Tunierphase ändern' => 'backstage_turnier_phase',
];
$rueckAnker = $rueckAnkerMap[$action] ?? 'backstage_daten_bearbeiten';
$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    header("Location: /#$rueckAnker");
}else{
    header("Location: /?test_turnier_id=$test_turnier_id#$rueckAnker");
}
?>

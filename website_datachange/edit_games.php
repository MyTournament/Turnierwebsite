<?php
//##########################################################
include_once '../database/db_connection.php';
include_once '../website_datachange/edit_interface.php';
//##########################################################

ini_set('display_errors', 1);
error_reporting(E_ALL);

ob_start();
echo "<script>console.log('Testausgabe 1')</script>";

//Variablen speichern
$TurnierID = $_POST['TurnierID'];
//echo "<script>console.log('TurnierID: $TurnierID')</script>";

$begegnungId = $_POST['begegnungId'];
//echo "<script>console.log('begegnungId: $begegnungId')</script>";
$spielID = $_POST['spielId'];
//echo "<script>console.log('Id des Spiels das bearbeitet oder gelöscht wird wird: $spielID')</script>";
$flaschen1 = $_POST['Flaschen1'];
$flaschen2 = $_POST['Flaschen2'];
$action = $_POST['action'];
$finalizeAfterEntry = isset($_POST['finalize_after_entry']) ? 1 : 0;
//echo "<script>console.log('Action: $action')</script>";


//LOGIN
include_once 'login_interface.php';
$bn = $_POST['bn'];
$pw = $_POST['pw'];

// ============================================================================
// RECHTE-AUDIT: NUR NOCH ÜBER EINZELNE FLAGS, KEIN ADMIN/CO-ADMIN-SHORTCUT MEHR
// ============================================================================
// Beliebige (auch fremde) Spiele bearbeiten darf ausschließlich, wer das Flag
// "rechte_alle_spiele" hat (Schiedsrichter*in). Admin/Co-Admin haben dieses Flag
// in der Rollentabelle ohnehin direkt gesetzt - ein zusätzlicher Admin/Co-Admin-
// Shortcut hier würde das Recht faktisch von der Rolle statt vom Flag abhängig
// machen, was der Nutzer ausdrücklich nicht mehr will.
// $istAdminOderCoAdminEditGames bleibt separat bestehen, weil "Begegnung anlegen"
// und "Begegnung sperren" laut expliziter Vorgabe weiterhin Admin/Co-Admin-only
// bleiben sollen (siehe weiter unten bei Begegnung_Hinzufuegen/Begegnung_Sperren).
$accountDarfSpieleBearbeiten = 0; //false
$istAdminOderCoAdminEditGames = false; //nur Admin/Co-Admin dürfen Begegnungen anlegen/sperren
$rollenInfoGames = getUserRollenInfo($conn, $bn, $pw);
if ($rollenInfoGames !== null) {
  $istAdminOderCoAdminEditGames = ($rollenInfoGames['ist_admin'] || $rollenInfoGames['ist_co_admin']);
  if ($rollenInfoGames['flags']['alle_spiele']) {
    $accountDarfSpieleBearbeiten = 1;
  }
}
//Teams
$teamListeFuerTurnier = getTeamsListeFuerTurnier($conn, $TurnierID);
$successfulTeamLogin = 0; //false
while ($row = $teamListeFuerTurnier->fetch_assoc()) {
  if(
    $row['kuerzel'] == $bn and
    $row['password'] == $pw and
    $row['bearbeitungsrechte'] == 1
  ){
    $successfulTeamLogin = 1;
  }
}


echo "<script>console.log('Testausgabe 2')</script>";

//Herausfinden ob Spiel zu Team gehört
$sql = "SELECT * FROM Turnier_Team, Turnier_Begegnung 
        WHERE Turnier_Team.geloescht = 0 
          AND Turnier_Begegnung.id = ? 
          AND (
              Turnier_Begegnung.fk_heimteam IN (
                  SELECT id FROM Turnier_Team WHERE geloescht = 0 AND kuerzel = ? AND `password` = ?
              ) 
              OR 
              Turnier_Begegnung.fk_auswaertsteam IN (
                  SELECT id FROM Turnier_Team WHERE geloescht = 0 AND kuerzel = ? AND `password` = ?
              )
          )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $begegnungId, $bn, $pw, $bn, $pw);
$stmt->execute();
$result = $stmt->get_result();
$spielGehoertZuTeam = 0;
while ($row = $result->fetch_assoc()) {
    $spielGehoertZuTeam = 1;
    //echo "<script>console.log('Das Spiel gehört zu deinem Team. Das ist gut.')</script>";
}
$stmt->close();

echo "<script>console.log('accountDarfSpieleBearbeiten=$accountDarfSpieleBearbeiten, successfulTeamLogin=$successfulTeamLogin, spielGehoertZuTeam=$spielGehoertZuTeam')</script>";

echo "<script>console.log('Testausgabe 3')</script>";


//##########################################################
//LOGIN
/*include_once 'login_interface_new_rights_system.php';
$rights = get_rights_of_user($conn, $TurnierID, $bn, $pw, $begegnungId);



$successfulLogin = $rights["team_rights"]["successfulLogin"];
$spielGehoertZuTeam = $rights["team_rights"]["spielGehoertZuTeam"];
$teamBearbeitungsrecht = $rights["team_rights"]["teamBearbeitungsrecht"];

$accountDarfSpieleBearbeiten = $rights["account_rights"]["rechte_alle_spiele"];
//##########################################################
*/
if ($action == 'Ändern') {
  if($accountDarfSpieleBearbeiten == 1 || ($successfulTeamLogin == 1 && $spielGehoertZuTeam == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    $sql = "UPDATE `Turnier_Spiel` SET `biereheimteam` = ?, `biereauswaertsteam` = ? WHERE `Turnier_Spiel`.`id` = ?;";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php", $sql, array($flaschen1, $flaschen2, $spielID));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde erfolgreich geändert. $successfulLogin $spielGehoertZuTeam')</script>";
  }else{ //Team-Login

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
      //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
      $test_turnier_id = $_GET['test_turnier_id'];
      if($test_turnier_id==NULL){
          header("Location: /#edit_games_failure");
          exit;
      }else{
          header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
          exit;
      }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde nicht geändert.')</script>";
    $message = "Leider gehört das Spiel, das du bearbeiten möchtest, nicht zu deinem Team. Falls du es bearbeiten möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if ($action == 'Löschen'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $sql = "DELETE FROM `Turnier_Spiel` WHERE `Turnier_Spiel`.`id` = ?;";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 2", $sql, array($spielID));

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde erfolgreich gelöscht.')</script>";
  }else{ //Team-Login

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
          header("Location: /#edit_games_failure");
          exit;
      }else{
          header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
          exit;
      }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde nicht gelöscht..')</script>";
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um  einen Spielstand zu löschen.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Eintragen'){
  if($accountDarfSpieleBearbeiten == 1 || ($successfulTeamLogin == 1 && $spielGehoertZuTeam == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    echo "<script>console.log('Testausgabe 4')</script>";
    
    $sqlInsertSpiel = "INSERT INTO Turnier_Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?)";
    $insertParams = array(
      'conn_set' => isset($conn),
      'TurnierID' => $TurnierID,
      'bn' => $bn,
      'ort' => 'edit_games.php 3',
      'sql' => $sqlInsertSpiel,
      'begegnungId' => $begegnungId,
      'Flaschen1' => $flaschen1,
      'Flaschen2' => $flaschen2
    );
    echo "<script>console.log('SQL insert params', " . json_encode($insertParams) . ");</script>";
    echo "<script>console.log('before myDb_execute insert')</script>";

    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 3",$sqlInsertSpiel, array($begegnungId, $_POST['Flaschen1'], $_POST['Flaschen2'], $bn));
    echo "<script>console.log('after myDb_execute insert')</script>";
    
    echo "<script>console.log('Testausgabe 5')</script>";

    // Direkt finalisieren, falls gewuenscht
    if ($finalizeAfterEntry) {
      $sqlFinalizeAfterInsert = "UPDATE Turnier_Begegnung SET `status` = CASE WHEN `status` = 4 THEN 7 ELSE 5 END WHERE id = ?";
      myDb_execute($conn, $TurnierID, $bn, "edit_games.php finalize_after_insert", $sqlFinalizeAfterInsert, array($begegnungId));
    }

    
    //TODO: Für KO-Phase alle Begegnungen mit 3 Spielen als final markieren
    //-final wird nie wieder als unnötig markiert #done (wird einfach ganz oben nicht als veraltet markiert) - TODO: trotzdem Fall mitbedenken dass Admin ein final-Spiel in Achtel löscht, dann müssten auch Finalspiele in höherer Ebene die darauf folgen gelöscht werden.
    //-ab finaler Begegnung kann auch kein Spiel mehr dazu eingetragen werden
    //-final kann nur noch von Admins gelöscht oder geändert werden 
    //-erst wenn Begegnung final, wird nächste Finalstufe berechnet
    //-bis halbe stunde nach eintragen noch ändern können

    $sqlGetNurOberesDreieckInGruppenphase = "SELECT nurOberesDreieckInGruppenphase FROM Turnier_Main WHERE id = ?";
    $stmt = myDb_execute($conn, $TurnierID, $bn, "edit_games.php 4",$sqlGetNurOberesDreieckInGruppenphase, array($TurnierID));
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nurOberesDreieck = $row['nurOberesDreieckInGruppenphase'];

    if ($nurOberesDreieck === 2){
      // Aktuelle Begegnung auf Status 5 setzen, wenn diese Begegnung eine Gruppenbegegnung ist und jetzt genau ein Spiel hat. (Begegnungen mit 2 oder mehr Spielen werden also nicht verändert, hier wird von bewusstem Handeln von Admins ausgegangen)
      $sqlFinalizeBegegnung = "UPDATE Turnier_Begegnung AS begegnung SET `status` = CASE WHEN begegnung.`status` = 4 THEN 7 ELSE 5 END WHERE id = ? AND ko_finallevel = 0 AND 1 = (SELECT COUNT(id) FROM Turnier_Spiel WHERE fk_begegnung = begegnung.id) ";
      myDb_execute($conn, $TurnierID, $bn, "edit_games.php 5",$sqlFinalizeBegegnung, array($begegnungId));
    }

    

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }
  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde nicht eingetragen.')</script>";
    $message = "Leider gehört das Spiel, das du eintragen möchtest, nicht zu deinem Team. Falls du es eintragen möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Finalisieren'){
  if($accountDarfSpieleBearbeiten == 1 || ($successfulTeamLogin == 1 && $spielGehoertZuTeam == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    $sql = "UPDATE Turnier_Begegnung SET `status` = CASE WHEN `status` = 4 THEN 7 ELSE 5 END WHERE id = ?";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 6",$sql, array($begegnungId));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde erfolgreich finalisiert. $successfulLogin $spielGehoertZuTeam')</script>";
  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Das Spiel wurde nicht finalisiert.')</script>";
    $message = "Leider gehört das Spiel, das du bearbeiten möchtest, nicht zu deinem Team. Falls du es bearbeiten möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Unfinalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $sql = "UPDATE Turnier_Begegnung SET `status` = CASE WHEN `status` = 7 THEN 4 ELSE 1 END, fk_siegerteam = NULL WHERE id = ?";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 7",$sql, array($begegnungId));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
        exit;
    }

  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Die Begegnung wurde nicht unfinalisiert...')</script>";
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um die Finalisierung aufzuheben. Wende dich am besten an einen Admininstrator";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Gruppe_Finalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $groupId = $_POST['groupId'];
    $sql = "UPDATE Turnier_Begegnung SET `status` = CASE WHEN `status` = 4 THEN 7 ELSE 5 END WHERE status <> 3 AND ko_finallevel = 0 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ?)";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 8",$sql, array($groupId, $groupId));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
        exit;
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
        exit;
    }

    //echo "<script>console.log('Die Begegnung wurde nicht unfinalisiert...')</script>";
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um die gesamte Gruppe zu finalisieren. Wende dich am besten an einen Admininstrator";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Gruppe_Uninalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $groupId = $_POST['groupId'];
    $sql = "UPDATE Turnier_Begegnung SET `status` = CASE WHEN `status` = 7 THEN 4 ELSE 1 END WHERE status <> 3 AND ko_finallevel = 0 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ?)";
    myDb_execute($conn, $TurnierID, $bn, "edit_games.php 9",$sql, array($groupId, $groupId));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    //echo "<script>console.log('Die Begegnung wurde nicht unfinalisiert...')</script>";
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um die gesamte Gruppe zu finalisieren. Wende dich am besten an einen Admininstrator";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
// ================================================================================================
// BEGEGNUNG HINZUFÜGEN (GREEN CARD): manuell angelegte Begegnung, vor dem Auto-Scheduler geschützt
// ================================================================================================
// Nur Admin/Co-Admin (explizite Vorgabe, kein eigenes Rollen-Flag dafür). Status wird fest auf 4
// (Green Card) gesetzt, damit die automatische Spielplan-Berechnung diese Begegnung nie überschreibt.
}else if($action == 'Begegnung_Hinzufuegen'){
  if($istAdminOderCoAdminEditGames){ //Nur Admin/Co-Admin dürfen Green-Card-Begegnungen anlegen
    $team1 = (int)$_POST['team1'];
    $team2 = (int)$_POST['team2'];
    $koFinallevel = (int)$_POST['ko_finallevel'];
    $koPositionRaw = isset($_POST['ko_turnierbaumposition']) ? trim($_POST['ko_turnierbaumposition']) : '';

    // Bei Gruppenphase (0) und Losing Bracket (20) gibt es keine Bracket-Position.
    // Bei allen anderen (K.-o.-)Finallevels muss sie explizit angegeben werden,
    // sonst könnte die Begegnung an eine falsche/leere Stelle im Turnierbaum geraten.
    $istBracketPhase = ($koFinallevel != 0 && $koFinallevel != 20);
    $gueltig = ($team1 > 0 && $team2 > 0 && $team1 != $team2 && (!$istBracketPhase || $koPositionRaw !== ''));

    if ($gueltig) {
      if ($istBracketPhase) {
        $koPosition = (int)$koPositionRaw;
        $sql = "INSERT INTO Turnier_Begegnung (fk_heimteam, fk_auswaertsteam, fk_siegerteam, ko_finallevel, ko_turnierbaumposition, status) VALUES (?, ?, NULL, ?, ?, 4)";
        myDb_execute($conn, $TurnierID, $bn, "edit_games.php 10", $sql, array($team1, $team2, $koFinallevel, $koPosition));
      } else {
        $sql = "INSERT INTO Turnier_Begegnung (fk_heimteam, fk_auswaertsteam, fk_siegerteam, ko_finallevel, ko_turnierbaumposition, status) VALUES (?, ?, NULL, ?, NULL, 4)";
        myDb_execute($conn, $TurnierID, $bn, "edit_games.php 10", $sql, array($team1, $team2, $koFinallevel));
      }
    }

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

  }else{ //keine ausreichenden Rechte

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

  }
// ================================================================================================
// BEGEGNUNG SPERREN: vorher nicht-funktionaler Stub, jetzt echt wirksam
// ================================================================================================
// Nur Admin/Co-Admin. Setzt status=6 ("gesperrt") - db_update.php lässt diese Begegnung dadurch in
// Ruhe (kein Überschreiben durch den Auto-Scheduler), Öffentlichkeit sieht sie nicht mehr,
// Backstage-Nutzer sehen sie weiterhin (ausgegraut, siehe printKO_PhaseTabellen).
}else if($action == 'Begegnung_Sperren'){
  if($istAdminOderCoAdminEditGames){ //Nur Admin/Co-Admin dürfen Begegnungen sperren
    $begegnungIdSperren = (int)$_POST['begegnungIdSperren'];

    if ($begegnungIdSperren > 0) {
      // Status 6 = "gesperrt": db_update.php lässt Begegnungen mit diesem Status unangetastet
      // (siehe begegnungErstellen() in database/db_update.php)
      $sql = "UPDATE Turnier_Begegnung SET status = 6 WHERE id = ?";
      myDb_execute($conn, $TurnierID, $bn, "edit_games.php 11", $sql, array($begegnungIdSperren));
    }

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

  }else{ //keine ausreichenden Rechte

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

  }
}else{
  
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

}

?> 

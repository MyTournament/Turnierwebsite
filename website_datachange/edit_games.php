<?php
//##########################################################
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
//##########################################################

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
//echo "<script>console.log('Action: $action')</script>";

$bn = $_POST['bn'];
$pw = $_POST['pw'];
//##########################################################
//LOGIN
include_once 'login_interface.php';
$rights = get_rights_of_user($conn, $TurnierID, $bn, $pw, $begegnungId);



$successfulLogin = $rights["team_rights"]["successfulLogin"];
$spielGehoertZuTeam = $rights["team_rights"]["spielGehoertZuTeam"];
$teamBearbeitungsrecht = $rights["team_rights"]["teamBearbeitungsrecht"];

$accountDarfSpieleBearbeiten = $rights["account_rights"]["rechte_alle_spiele"];
//##########################################################

if ($action == 'Ändern') {
  if($accountDarfSpieleBearbeiten == 1 || ($successfulLogin == 1 && $spielGehoertZuTeam == 1 && $teamBearbeitungsrecht == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    $sql = "UPDATE `Turnier_Spiel` SET `biereheimteam` = ?, `biereauswaertsteam` = ? WHERE `Turnier_Spiel`.`id` = ?;";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($flaschen1, $flaschen2, $spielID));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
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
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    //echo "<script>console.log('Das Spiel wurde nicht geändert.')</script>";
    $message = "Leider gehört das Spiel, das du bearbeiten möchtest, nicht zu deinem Team. Falls du es bearbeiten möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if ($action == 'Löschen'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $sql = "DELETE FROM `Turnier_Spiel` WHERE `Turnier_Spiel`.`id` = ?;";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($spielID));

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
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
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    //echo "<script>console.log('Das Spiel wurde nicht gelöscht..')</script>";
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um  einen Spielstand zu löschen.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Eintragen'){
  if($accountDarfSpieleBearbeiten == 1 || ($successfulLogin == 1 && $spielGehoertZuTeam == 1 && $teamBearbeitungsrecht == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    $sqlInsertSpiel = "INSERT INTO Turnier_Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?)";
    myDb_execute($conn, $TurnierID, $bn, $sqlInsertSpiel, array($begegnungId, $_POST['Flaschen1'], $_POST['Flaschen2'], $bn));
    
    //TODO: Für Gruppenphase alle Begegnungen mit 1 Spiel und für KO-Phase alle Begegnungen mit 3 Spielen als final markieren
    //-final wird nie wieder als unnötig markiert #done (wird einfach ganz oben nicht als veraltet markiert) - TODO: trotzdem Fall mitbedenken dass Admin ein final-Spiel in Achtel löscht, dann müssten auch Finalspiele in höherer Ebene die darauf folgen gelöscht werden.
    //-ab finaler Begegnung kann auch kein Spiel mehr dazu eingetragen werden
    //-final kann nur noch von Admins gelöscht oder geändert werden 
    //-erst wenn Begegnung final, wird nächste Finalstufe berechnet
    //-bis halbe stunde nach eintragen noch ändern können

    // TODO Check if its Gruppenphase and both sides of Gruppenphasendreieck are used
    $sqlGetNurOberesDreieckInGruppenphase = "SELECT nurOberesDreieckInGruppenphase FROM Turnier_Main WHERE id = ?";
    $stmt = myDb_execute($conn, $TurnierID, $bn, $sqlGetNurOberesDreieckInGruppenphase, $TurnierID);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nurOberesDreieck = $row['nurOberesDreieckInGruppenphase'];

    if ($nurOberesDreieck === 2){
      $sqlFinalizeBegegnung = "UPDATE Turnier_Begegnung SET `status` = 5 WHERE id = ?";
      myDb_execute($conn, $TurnierID, $bn, $sqlFinalizeBegegnung, array($begegnungId));
    }

    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

    //echo "<script>console.log('Das Spiel wurde erfolgreich eingetragen. $successfulLogin $spielGehoertZuTeam')</script>";
  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    //echo "<script>console.log('Das Spiel wurde nicht eingetragen.')</script>";
    $message = "Leider gehört das Spiel, das du eintragen möchtest, nicht zu deinem Team. Falls du es eintragen möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Finalisieren'){
  if($accountDarfSpieleBearbeiten == 1 || ($successfulLogin == 1 && $spielGehoertZuTeam == 1 && $teamBearbeitungsrecht == 1)){ //Account-Login oder Team-Login && Spiel gehört zu Team
    $sql = "UPDATE Turnier_Begegnung SET `status` = 5 WHERE id = ?";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($begegnungId));
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_success");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_success");
    }

    //echo "<script>console.log('Das Spiel wurde erfolgreich finalisiert. $successfulLogin $spielGehoertZuTeam')</script>";
  }else{ //Team-Login
    
    //WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /#edit_games_failure");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#edit_games_failure");
    }

    //echo "<script>console.log('Das Spiel wurde nicht finalisiert.')</script>";
    $message = "Leider gehört das Spiel, das du bearbeiten möchtest, nicht zu deinem Team. Falls du es bearbeiten möchtest, wende dich am besten an einen Administrator.";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Unfinalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $sql = "UPDATE Turnier_Begegnung SET `status` = 1, fk_siegerteam = NULL WHERE id = ?";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($begegnungId));
    
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
    $message = "Leider hast du nicht die nötigen Bearbeitungsrechte, um die Finalisierung aufzuheben. Wende dich am besten an einen Admininstrator";
    //echo "<script type='text/javascript'>alert('$message');</script>";
  }
}else if($action == 'Gruppe_Finalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $groupId = $_POST['groupId'];
    $sql = "UPDATE Turnier_Begegnung SET `status` = 5 WHERE status <> 3 AND ko_finallevel = 0 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_gruppe = ?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE fk_gruppe = ?)";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($groupId, $groupId));
    
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
}else if($action == 'Gruppe_Uninalisieren'){
  if($accountDarfSpieleBearbeiten == 1){ //Account-Login
    $groupId = $_POST['groupId'];
    $sql = "UPDATE Turnier_Begegnung SET `status` = 1 WHERE status <> 3 AND ko_finallevel = 0 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_gruppe = ?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE fk_gruppe = ?)";
    myDb_execute($conn, $TurnierID, $bn, $sql, array($groupId, $groupId));
    
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
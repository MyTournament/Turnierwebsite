<?php
echo "<script>console.log('edit_account Checkpoint 1')</script>";
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
include_once '../variables.php';
include_once 'login_interface.php';

$action = $_POST['action'];
$bn = $_POST['bn'];
$pw = $_POST['pw'];
$fk_rechte = '30';
echo "<script>console.log('edit_account Checkpoint 2')</script>";

if($action == 'register'){
    $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort, fk_rechte) VALUES (?, ?, ?)";
    //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: $accountId = myDb_execute($conn, $TurnierID, $bn, "edit_account.php", $sql, array($bn, $pw, $fk_rechte));
    //accountId könnte jetzt natürlich noch zurück zur index gegeben werden, damit man direkt eingeloggt ist
    //weiß aber leider nicht wie das geht ohne es im Klartext an die uri zu hängen

}else if($action == 'admin_erstellt_nutzer'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $neuerBn = trim($_POST['neuer_bn']);
    $neuerPw = $_POST['neuer_pw'];
    $neueRolle = (int)$_POST['neue_rolle'];

    //Rechte des anlegenden Admins/Co-Admins herausfinden
    $benutzerliste = getBenutzerListe($conn);
    $adminRechte = null;
    while ($row = $benutzerliste->fetch_assoc()) {
        if ($row['Benutzername'] == $adminBn && $row['Passwort'] == $adminPw) {
            $adminRechte = (int)$row['fk_rechte'];
        }
    }

    $darfAnlegen = false;
    if ($adminRechte == 1 || $adminRechte == 2) {
        $sqlEigeneRolle = "SELECT * FROM System_Benutzer_in_Rolle WHERE id = " . $adminRechte;
        $resultEigeneRolle = $conn->query($sqlEigeneRolle);
        $rowEigeneRolle = $resultEigeneRolle ? $resultEigeneRolle->fetch_assoc() : null;
        if ($rowEigeneRolle) {
            if ($neueRolle == 1 && $rowEigeneRolle['rechte_neue_admins'] == 1) { $darfAnlegen = true; }
            else if ($neueRolle == 2 && $rowEigeneRolle['rechte_neue_co_admins'] == 1) { $darfAnlegen = true; }
            else if ($neueRolle != 1 && $neueRolle != 2 && $rowEigeneRolle['rechte_restliche_rollen_vergeben'] == 1) { $darfAnlegen = true; }
        }
    }

    if ($darfAnlegen && $neuerBn !== '' && $neuerPw !== '') {
        $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort, fk_rechte) VALUES (?, ?, ?)";
        myDb_execute($conn, 0, $adminBn, "edit_account.php 2", $sql, array($neuerBn, $neuerPw, $neueRolle));
    }
}

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$test_turnier_id = $_GET['test_turnier_id'];
if ($action == 'admin_erstellt_nutzer') {
    if($test_turnier_id==NULL){
        header("Location: /#backstage_nutzermanagement");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#backstage_nutzermanagement");
    }
}else if($test_turnier_id==NULL){
    header("Location: ../#pausenraum");
}else{
    header("Location: ../#pausenraum?test_turnier_id=$test_turnier_id");
}
?>
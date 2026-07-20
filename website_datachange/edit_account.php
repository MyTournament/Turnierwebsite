<?php
echo "<script>console.log('edit_account Checkpoint 1')</script>";
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
include_once '../variables.php';
include_once 'login_interface.php';

$action = $_POST['action'];
$bn = $_POST['bn'];
$pw = $_POST['pw'];
echo "<script>console.log('edit_account Checkpoint 2')</script>";

// ============================================================================================
// RECHTE-AUDIT: OB EINE ROLLE VERGEBEN/ENTZOGEN WERDEN DARF, HÄNGT AN DEN FLAGS DER ZIEL-ROLLE
// SELBST (rechte_neue_admins/rechte_neue_co_admins), NICHT AN IHRER ID.
// ============================================================================================
// Vorher wurde hart nach Rollen-ID geprüft (zielRolle==1 -> Admin, ==2 -> Co-Admin). Jetzt wird
// stattdessen die Ziel-Rolle selbst aus System_Benutzer_in_Rolle nachgeschlagen: hat SIE das Flag
// rechte_neue_admins, braucht der Vergebende ebenfalls rechte_neue_admins usw. - unabhängig von
// IDs oder Namen, funktioniert also auch für später hinzukommende admin-artige Rollen.
function darfRolleVergeben($conn, $rollenInfoAdmin, $zielRolle) {
    if ($rollenInfoAdmin === null) { return false; }
    $flags = $rollenInfoAdmin['flags'];
    $stmtZielRolle = $conn->prepare("SELECT rechte_neue_admins, rechte_neue_co_admins FROM System_Benutzer_in_Rolle WHERE id = ?");
    $stmtZielRolle->bind_param("i", $zielRolle);
    $stmtZielRolle->execute();
    $zielRolleRow = $stmtZielRolle->get_result()->fetch_assoc();
    if ($zielRolleRow === null) { return false; }
    if ($zielRolleRow['rechte_neue_admins']) { return $flags['neue_admins']; }
    if ($zielRolleRow['rechte_neue_co_admins']) { return $flags['neue_co_admins']; }
    return $flags['restliche_rollen_vergeben'];
}

if($action == 'register'){
    $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort) VALUES (?, ?)";
    //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: $accountId = myDb_execute($conn, $TurnierID, $bn, "edit_account.php", $sql, array($bn, $pw));
    //accountId könnte jetzt natürlich noch zurück zur index gegeben werden, damit man direkt eingeloggt ist
    //weiß aber leider nicht wie das geht ohne es im Klartext an die uri zu hängen

}else if($action == 'admin_erstellt_nutzer'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $neuerBn = trim($_POST['neuer_bn']);
    $neuerPw = $_POST['neuer_pw'];
    $neueRolle = (int)$_POST['neue_rolle'];

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    if (darfRolleVergeben($conn, $rollenInfoAdmin, $neueRolle) && $neuerBn !== '' && $neuerPw !== '') {
        // Die Rolle wird ausschließlich im Mehrfach-Rollen-System eingetragen, fk_rechte wird
        // nicht mehr benutzt (Spalte soll in einer der nächsten Versionen entfernt werden).
        $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort) VALUES (?, ?)";
        $neuerBenutzerId = myDb_execute($conn, 0, $adminBn, "edit_account.php 2", $sql, array($neuerBn, $neuerPw));
        $sqlRel = "INSERT INTO System_Benutzer_in_Relation_Rolle (fk_benutzer_in, fk_rolle) VALUES (?, ?)";
        myDb_execute($conn, 0, $adminBn, "edit_account.php 3", $sqlRel, array($neuerBenutzerId, $neueRolle));
    }

}else if($action == 'Rolle_Hinzufuegen'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];
    $neueRolle = (int)$_POST['neue_rolle'];

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    if (darfRolleVergeben($conn, $rollenInfoAdmin, $neueRolle) && $zielBenutzerId > 0) {
        try {
            $sqlPruefen = "SELECT 1 FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ? AND fk_rolle = ?";
            $stmtPruefen = $conn->prepare($sqlPruefen);
            $stmtPruefen->bind_param("ii", $zielBenutzerId, $neueRolle);
            $stmtPruefen->execute();
            $bereitsVorhanden = $stmtPruefen->get_result()->fetch_assoc();
            if (!$bereitsVorhanden) {
                $sqlRel = "INSERT INTO System_Benutzer_in_Relation_Rolle (fk_benutzer_in, fk_rolle) VALUES (?, ?)";
                myDb_execute($conn, 0, $adminBn, "edit_account.php 4", $sqlRel, array($zielBenutzerId, $neueRolle));
            }
        } catch (Throwable $e) {
            // Relation-Tabelle (noch) nicht vorhanden
        }
    }

}else if($action == 'Rolle_Entfernen'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];
    $entferneRolle = (int)$_POST['entferne_rolle'];

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    if (darfRolleVergeben($conn, $rollenInfoAdmin, $entferneRolle) && $zielBenutzerId > 0) {
        try {
            $sqlRel = "DELETE FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ? AND fk_rolle = ?";
            myDb_execute($conn, 0, $adminBn, "edit_account.php 5", $sqlRel, array($zielBenutzerId, $entferneRolle));
        } catch (Throwable $e) {
            // Relation-Tabelle (noch) nicht vorhanden
        }
    }
}

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$test_turnier_id = $_GET['test_turnier_id'];
$nutzermanagementActions = ['admin_erstellt_nutzer', 'Rolle_Hinzufuegen', 'Rolle_Entfernen'];
if (in_array($action, $nutzermanagementActions, true)) {
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

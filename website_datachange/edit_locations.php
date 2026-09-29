<?php
// ================================================================================================
// BIERBALL LOCATIONS (Pausenraum) - komplett neu geschrieben bei der Reaktivierung.
// ================================================================================================
// Die alte Version hatte ein eigenes, zweites Ad-hoc-Login (rohe SQL-Abfrage direkt aus $_POST,
// $accountId kam unvalidiert direkt aus dem Formular statt aus einem echten Login) und keinerlei
// Prepared Statements. Jetzt: dasselbe zentrale Login wie überall sonst auf der Website
// (getUserRollenInfo), die Benutzer-ID kommt ausschließlich aus dem so validierten Login (nie aus
// einem rohen $_POST-Feld), durchgehend Prepared Statements über myDb_execute(), CSRF-Token-Pflicht.
// Sichtbarkeit/Nutzung ist Admin/Co-Admin-only (siehe pausenraum.php) - hier zusätzlich serverseitig
// nachgeprüft, nicht nur über die Sichtbarkeit der Buttons.
// ================================================================================================
include_once '../website_functionalities/session_bootstrap.php';
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
include_once 'login_interface.php';
include_once '../website_functionalities/csrf.php';

$bn = isset($_POST['bn']) ? $_POST['bn'] : '';
$pw = isset($_POST['pw']) ? $_POST['pw'] : '';
$TurnierID = isset($_POST['TurnierID']) ? (int)$_POST['TurnierID'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Admin/Co-Admin-only (identisch zu $istAdminOderCoAdmin/pausenraum.php - nicht "irgendeine Rolle").
$rollenInfoLocations = getUserRollenInfo($conn, $bn, $pw);
$darfPausenraumNutzen = ($rollenInfoLocations !== null) && ($rollenInfoLocations['ist_admin'] || $rollenInfoLocations['ist_co_admin']);

if ($darfPausenraumNutzen && csrf_verify()) {
    $accountId = $rollenInfoLocations['benutzer_id'];

    if ($action == 'new_location') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        if ($name !== '' && $description !== '') {
            $sql = "INSERT INTO Pausenraum_Location (name, description, autor) VALUES (?, ?, ?)";
            myDb_execute($conn, $TurnierID, $bn, "edit_locations.php new_location", $sql, array($name, $description, $accountId));

            $typeId = 6; // Achievement: Location hinzugefuegt
            $add_text = ": " . $name;
            $sqlAch = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type, add_text) VALUES (?, ?, ?)";
            myDb_execute($conn, $TurnierID, $bn, "edit_locations.php new_location achievement", $sqlAch, array($accountId, $typeId, $add_text));
        }
        header("Location: ../#bierball_locations");
        exit;

    } else if ($action == 'new_rating') {
        $fkLocation = (int)(isset($_POST['fk_location']) ? $_POST['fk_location'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $sterne = (int)(isset($_POST['sterne']) ? $_POST['sterne'] : 0);
        if ($sterne < 0) { $sterne = 0; }
        if ($sterne > 5) { $sterne = 5; }

        if ($fkLocation > 0 && $name !== '' && $description !== '') {
            $sql = "INSERT INTO Pausenraum_Location_Bewertung (name, description, sterne, fk_location, autor) VALUES (?, ?, ?, ?, ?)";
            myDb_execute($conn, $TurnierID, $bn, "edit_locations.php new_rating", $sql, array($name, $description, $sterne, $fkLocation, $accountId));

            $typeId = 2; // Achievement: Bewertung hinzugefuegt
            $locationName = trim(isset($_POST['location_name']) ? $_POST['location_name'] : '');
            $add_text = " fuer " . $locationName . ": " . $sterne . " Sterne";
            $sqlAch = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type, add_text) VALUES (?, ?, ?)";
            myDb_execute($conn, $TurnierID, $bn, "edit_locations.php new_rating achievement", $sqlAch, array($accountId, $typeId, $add_text));
        }
        header("Location: ../#bierball_locations");
        exit;
    }
}

// Kein gültiger Login/keine Rolle/kein CSRF-Token, oder unbekannte Aktion
header("Location: ../#pausenraum");
exit;
?>

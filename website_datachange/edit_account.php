<?php
// SICHERHEIT: MUSS vor dem ersten session_start() der Anfrage eingebunden werden.
include_once '../website_functionalities/session_bootstrap.php';
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
include_once '../variables.php';
include_once 'login_interface.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$bn = isset($_POST['bn']) ? $_POST['bn'] : '';
$pw = isset($_POST['pw']) ? $_POST['pw'] : '';

// ================================================================================================
// CSRF-SCHUTZ - alle Formulare, die auf diese Aktionen posten (Nutzermanagement in index.php,
// #register_account), schicken seit dieser Aenderung den Session-Token mit (siehe csrf.php). Bei
// fehlendem/falschem Token wird die Aktion NICHT ausgefuehrt ($action geleert, keiner der folgenden
// Zweige matcht dann mehr) - sicherer Fallback statt eines harten die().
// ================================================================================================
include_once '../website_functionalities/csrf.php';
$csrfGeschuetzteAktionen = ['register', 'admin_erstellt_nutzer', 'Nutzer_Rollen_Speichern', 'Eigenes_Profil_Speichern', 'Benutzername_Aendern', 'Admin_Kommentar_Aendern', 'Login_Als_User', 'Benutzer_Loeschen'];
if (in_array($action, $csrfGeschuetzteAktionen, true) && !csrf_verify()) {
    $action = '';
}

// ================================================================================================
// CAPTCHA-CHECK FÜR DIE SELBSTREGISTRIERUNG (#register_account) - eigener formKey "user_register",
// bewusst NICHT "register" (das ist bereits der formKey der Team-Anmeldung in edit_teams.php), damit
// sich die beiden unabhängigen Captcha-Abläufe nicht gegenseitig überschreiben. Gleiches Muster wie
// dort: nur das Captcha prüfen, Benutzername merken und zurück zu #register_account.
// ================================================================================================
if (isset($_POST['cb_action']) && $_POST['cb_action'] === 'check') {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    require_once __DIR__ . '/../website_functionalities/captcha_blanki.php';
    $regRes = CaptchaBlanki::preverify($_POST);
    $_SESSION['register_account_form_data'] = ['reg_bn' => isset($_POST['reg_bn']) ? $_POST['reg_bn'] : ''];
    $regStatusMessage = $regRes['ok']
        ? 'Captcha bestätigt. Du kannst jetzt absenden.'
        : (($regRes['remaining']>0) ? ('Captcha falsch. Verbleibende Versuche: '.$regRes['remaining']) : 'Captcha 3x fehlgeschlagen. Die Seite wurde neu geladen.');
    $_SESSION['flash_error_user_register'] = $regStatusMessage;
    $_SESSION['captcha_remaining_user_register'] = isset($regRes['remaining']) ? (int)$regRes['remaining'] : 3;
    $_SESSION['captcha_attempted_user_register'] = 1;
    header("Location: /#register_account");
    exit;
}

// ============================================================================================
// RECHTE-AUDIT: OB EINE ROLLE VERGEBEN/ENTZOGEN WERDEN DARF, HÄNGT AN DEN FLAGS DER ZIEL-ROLLE
// SELBST (rechte_neue_admins/rechte_neue_co_admins), NICHT AN IHRER ID.
// ============================================================================================
// Vorher wurde hart nach Rollen-ID geprüft (zielRolle==1 -> Admin, ==2 -> Co-Admin). Jetzt wird
// stattdessen die Ziel-Rolle selbst nachgeschlagen: hat SIE das Flag rechte_neue_admins, braucht der
// Vergebende ebenfalls rechte_neue_admins usw. - unabhängig von IDs oder Namen, funktioniert also
// auch für später hinzukommende admin-artige Rollen. Die Flags kommen aus getRollenFlags()
// (rollen_definitionen.php, Code statt DB-Tabelle - siehe Datei für den Hintergrund).
function darfRolleVergeben($conn, $rollenInfoAdmin, $zielRolle) {
    if ($rollenInfoAdmin === null) { return false; }
    $flags = $rollenInfoAdmin['flags'];
    $zielRolleFlags = getRollenFlags($zielRolle);
    if ($zielRolleFlags['rechte_neue_admins']) { return $flags['neue_admins']; }
    if ($zielRolleFlags['rechte_neue_co_admins']) { return $flags['neue_co_admins']; }
    return $flags['restliche_rollen_vergeben'];
}

// ================================================================================================
// SELBSTREGISTRIERUNG (#register_account) - neue Accounts bekommen bewusst KEINE Rolle (nicht mal
// "Benutzer*in"). Ein Admin/Co-Admin muss im Nutzermanagement zuerst eine Rolle zuweisen, bevor der
// Account irgendetwas darf. Bot-Schutz über CaptchaBlanki mit eigenem formKey "user_register" (siehe
// cb_action=='check'-Block oben) - ohne bestätigtes Captcha wird nichts angelegt.
// ================================================================================================
if($action == 'register'){
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $test_turnier_id_reg = isset($_GET['test_turnier_id']) ? $_GET['test_turnier_id'] : 0;
    $regSuffix = $test_turnier_id_reg != 0 ? "?test_turnier_id=$test_turnier_id_reg" : "";
    $regBn = trim(isset($_POST['reg_bn']) ? $_POST['reg_bn'] : '');
    $regPw = isset($_POST['reg_pw']) ? $_POST['reg_pw'] : '';
    $regPw2 = isset($_POST['reg_pw2']) ? $_POST['reg_pw2'] : '';

    require_once __DIR__ . '/../website_functionalities/captcha_blanki.php';

    if (!CaptchaBlanki::passed('user_register')) {
        $_SESSION['flash_error_register_account'] = 'Bitte zuerst das Captcha bestätigen.';
        header("Location: ../#register_account" . $regSuffix);
        exit;
    }
    if ($regBn === '' || $regPw === '') {
        $_SESSION['flash_error_register_account'] = 'Bitte Benutzername und Passwort ausfüllen.';
        header("Location: ../#register_account" . $regSuffix);
        exit;
    }
    if ($regPw !== $regPw2) {
        $_SESSION['flash_error_register_account'] = 'Die beiden Passwörter stimmen nicht überein.';
        header("Location: ../#register_account" . $regSuffix);
        exit;
    }
    $stmtRegPruefen = $conn->prepare("SELECT id FROM System_Benutzer_in WHERE Benutzername = ?");
    $stmtRegPruefen->bind_param("s", $regBn);
    $stmtRegPruefen->execute();
    $regBereitsVergeben = $stmtRegPruefen->get_result()->fetch_assoc();
    if ($regBereitsVergeben) {
        $_SESSION['flash_error_register_account'] = 'Dieser Benutzername ist bereits vergeben.';
        header("Location: ../#register_account" . $regSuffix);
        exit;
    }

    // Bewusst OHNE jede Rollen-Vergabe - siehe Kommentar oberhalb dieses Blocks.
    $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort) VALUES (?, ?)";
    myDb_execute($conn, 0, $regBn, "edit_account.php register", $sql, array($regBn, $regPw));

    $_SESSION['flash_success_register_account'] = 'Account erstellt! Du kannst dich jetzt einloggen - bitte sag Bescheid, damit ein Admin dich freischaltet.';
    header("Location: ../#backstage" . $regSuffix);
    exit;

}else if($action == 'admin_erstellt_nutzer'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $neuerBn = trim($_POST['neuer_bn']);
    $neuerPw = $_POST['neuer_pw'];
    // Ein Nutzer kann mehrere Rollen gleichzeitig haben - das Formular sammelt sie clientseitig als
    // Array ("neue_rollen[]"), jede einzeln wird unabhängig gegen darfRolleVergeben geprüft, damit
    // niemand sich über eine erlaubte Rolle indirekt eine NICHT erlaubte Rolle "erschleichen" kann.
    $neueRollenRoh = isset($_POST['neue_rollen']) && is_array($_POST['neue_rollen']) ? $_POST['neue_rollen'] : [];
    $neueRollen = array_values(array_unique(array_map('intval', $neueRollenRoh)));

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    $erlaubteRollen = [];
    foreach ($neueRollen as $rid) {
        if (darfRolleVergeben($conn, $rollenInfoAdmin, $rid)) { $erlaubteRollen[] = $rid; }
    }

    if (count($erlaubteRollen) > 0 && $neuerBn !== '' && $neuerPw !== '') {
        // Die Rollen werden ausschließlich im Mehrfach-Rollen-System eingetragen, fk_rechte wird
        // nicht mehr benutzt (Spalte soll in einer der nächsten Versionen entfernt werden).
        $sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort) VALUES (?, ?)";
        $neuerBenutzerId = myDb_execute($conn, 0, $adminBn, "edit_account.php 2", $sql, array($neuerBn, $neuerPw));
        foreach ($erlaubteRollen as $rid) {
            $sqlRel = "INSERT INTO System_Benutzer_in_Relation_Rolle (fk_benutzer_in, fk_rolle) VALUES (?, ?)";
            myDb_execute($conn, 0, $adminBn, "edit_account.php 3", $sqlRel, array($neuerBenutzerId, $rid));
        }
    }

// ================================================================================================
// ROLLEN + PASSWORT IN EINEM RUTSCH SPEICHERN - ersetzt die früheren Einzel-Aktionen
// Rolle_Hinzufuegen/Rolle_Entfernen/Passwort_Aendern (je eine pro Klick, jede mit eigenem Redirect/
// Page-Reload). Auf ausdrücklichen Wunsch (siehe Chat: "nervig, wenn man mehrere Rollen hinzufügen
// will und die Seite jedes Mal neu lädt") sammelt das Nutzermanagement jetzt alle Änderungen an
// einem Nutzer (mehrere Rollen hinzufügen/entfernen, Passwort ändern) clientseitig und schickt sie
// erst bei Klick auf "Speichern" gemeinsam in EINEM POST-Request - dadurch nur noch ein Reload
// (und damit ein "Wiedersuchen" des Nutzers) pro Bearbeitungsvorgang statt pro Einzeländerung.
// Jede Rolle wird weiterhin EINZELN gegen darfRolleVergeben() geprüft (wie vorher), damit niemand
// über eine erlaubte Rolle indirekt eine nicht erlaubte Rolle hinzufügen/entfernen kann. Das
// Passwort bleibt "echten" Admins vorbehalten (ist_admin), genau wie beim vorherigen Passwort_Aendern.
// ================================================================================================
}else if($action == 'Nutzer_Rollen_Speichern'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];
    $rollenHinzu = isset($_POST['rollen_hinzufuegen']) && is_array($_POST['rollen_hinzufuegen']) ? array_map('intval', $_POST['rollen_hinzufuegen']) : [];
    $rollenWeg = isset($_POST['rollen_entfernen']) && is_array($_POST['rollen_entfernen']) ? array_map('intval', $_POST['rollen_entfernen']) : [];
    $neuesPasswort = trim(isset($_POST['neues_passwort']) ? $_POST['neues_passwort'] : '');

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    if ($rollenInfoAdmin !== null && $zielBenutzerId > 0) {
        foreach (array_unique($rollenHinzu) as $neueRolle) {
            if (!darfRolleVergeben($conn, $rollenInfoAdmin, $neueRolle)) { continue; }
            try {
                $stmtPruefen = $conn->prepare("SELECT 1 FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ? AND fk_rolle = ?");
                $stmtPruefen->bind_param("ii", $zielBenutzerId, $neueRolle);
                $stmtPruefen->execute();
                $bereitsVorhanden = $stmtPruefen->get_result()->fetch_assoc();
                if (!$bereitsVorhanden) {
                    $sqlRel = "INSERT INTO System_Benutzer_in_Relation_Rolle (fk_benutzer_in, fk_rolle) VALUES (?, ?)";
                    myDb_execute($conn, 0, $adminBn, "edit_account.php Nutzer_Rollen_Speichern hinzu", $sqlRel, array($zielBenutzerId, $neueRolle));
                }
            } catch (Throwable $e) {
                // Relation-Tabelle (noch) nicht vorhanden
            }
        }
        foreach (array_unique($rollenWeg) as $entferneRolle) {
            if (!darfRolleVergeben($conn, $rollenInfoAdmin, $entferneRolle)) { continue; }
            try {
                $sqlRel = "DELETE FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ? AND fk_rolle = ?";
                myDb_execute($conn, 0, $adminBn, "edit_account.php Nutzer_Rollen_Speichern weg", $sqlRel, array($zielBenutzerId, $entferneRolle));
            } catch (Throwable $e) {
                // Relation-Tabelle (noch) nicht vorhanden
            }
        }
        if ($rollenInfoAdmin['ist_admin'] && $neuesPasswort !== '') {
            $sqlPwAendern = "UPDATE System_Benutzer_in SET Passwort = ? WHERE id = ?";
            myDb_execute($conn, 0, $adminBn, "edit_account.php Nutzer_Rollen_Speichern passwort", $sqlPwAendern, array($neuesPasswort, $zielBenutzerId));
        }
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION['flash_success'] = 'Änderungen gespeichert.';
    }

// ================================================================================================
// BENUTZERNAME EINES ANDEREN NUTZERS ÄNDERN - genau wie Passwort ändern bewusst nur für "echte"
// Admins (ist_admin), nicht für Co-Admins.
// ================================================================================================
}else if($action == 'Benutzername_Aendern'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];
    $neuerBenutzername = trim($_POST['neuer_benutzername']);

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);

    if ($rollenInfoAdmin !== null && $rollenInfoAdmin['ist_admin'] && $zielBenutzerId > 0 && $neuerBenutzername !== '') {
        // Eindeutigkeit prüfen - Benutzername wird beim Login zur Identifikation genutzt, darf also
        // nicht doppelt vergeben werden (außer an den Nutzer selbst, dessen Name unverändert bleibt).
        $stmtPruefen = $conn->prepare("SELECT id FROM System_Benutzer_in WHERE Benutzername = ? AND id != ?");
        $stmtPruefen->bind_param("si", $neuerBenutzername, $zielBenutzerId);
        $stmtPruefen->execute();
        $bereitsVergeben = $stmtPruefen->get_result()->fetch_assoc();
        if (!$bereitsVergeben) {
            $sqlBnAendern = "UPDATE System_Benutzer_in SET Benutzername = ? WHERE id = ?";
            myDb_execute($conn, 0, $adminBn, "edit_account.php Benutzername_Aendern", $sqlBnAendern, array($neuerBenutzername, $zielBenutzerId));
        }
    }

// ================================================================================================
// EIGENES PROFIL BEARBEITEN (#account_profil) - jeder eingeloggte Account darf SEINEN EIGENEN
// Benutzernamen/Passwort ändern, unabhängig von seiner Rolle (anders als beim Nutzermanagement oben,
// das "echten" Admins vorbehalten ist und dort fremde Accounts ändert). Identität kommt ausschließlich
// aus admin_bn/admin_pw (den eigenen, bereits bekannten Zugangsdaten) - es gibt bewusst KEIN
// ziel_benutzer_id-Feld, damit über diese Aktion niemals ein FREMDER Account verändert werden kann.
// ================================================================================================
}else if($action == 'Eigenes_Profil_Speichern'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $neuerBenutzername = trim(isset($_POST['neuer_benutzername']) ? $_POST['neuer_benutzername'] : '');
    $neuesPasswort = trim(isset($_POST['neues_passwort']) ? $_POST['neues_passwort'] : '');
    $neuerAvatar = trim(isset($_POST['neuer_avatar']) ? $_POST['neuer_avatar'] : '');

    $rollenInfoEigen = getUserRollenInfo($conn, $adminBn, $adminPw);

    if ($rollenInfoEigen !== null) {
        $eigeneId = $rollenInfoEigen['benutzer_id'];
        $aktuellerBn = $adminBn;

        // SICHERHEIT: nur gegen die feste Emoji-Liste geprüfte Werte werden gespeichert - verhindert,
        // dass beliebige (evtl. schädliche/lange) Zeichenketten in die Spalte gelangen. Rein kosmetisch,
        // daher bewusst über die defensive nutzerAvatarSpeichern() statt myDb_execute() (siehe deren
        // Kommentar in login_interface.php) - fehlt die Spalte noch, schlägt nur das Avatar-Feature
        // fehl, nie Benutzername/Passwort-Änderung oder gar der Login selbst.
        if ($neuerAvatar !== '' && in_array($neuerAvatar, getProfilAvatarOptionen(), true)) {
            nutzerAvatarSpeichern($conn, $eigeneId, $neuerAvatar);
        }

        if ($neuerBenutzername !== '' && $neuerBenutzername !== $adminBn) {
            // Eindeutigkeit prüfen - gleiche Regel wie bei Benutzername_Aendern oben.
            $stmtPruefen = $conn->prepare("SELECT id FROM System_Benutzer_in WHERE Benutzername = ? AND id != ?");
            $stmtPruefen->bind_param("si", $neuerBenutzername, $eigeneId);
            $stmtPruefen->execute();
            $bereitsVergeben = $stmtPruefen->get_result()->fetch_assoc();
            if (!$bereitsVergeben) {
                $sqlBnAendern = "UPDATE System_Benutzer_in SET Benutzername = ? WHERE id = ?";
                myDb_execute($conn, 0, $adminBn, "edit_account.php Eigenes_Profil_Speichern bn", $sqlBnAendern, array($neuerBenutzername, $eigeneId));
                $aktuellerBn = $neuerBenutzername;
            } else {
                if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
                $_SESSION['flash_error_profil'] = 'Dieser Benutzername ist bereits vergeben.';
            }
        }
        $aktuellesPw = $adminPw;
        if ($neuesPasswort !== '') {
            $sqlPwAendern = "UPDATE System_Benutzer_in SET Passwort = ? WHERE id = ?";
            myDb_execute($conn, 0, $adminBn, "edit_account.php Eigenes_Profil_Speichern pw", $sqlPwAendern, array($neuesPasswort, $eigeneId));
            $aktuellesPw = $neuesPasswort;
        }

        // WICHTIG: bei geändertem Benutzernamen/Passwort muss die eigene Session sofort mitziehen -
        // sonst würde der nächste Request mit den jetzt veralteten Zugangsdaten aus der Session
        // fehlschlagen und die Person wäre faktisch ausgeloggt (siehe gleiches Muster bei Login_Als_User).
        if ($aktuellerBn !== $adminBn || $aktuellesPw !== $adminPw) {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $_SESSION['admin_bn'] = $aktuellerBn;
            $_SESSION['admin_pw'] = $aktuellesPw;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION['flash_success'] = 'Profil aktualisiert.';
    }

// ================================================================================================
// ADMIN-KOMMENTAR ÄNDERN: rein interne Notiz zu einem Nutzer (z.B. echter Name hinter einem
// Pseudonym), nie öffentlich sichtbar. Anders als Benutzername/Passwort bewusst für Admin UND
// Co-Admin freigegeben, nicht nur "echte" Admins - siehe explizite Vorgabe im Chat.
// ================================================================================================
}else if($action == 'Admin_Kommentar_Aendern'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];
    $neuerKommentar = trim($_POST['neuer_kommentar']);

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);
    $istAdminOderCoAdminKommentar = ($rollenInfoAdmin !== null) && ($rollenInfoAdmin['ist_admin'] || $rollenInfoAdmin['ist_co_admin']);

    if ($istAdminOderCoAdminKommentar && $zielBenutzerId > 0) {
        // Leeres Feld -> NULL statt leerem String, damit "kein Kommentar vorhanden" (Standardzustand
        // nach Registrierung) sauber von "Kommentar bewusst geleert" unterscheidbar bleibt.
        $kommentarWert = ($neuerKommentar === '') ? null : $neuerKommentar;
        $sqlKommentarAendern = "UPDATE System_Benutzer_in SET admin_kommentar = ? WHERE id = ?";
        myDb_execute($conn, 0, $adminBn, "edit_account.php Admin_Kommentar_Aendern", $sqlKommentarAendern, array($kommentarWert, $zielBenutzerId));
    }

// ================================================================================================
// "LOGIN ALS USER": ersetzt den früheren Mechanismus, bei dem das Klartext-Passwort der Zielperson
// als verstecktes Formularfeld direkt im HTML-Quelltext der Nutzerübersicht lag (für JEDE Person mit
// Nutzermanagement-Zugriff einsehbar, auch ohne "Passwort anzeigen"-Recht - z.B. ein Co-Admin hätte
// so trotzdem an alle Passwörter kommen können). Jetzt läuft der komplette Vorgang serverseitig: die
// eigenen Zugangsdaten der anfragenden Person werden geprüft, das Ziel-Passwort wird nur intern aus
// der DB gelesen und landet direkt in der Session - nie im HTML/Browser.
// ================================================================================================
}else if($action == 'Login_Als_User'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);
    $istAdminOderCoAdminAcc = ($rollenInfoAdmin !== null) && ($rollenInfoAdmin['ist_admin'] || $rollenInfoAdmin['ist_co_admin']);

    if ($istAdminOderCoAdminAcc && $zielBenutzerId > 0) {
        $stmtZiel = $conn->prepare("SELECT Benutzername, Passwort FROM System_Benutzer_in WHERE id = ?");
        $stmtZiel->bind_param("i", $zielBenutzerId);
        $stmtZiel->execute();
        $zielRow = $stmtZiel->get_result()->fetch_assoc();

        if ($zielRow !== null) {
            // Zweite, serverseitige Prüfung (nicht nur UI-Sichtbarkeit in index.php): ein Co-Admin
            // darf sich nicht als Admin einloggen (könnte darüber sonst z.B. Passwörter anderer
            // Nutzer einsehen/ändern - Rechte, die Co-Admin gezielt NICHT hat).
            $rollenInfoZiel = getUserRollenInfo($conn, $zielRow['Benutzername'], $zielRow['Passwort']);
            $zielIstAdmin = ($rollenInfoZiel !== null) && $rollenInfoZiel['ist_admin'];
            if (!$zielIstAdmin || $rollenInfoAdmin['ist_admin']) {
                if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
                $_SESSION['admin_bn'] = $zielRow['Benutzername'];
                $_SESSION['admin_pw'] = $zielRow['Passwort'];
            }
        }
    }

    // Eigener Redirect (nicht über die Nutzermanagement-Sammelliste unten): landet wie beim alten
    // Mechanismus direkt auf der Startseite, jetzt eingeloggt als die Zielperson (per Session).
    $test_turnier_id = $_GET['test_turnier_id'];
    if($test_turnier_id==NULL){
        header("Location: /");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id");
    }
    exit;

// ================================================================================================
// NUTZER LÖSCHEN - Admin und Co-Admin dürfen grundsätzlich Nutzer löschen, ABER: Admins dürfen
// Admins und Co-Admins löschen, Co-Admins dürfen WEDER Admins NOCH andere Co-Admins löschen
// (nur "einfache" Rollen wie Autor*in/Turniermaster/etc.). Serverseitig geprüft (nicht nur über die
// Sichtbarkeit des Buttons in index.php), damit ein Co-Admin die Einschränkung nicht per direktem
// POST-Request umgehen kann. Genau wie bei Login_Als_User wird die Ziel-Rolle über
// getUserRollenInfo() auf ist_admin/ist_co_admin geprüft (identitätsbasiert über Rollen-ID 1/2, nicht
// über Flags - die Flags rechte_neue_co_admins etc. sind bei Admin UND Co-Admin gleichzeitig gesetzt
// und würden hier nicht zwischen beiden unterscheiden).
// ================================================================================================
}else if($action == 'Benutzer_Loeschen'){
    $adminBn = $_POST['admin_bn'];
    $adminPw = $_POST['admin_pw'];
    $zielBenutzerId = (int)$_POST['ziel_benutzer_id'];

    $rollenInfoAdmin = getUserRollenInfo($conn, $adminBn, $adminPw);
    $istAdminOderCoAdminLoeschen = ($rollenInfoAdmin !== null) && ($rollenInfoAdmin['ist_admin'] || $rollenInfoAdmin['ist_co_admin']);

    // Niemand kann sich selbst löschen (verhindert versehentliches Aussperren, z.B. der letzte Admin).
    if ($istAdminOderCoAdminLoeschen && $zielBenutzerId > 0 && $zielBenutzerId !== $rollenInfoAdmin['benutzer_id']) {
        $stmtZiel = $conn->prepare("SELECT Benutzername, Passwort FROM System_Benutzer_in WHERE id = ?");
        $stmtZiel->bind_param("i", $zielBenutzerId);
        $stmtZiel->execute();
        $zielRow = $stmtZiel->get_result()->fetch_assoc();

        if ($zielRow !== null) {
            $rollenInfoZiel = getUserRollenInfo($conn, $zielRow['Benutzername'], $zielRow['Passwort']);
            $zielIstAdmin = ($rollenInfoZiel !== null) && $rollenInfoZiel['ist_admin'];
            $zielIstCoAdmin = ($rollenInfoZiel !== null) && $rollenInfoZiel['ist_co_admin'];
            $darfLoeschen = $rollenInfoAdmin['ist_admin'] || (!$zielIstAdmin && !$zielIstCoAdmin);

            if ($darfLoeschen) {
                // Rollen-Zuordnungen zuerst entfernen (Fremdschlüssel-Beziehung), dann den Nutzer selbst.
                try {
                    $sqlRelLoeschen = "DELETE FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ?";
                    myDb_execute($conn, 0, $adminBn, "edit_account.php Benutzer_Loeschen Rollen", $sqlRelLoeschen, array($zielBenutzerId));
                } catch (Throwable $e) {
                    // Relation-Tabelle (noch) nicht vorhanden
                }
                $sqlLoeschen = "DELETE FROM System_Benutzer_in WHERE id = ?";
                myDb_execute($conn, 0, $adminBn, "edit_account.php Benutzer_Loeschen", $sqlLoeschen, array($zielBenutzerId));
            }
        }
    }
}

//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$test_turnier_id = $_GET['test_turnier_id'];
if ($action === 'Eigenes_Profil_Speichern') {
    if($test_turnier_id==NULL){
        header("Location: /#account_profil");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id#account_profil");
    }
    exit;
}
$nutzermanagementActions = ['admin_erstellt_nutzer', 'Nutzer_Rollen_Speichern', 'Benutzername_Aendern', 'Admin_Kommentar_Aendern', 'Benutzer_Loeschen'];
if (in_array($action, $nutzermanagementActions, true)) {
    // nm_scroll_zu: sagt der Nutzermanagement-Seite nach dem Reload, zu welcher Nutzer-Karte sie
    // automatisch scrollen und sie kurz aufklappen/hervorheben soll - erspart das manuelle
    // Wiedersuchen des gerade bearbeiteten Nutzers in der Liste (siehe Chat). $zielBenutzerId wird von
    // jeder der obigen Aktionen gesetzt (nur die tatsächlich ausgeführte Aktion beeinflusst hier
    // etwas, da pro Request immer nur ein einziger $action-Zweig läuft).
    $nmScrollZu = (isset($zielBenutzerId) && $zielBenutzerId > 0) ? (int)$zielBenutzerId : null;
    if($test_turnier_id==NULL){
        header("Location: /" . ($nmScrollZu !== null ? "?nm_scroll_zu=$nmScrollZu" : '') . "#backstage_nutzermanagement");
    }else{
        header("Location: /?test_turnier_id=$test_turnier_id" . ($nmScrollZu !== null ? "&nm_scroll_zu=$nmScrollZu" : '') . "#backstage_nutzermanagement");
    }
}else if($test_turnier_id==NULL){
    header("Location: ../#pausenraum");
}else{
    header("Location: ../#pausenraum?test_turnier_id=$test_turnier_id");
}
?>

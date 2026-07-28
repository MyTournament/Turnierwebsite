<?php
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
include_once '../variables.php';

//##########################################################
//LOGIN
include_once 'login_interface.php';
//##########################################################

$action = $_POST['action'];
if($action == NULL){
	$action = $_GET['action'];
}
$websiteId = 1; //$_POST['websiteId'];
if($action == 'take_offline'){
	$TurnierID = $_POST['TurnierID'];

	$bn = $_POST['bn'];
	$pw = $_POST['pw'];

	// ================================================================================================
	// RECHTE-AUDIT: teams-Flag (Admin/Co-Admin/Turniermaster) - die ganze Funktion ist jetzt ohnehin
	// nur noch für diese Rollen im Backstage-Bereich sichtbar (siehe index.php, Menü-Button
	// "Bullerei kommt"), die Rechte-Prüfung hier soll das exakt widerspiegeln, nicht nur die
	// Sichtbarkeit im Menü.
	// ================================================================================================
	$successfulLogin = 0; //false
	$rollenInfoBullerei = getUserRollenInfo($conn, $bn, $pw);
	if ($rollenInfoBullerei !== null && $rollenInfoBullerei['flags']['teams']) {
		$successfulLogin = 1;
	}

	if ($successfulLogin == 0){ //fehlerhafter Login
		$message = "Login leider nicht erfolgreich! Die Website wurde nicht offline genommen. Versuch es gerne noch einmal.";
		echo "<script type='text/javascript'>alert('$message');</script>";
	}else{
		// War deaktiviert ("aktuell nicht genutzt") - jetzt aktiv, damit der Schalter tatsächlich wirkt
		// (siehe index.php: sperrung=1 leitet auf /bullerei/home.php um).
		$sql = "UPDATE System_Website SET sperrung = ? WHERE id = ?";
		$argArr = [1, $websiteId];
		myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php take_offline", $sql, $argArr);

		//TODO:
		//PER MAIL VERSENDEN
		//include_once '../website_functionalities/send_mail.php';
		//$fromEmail = "kummerkasten@blankiball.de";
		//$name = $_POST['bn'];
		//$message = "";
		//mail_att("kummerkasten@blankiball.de", $fromEmail, "WEBSITE OFFLINE GENOMMEN von ".$name, $message);
	}
	//WEITERLEITUNG ZURÜCK
	header("Location: /");
	exit;
}else if ($action == 'take_online'){
	// ================================================================================================
	// RECHTE-AUDIT (NEU): vorher konnte JEDE Person ohne jeden Login die Website per einfachem GET-
	// Aufruf wieder online schalten (bullenwiederweg.php redirectete direkt hierher). Jetzt genau wie
	// take_offline: erfordert Zugangsdaten mit teams-Flag (Admin/Co-Admin/Turniermaster), die über das
	// (bewusst unauffällige) Login-Formular unten auf bullerei/home.php eingegeben werden.
	// ================================================================================================
	$TurnierID = isset($_POST['TurnierID']) ? (int)$_POST['TurnierID'] : 0;
	$bn = isset($_POST['bn']) ? $_POST['bn'] : '';
	$pw = isset($_POST['pw']) ? $_POST['pw'] : '';

	$rollenInfoBullereiOnline = getUserRollenInfo($conn, $bn, $pw);
	$darfWiederOnlineSchalten = $rollenInfoBullereiOnline !== null && $rollenInfoBullereiOnline['flags']['teams'];

	if (!$darfWiederOnlineSchalten) {
		// Bewusst KEINE Fehlermeldung/kein Hinweis, dass ein Login stattgefunden hat - die Seite bleibt
		// einfach die harmlose Regel-Seite, zurück zum Login-Formular.
		header("Location: /bullerei/home.php");
		exit;
	}

	$sql = "UPDATE System_Website SET sperrung = ? WHERE id = ?";
	$argArr = [0, $websiteId];
	myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php take_online", $sql, $argArr);

	//TODO:
	//PER MAIL VERSENDEN
	//include_once '../website_functionalities/send_mail.php';
	//$fromEmail = "kummerkasten@blankiball.de";
	//$name = $bn;
	//$message = "";
	//mail_att("kummerkasten@blankiball.de", $fromEmail, "WEBSITE WIEDER ONLINE".$name, $message);

	//WEITERLEITUNG ZURÜCK - Website ist jetzt wieder online, ganz normal zur Startseite
	header("Location: /");
	exit;
}

?>

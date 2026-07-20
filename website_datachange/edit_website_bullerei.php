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

	//LOGIN
	include_once 'login_interface.php';
	$bn = $_POST['bn'];
	$pw = $_POST['pw'];

	// ========================================================================
	// RECHTE-AUDIT: CMS-BEARBEITUNG NUR NOCH ÜBER DAS "cms"-FLAG (Autor*in-Rolle)
	// ========================================================================
	// Kein Admin/Co-Admin-Shortcut mehr - siehe edit_content.php für die
	// ausführliche Begründung. Admin/Co-Admin haben das cms-Flag ohnehin gesetzt.
	$successfulLogin = 0; //false
	$rollenInfoBullerei = getUserRollenInfo($conn, $bn, $pw);
	if ($rollenInfoBullerei !== null && $rollenInfoBullerei['flags']['cms']) {
		$successfulLogin = 1;
	}
	//Teams
	/*$teamListeFuerTurnier = getTeamsListeFuerTurnier($conn, $TurnierID);
	$successfulLogin = 0; //false
	while ($row = $teamListeFuerTurnier->fetch_assoc()) {
		if(
			$row['kuerzel'] == $bn and
			$row['password'] == $pw and
			$row['bearbeitungsrechte'] == 1
		){
			$successfulLogin = 1;
		}
	}*/
	


	if ($successfulLogin == 0){ //fehlerhafter Login
		$message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
		echo "<script type='text/javascript'>alert('$message');</script>";
	}else{
		$sql = "UPDATE System_Website SET sperrung = ? WHERE id = ?";
		$argArr = [1, $websiteId];
		//DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php",$sql, $argArr);

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
}else if ($action == 'take_online'){
	$TurnierID = $_POST['TurnierID'];
	$bn = "unknown";

	$sql = "UPDATE System_Website SET sperrung = ? WHERE id = ?";
	$argArr = [0, $websiteId];
	//DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php 2",$sql, $argArr);

	//TODO:
	//PER MAIL VERSENDEN
	//include_once '../website_functionalities/send_mail.php';
	//$fromEmail = "kummerkasten@blankiball.de";
	//$name = $_POST['bn'];
	//$message = "";
	//mail_att("kummerkasten@blankiball.de", $fromEmail, "WEBSITE WIEDER ONLINE".$name, $message);

	//WEITERLEITUNG ZURÜCK
	header("Location: /");
}
	
?> 
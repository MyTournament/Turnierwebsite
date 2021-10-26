<?php
include_once '../database/db_connection.php';
include_once 'edit_interface.php';

//##########################################################
//LOGIN
include_once 'login_interface.php';
//##########################################################

$action = $_POST['action'];
if($action == NULL){
	$action = $_GET['action'];
}
$websiteId = $_POST['websiteId'];
if($action == 'take_offline'){
	if($successfulLogin == 1 || ($successfulLogin == 2 && $teamBearbeitungsrecht == 1)){
		$sql = "UPDATE Website SET sperrung = 1 WHERE id = '$websiteId'";
		myDb_execute($conn, $TurnierID, $bn, $sql, array());

		//PER MAIL VERSENDEN
		include_once '../website_functionalities/send_mail.php';
		$fromEmail = "kummerkasten@REDACTED.de";
		$name = $_POST['bn'];
		$message = "";
		mail_att("kummerkasten@REDACTED.de", $fromEmail, "WEBSITE OFFLINE GENOMMEN von ".$name, $message);
	}
	//WEITERLEITUNG ZURÜCK
	header("Location: /");
}else if ($action == 'take_online'){
	$sql = "UPDATE Website SET sperrung = 0"; //Für alle Websites
	myDb_execute($conn, $TurnierID, "unknown", $sql, array());

	//PER MAIL VERSENDEN
	include_once '../website_functionalities/send_mail.php';
	$fromEmail = "kummerkasten@REDACTED.de";
	$name = $_POST['bn'];
	$message = "";
	mail_att("kummerkasten@REDACTED.de", $fromEmail, "WEBSITE WIEDER ONLINE".$name, $message);

	//WEITERLEITUNG ZURÜCK
	header("Location: /");
}
	
?> 
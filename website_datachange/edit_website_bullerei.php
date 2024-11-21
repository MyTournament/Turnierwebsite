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
	//LOGIN
	$bn = $_POST['bn'];
	$pw = $_POST['pw'];
	$TurnierID = $_POST['TurnierID'];
	$sqlLogin = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' AND fk_rechte <= 5 ORDER BY ID";
	$resultLogin = $conn->query($sqlLogin);
	$successfulLogin = 0; //false
	while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){
		$successfulLogin = 1;
		$rechte = $rowLogin['fk_rechte'];
	}
	if ($successfulLogin == 0){ //fehlerhafter Login
		$message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
		echo "<script type='text/javascript'>alert('$message');</script>";
	}else{
		$sql = "UPDATE System_Website SET sperrung = ? WHERE id = ?";
		$argArr = [1, $websiteId];
		myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php",$sql, $argArr);

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
	myDb_execute($conn, $TurnierID, $bn, "edit_website_bullerei.php 2",$sql, $argArr);

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
<?php

//########################
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
//DATENBANKBACKUP MACHEN
//include_once '../database/db_backup.php';
//########################

//$TeamnameR = $_POST['Teamname'];
//$KuerzelR = $_POST['Kuerzel'];
//$Spieler1R = $_POST['Spieler1'];
//$tel1R = $_POST['tel1'];
//$Spieler2R = $_POST['Spieler2'];
//$tel2R = $_POST['tel2'];
//$Spieler3R = $_POST['Spieler3'];
//$tel3R = $_POST['tel3'];
//if(strlen($Teamname)>2 && strlen($Spieler1)>2){

	$action = $_POST['action'];


	if($action == 'Anmelden'){
		//Tut: https://www.codexworld.com/integrate-captcha-checkbox-with-hcaptcha-php/

		$SECRET_KEY = "0x62d2efE251519B2C6D229442251EbeE6D79E1283";    # replace with your secret key
		$VERIFY_URL = "https://hcaptcha.com/siteverify";
		//echo $_POST['h-captcha-response'];
		# Retrieve token from post data with key 'h-captcha-response'.
		$token = $_POST['h-captcha-response']; //$request.POST_DATA['h-captcha-response'];
		# Build payload with secret key and token.
		$data = array( 
			'secret' => $SECRET_KEY, 
			'response' => $token, 
			'remoteip' => $_SERVER['REMOTE_ADDR'] 
		); 
		// Initialize cURL request 
		// Make POST request with data payload to hCaptcha API endpoint 
		$curlConfig = array( 
			CURLOPT_URL => $VERIFY_URL, 
			CURLOPT_POST => true, 
			CURLOPT_RETURNTRANSFER => true, 
			CURLOPT_POSTFIELDS => $data 
		); 
		$ch = curl_init(); 
		curl_setopt_array($ch, $curlConfig); 
		$response = curl_exec($ch); 
		curl_close($ch); 
		
		// Parse JSON from response. Check for success or error codes 
		$responseData = json_decode($response); 
        
		// If reCAPTCHA response is valid 
		if($responseData->success){//$responseData->success){ //TODO: wieder einkommentieren

			$TurnierID = $_POST['TurnierID']; //die übergebene TurnierID benutzen und nicht die aus variables.php

			//SONDERFALL: WARTELISTE
				//Aktuelle Turnierphase herausfinden - erstmal ID
				$sqlPhase = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
				$resultPhase = $conn->query($sqlPhase);
				while ($rowPhase = $resultPhase->fetch_assoc()) {
					$turnier_phase_ID = $rowPhase['fk_turnier_phase'];
				}
				//Falls Turnierphase = Warteliste -> Teams in entsprechende Warteliste einfügen
				//Warteliste finden
				if($turnier_phase_ID==12){
					$warteliste_ID = 6; //Auffangbecken
					$sqlWarteliste = 'SELECT * FROM `Turnier_Warteliste` WHERE fk_turnier = '. $TurnierID .' ORDER BY ID';
					$resultWarteliste = $conn->query($sqlWarteliste);
					while ($rowWarteliste = $resultWarteliste->fetch_assoc()) {
						$warteliste_ID = $rowWarteliste['id'];
					}

					$bn = "unknown";
					$sql = "INSERT INTO Turnier_Team (fk_warteliste, name, kuerzel, password, mail, woher_erfahren) VALUES (?, ?, ?, ?, ?, ?)";
					$teamID = myDb_execute($conn, $TurnierID, $bn, "edit_teams.php",$sql, array($warteliste_ID, $_POST['Teamname'], $_POST['Kuerzel'], $_POST['Passwort'], $_POST['Mail'], $_POST['woher_erfahren']));
				}
			else{
				$bn = "unknown";
				$sql = "INSERT INTO Turnier_Team (fk_turnier, name, kuerzel, password, mail, woher_erfahren) VALUES (?, ?, ?, ?, ?, ?)";
				$teamID = myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 2",$sql, array($TurnierID, $_POST['Teamname'], $_POST['Kuerzel'], $_POST['Passwort'], $_POST['Mail'], $_POST['woher_erfahren']));
			}
			
			$sql = "INSERT INTO Turnier_Spieler_in (fk_team, name, telefonnummer) VALUES (?, ?, ?)";
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 3",$sql, array($teamID, $_POST['Spieler1'], $_POST['tel1']));
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 4",$sql, array($teamID, $_POST['Spieler2'], $_POST['tel2']));
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 5",$sql, array($teamID, $_POST['Spieler3'], $_POST['tel3']));
			


			//Text für beide Mails vorbereiten
			$infoVomAngemeldetenTeam = "";
			$infoVomAngemeldetenTeam .= "Teamname: " . $_POST['Teamname'] . "\r\n";
			$infoVomAngemeldetenTeam .= "Team-Kürzel: " . $_POST['Kuerzel'] . "\r\n";
			$infoVomAngemeldetenTeam .= "Team-Passwort: " . $_POST['Passwort'] . "\r\n \r\n";
			$infoVomAngemeldetenTeam .= "Spieler 1: " . $_POST['Spieler1'] . " - Telefonnummer: " . $_POST['tel1'] . " \r\n \r\n";
			$infoVomAngemeldetenTeam .= "Spieler 2: " . $_POST['Spieler2'] . " - Telefonnummer: " . $_POST['tel2'] . " \r\n \r\n";
			$infoVomAngemeldetenTeam .= "Spieler 3: " . $_POST['Spieler3'] . " - Telefonnummer: " . $_POST['tel3'] . " \r\n \r\n";

			include_once '../website_functionalities/send_mail.php';

			//PER MAIL VERSENDEN
			//an kummerkasten
			//$fromEmail = "kummerkasten@blankiball.de";
			$name = $_POST['Teamname'];
			$message = "";
			$message .= $infoVomAngemeldetenTeam;
			$message = wordwrap($message, 70, "\r\n");
			$header = 'From: Blankiball Bierball Turnier <kummerkasten@blankiball.de>' . "\r\n" .
				'Reply-To: Blankiball Bierball Turnier <kummerkasten@blankiball.de>' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
			//Verschicken
			mail("kummerkasten@blankiball.de", "Teamregistrierung Blankiball-Turnier", $message, $header);
			

			//an Team
			//$fromEmail = "kummerkasten@blankiball.de";
			$empfaenger = $_POST['Mail'];
			$name = $_POST['Teamname'];
			if($turnier_phase_ID==12){ // Falls Warteliste
				$message2 = "Leider sind die Plaetze des Turniers vorläufig voll. Dein Team wurde der Warteliste hinzugefügt und kann eventuell noch nachrücken. Falls Plaetze frei werden, sagen wir euch Bescheid. \r\n \r\n";
			}else{
				$message2 = "Dein Team wurde erfolgreich für das Blankiball-Turnier registriert! \r\n \r\n";
			}
			$message2 .= "Hier kannst du noch einmal deine Angaben überprüfen und hast euer Team-Passwort auch nochmal zum Abspeichern. \r\n \r\n";
			$message2 .= $infoVomAngemeldetenTeam;
			$message2 .= "Bei Fragen oder Wuenschen, schreib uns gern eine Mail!";

			// Verschicken
			$betreff = 'Der Betreff';
			//$nachricht = "Zeile 1\r\nZeile 2\r\nZeile 3";
			// Falls eine Zeile der Nachricht mehr als 70 Zeichen enthälten könnte,
			// sollte wordwrap() benutzt werden
			$message2 = wordwrap($message2, 70, "\r\n");
			$header = 'From: Blankiball <kummerkasten@blankiball.de>' . "\r\n" .
				'Reply-To: Blankiball <kummerkasten@blankiball.de>' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
			mail($empfaenger, 'Deine Blankiball-Anmeldung', $message2, $header);
			
			//Beide Mails versenden
			//mail_att($team_mail, $fromEmail, "Teamregistrierung Blankiball-Turnier", $message);
			//mail_att("kummerkasten@blankiball.de", $fromEmail, "Neues Team angemeldet: ".$name, $message);

			//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
			$test_turnier_id = $_GET['test_turnier_id'];
			if($test_turnier_id==NULL){
				if($turnier_phase_ID==12){ //WARTELISTE
					header("Location: /#vielendankfuerdeineanmeldung_warteliste");
				}else{
					header("Location: /#vielendankfuerdeineanmeldung");
				}
			}else{
				if($turnier_phase_ID==12){ //WARTELISTE
					header("Location: /?test_turnier_id=$test_turnier_id#vielendankfuerdeineanmeldung_warteliste");
				}else{
					header("Location: /?test_turnier_id=$test_turnier_id#vielendankfuerdeineanmeldung");
				}
			}
		}else{
			//echo "Du Keck, du musst das Captcha ausfüllen, damit dein Team angemeldet wird. Für die Dummheit designen wir dir die Seite hier nichtmal schön. Klicke einfach auf Zurück in deinem Browser und probiere es noch einmal...";
			echo '
				<!DOCTYPE html>
				<html lang="de">
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<title>Fehlermeldung</title>
					<style>
						body {
							display: flex;
							justify-content: center;
							align-items: center;
							height: 100vh;
							margin: 0;
							background-color: #f0f0f0;
							font-family: Arial, sans-serif;
						}
						.message {
							text-align: center;
							max-width: 600px;
							padding: 20px;
							background-color: #fff;
							box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
							border-radius: 10px;
						}
						.message h1 {
							font-size: 2em;
							margin-bottom: 20px;
						}
						.message p {
							font-size: 1.2em;
						}
					</style>
				</head>
				<body>
					<div class="message">
						<h1>Du Keck!</h1>
						<p>Du musst das Captcha ausfüllen, damit dein Team angemeldet wird. Für die Dummheit designen wir dir die Seite hier nichtmal schön. Klicke einfach auf Zurück in deinem Browser und probiere es noch einmal...</p>
					</div>
				</body>
				</html>';
		}

	}else { //Alles was nicht Team registrieren ist braucht Login
		$TurnierID = $_POST['TurnierID'];
		$teamId = $_POST['Team_zum_abmelden'];

		//LOGIN
		include_once 'login_interface.php';
		$bn = $_POST['bn'];
		$pw = $_POST['pw'];

		//Benutzer
		$benutzerliste = getBenutzerListe($conn);
		$successfulLogin = 0; //false
		while ($row = $benutzerliste->fetch_assoc()) {
			if(
				$row['Benutzername'] == $bn and
				$row['Passwort'] == $pw and
				$row['fk_rechte'] <= 10
			){
				$successfulLogin = 1;
				$rechte = $row['fk_rechte'];
			}
		}
		//Teams
		//TODO: Team-Login hab ich erstmal rausgenommwen weil braucht es eigentlich nicht - riskant
		//FALL: Team-Login -> Bearbeitungsrechte nur für eigene Begegnungen
		/*$teamListeFuerTurnier = getTeamsListeFuerTurnier($conn, $TurnierID);
		$successfulLogin = 0; //false
		while ($row = $teamListeFuerTurnier->fetch_assoc()) {
			if(
				$row['kuerzel'] = $bn and
				$row['password'] == $pw and
				$row['id'] == $teamId and
				$row['bearbeitungsrechte'] == 1
			){
				$successfulLogin = 2;
			}
		}*/
	
		
		
		if($action == 'Abmelden'){
			if($teamId == "auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss"){
				$message = "Du musst schon ein Team auswählen du Keck";
				//echo "<script type='text/javascript'>alert('$message');</script>";
			}else{
				//LOGIN ÜBERPRÜFEN
				if ($successfulLogin == 0){ //fehlerhafter Login
					$message = "Login leider nicht erfolgreich!";
					//echo "<script type='text/javascript'>alert('$message');</script>";
				}else if($successfulLogin == 1 || $successfulLogin == 2){ //Account-Login oder Team-Login && Spiel gehört zu Team
					//$sql = "DELETE FROM Spieler WHERE fk_team = ?";
					//myDb_execute($conn, $TurnierID, $bn, "edit_teams.php x",$sql, array($teamId));
	
					//$sql = "DELETE FROM Team WHERE id = ?";
					$sql = "UPDATE Turnier_Team SET geloescht = 1 WHERE id = ?";
					myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 6",$sql, array($teamId));
					
					$message = "Deine Abmeldung war erfolgreich!";
					//echo "<script type='text/javascript'>alert('$message');</script>";
	
					//echo "<script>console.log('Das Team wurde erfolgreich abgemeldet. $successfulLogin')</script>";
				}else{ //Team-Login
					//echo "<script>console.log('Das Team wurde nicht abgemeldet.')</script>";
					$message = "Falsche Login-Daten! Das Team wurde nicht abgemeldet..";
					//echo "<script type='text/javascript'>alert('$message');</script>";
				}
			}

			//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
			$test_turnier_id = $_GET['test_turnier_id'];
			if($test_turnier_id==NULL){
				header("Location: /#teams");
			}else{
				header("Location: /?test_turnier_id=$test_turnier_id#teams");
			}	

		}else if($action == 'change_group'){
			$teamId = $_POST['team'];
			$gruppeId = $_POST['gruppe'];
			$sql = "UPDATE Turnier_Team SET fk_gruppe = ? WHERE id = ?";
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 7",$sql, array($gruppeId, $teamId));
			
			//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
			$test_turnier_id = $_GET['test_turnier_id'];
			if($test_turnier_id==NULL){
				header("Location: /#login");
			}else{
				header("Location: /?test_turnier_id=$test_turnier_id#login");
			}

		}else if($action == 'rechte_weg'){
			$teamId = $_POST['team'];
			$sql = "UPDATE Turnier_Team SET bearbeitungsrechte = 0 WHERE id = ?";
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 8",$sql, array($teamId));
			
			//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
			$test_turnier_id = $_GET['test_turnier_id'];
			if($test_turnier_id==NULL){
				header("Location: /#login");
			}else{
				header("Location: /?test_turnier_id=$test_turnier_id#login");
			}

		}else if($action == 'rechte_geben'){
			$teamId = $_POST['team'];
			$sql = "UPDATE Turnier_Team SET bearbeitungsrechte = 0 WHERE id = ?";
			myDb_execute($conn, $TurnierID, $bn, "edit_teams.php 9",$sql, array($teamId));
			
			//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
			$test_turnier_id = $_GET['test_turnier_id'];
			if($test_turnier_id==NULL){
				header("Location: /#login");
			}else{
				header("Location: /?test_turnier_id=$test_turnier_id#login");
			}

		}


	}
	
?> 

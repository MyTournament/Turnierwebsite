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
				$sql = "INSERT INTO Turnier_Team (fk_warteliste, name, kuerzel, password, mail) VALUES (?, ?, ?, ?, ?)";
				$teamID = myDb_execute($conn, $TurnierID, $bn, $sql, array($warteliste_ID, $_POST['Teamname'], $_POST['Kuerzel'], $_POST['Passwort'], $_POST['Mail']));
			}
		else{
			$bn = "unknown";
			$sql = "INSERT INTO Turnier_Team (fk_turnier, name, kuerzel, password, mail) VALUES (?, ?, ?, ?, ?)";
			$teamID = myDb_execute($conn, $TurnierID, $bn, $sql, array($TurnierID, $_POST['Teamname'], $_POST['Kuerzel'], $_POST['Passwort'], $_POST['Mail']));
		}
			
		$sql = "INSERT INTO Turnier_Spieler_in (fk_team, name, telefonnummer) VALUES (?, ?, ?)";
		myDb_execute($conn, $TurnierID, $bn, $sql, array($teamID, $_POST['Spieler1'], $_POST['tel1']));
		myDb_execute($conn, $TurnierID, $bn, $sql, array($teamID, $_POST['Spieler2'], $_POST['tel2']));
		myDb_execute($conn, $TurnierID, $bn, $sql, array($teamID, $_POST['Spieler3'], $_POST['tel3']));


		//Text für beide Mails vorbereiten
		$infoVomAngemeldetenTeam = "";
		$infoVomAngemeldetenTeam .= "Teamname: " . $_POST['Teamname'] . "\r\n";
		$infoVomAngemeldetenTeam .= "Team-Kuerzel: " . $_POST['Kuerzel'] . "\r\n";
		$infoVomAngemeldetenTeam .= "Team-Passwort: " . $_POST['Passwort'] . "\r\n \r\n";
		$infoVomAngemeldetenTeam .= "Spieler 1: " . $_POST['Spieler1'] . " - Telefonnummer: " . $_POST['tel1'] . " \r\n \r\n";
		$infoVomAngemeldetenTeam .= "Spieler 2: " . $_POST['Spieler2'] . " - Telefonnummer: " . $_POST['tel2'] . " \r\n \r\n";
		$infoVomAngemeldetenTeam .= "Spieler 3: " . $_POST['Spieler3'] . " - Telefonnummer: " . $_POST['tel3'] . " \r\n \r\n";

		include_once '../website_functionalities/send_mail.php';

		//PER MAIL VERSENDEN
		//an kummerkasten
		$fromEmail = "kummerkasten@blankiball.de";
		$name = $_POST['Teamname'];
		$message = "";
		$message .= $infoVomAngemeldetenTeam;
		mail_att("kummerkasten@blankiball.de", $fromEmail, "Neues Team angemeldet: ".$name, $message);

		//an Team
		$fromEmail = "kummerkasten@blankiball.de";
		$team_mail = $_POST['Mail'];
		$name = $_POST['Teamname'];
		if($turnier_phase_ID==12){ // Falls Warteliste
			$message = "Leider sind die Plaetze des Turniers vorlaeufig voll. Dein Team wurde der Warteliste hinzugefuegt und kann eventuell noch nachruecken. Falls Plaetze frei werden, sagen wir euch Bescheid. \r\n \r\n";
		}else{
			$message = "Dein Team wurde erfolgreich fuer das Blankiball-Turnier registriert! \r\n \r\n";
		}
		$message .= "Hier kannst du noch einmal deine Angaben ueberpruefen. (Umlaute und Emojis werden eventuell nicht richtig dargestellt -> gerade wenn du welche im Passwort haben solltest, wird dein Passwort hier moeglicherweise falsch angezeigt, funktioniert aber in der urspruenglichen Version) \r\n \r\n";
		$message .= $infoVomAngemeldetenTeam;
		$message .= "Bei Fragen oder Wuenschen, schreib uns gern eine Mail!";
		mail_att($team_mail, $fromEmail, "Teamregistrierung Blankiball-Turnier", $message);

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

	}else { //Alles was nicht Team registrieren ist braucht Login
		//LOGIN
		$bn = $_POST['bn'];
		$pw = $_POST['pw'];
		$successfulLogin = 0; //false

		$teamId = $_POST['Team_zum_abmelden'];

		//FALL: Team-Login -> Bearbeitungsrechte nur für eigene Begegnungen
		$sqlLogin = "SELECT * FROM `Turnier_Team` WHERE id = '$teamId' AND kuerzel = '$bn' AND password = '$pw' ORDER BY ID";
		$resultLogin = $conn->query($sqlLogin);
		while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){
			$successfulLogin = 2;
			//echo "<script>console.log('Du bist eingeloggt mit dem richtigen Team.')</script>";
		}
		
		//FALL: Account-Login -> Bearbeitungsrechte für alle Begegnungen
		$sqlLoginAccount = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' AND fk_rechte <= 10 ORDER BY ID"; //
		$resultLoginAccount = $conn->query($sqlLoginAccount);
		while ( !empty( $rowLoginAccount = $resultLoginAccount->fetch_assoc() ) ){
			$successfulLogin = 1;
			//echo "<script>console.log('Du bist eingeloggt mit deinem Account und hast damit volle Bearbeitungsrechte.')</script>";
		}
		
		
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
					//myDb_execute($conn, $TurnierID, $bn, $sql, array($teamId));
	
					//$sql = "DELETE FROM Team WHERE id = ?";
					$sql = "UPDATE Turnier_Team SET fk_turnier = 5 WHERE id = ?"; //das ist das Turnier für abgemeldete Teams
					myDb_execute($conn, $TurnierID, $bn, $sql, array($teamId));
					
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
			myDb_execute($conn, $TurnierID, $bn, $sql, array($gruppeId, $teamId));
			
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
			myDb_execute($conn, $TurnierID, $bn, $sql, array($teamId));
			
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
			myDb_execute($conn, $TurnierID, $bn, $sql, array($teamId));
			
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
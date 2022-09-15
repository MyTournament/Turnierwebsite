<!DOCTYPE HTML>
<!--
 _____ _                  _                       _____                      _       ___   _ _  __     
/  ___| |                | |                     |  ___|                    | |     /   | | (_)/ _|    
\ `--.| |_ ___ _ __ _ __ | |__  _   _ _ __ __ _  | |____  ___ __   ___  _ __| |_   / /| | | |_| |_ ___ 
 `--. \ __/ _ \ '__| '_ \| '_ \| | | | '__/ _` | |  __\ \/ / '_ \ / _ \| '__| __| / /_| | | | |  _/ _ \
/\__/ / ||  __/ |  | | | | |_) | |_| | | | (_| | | |___>  <| |_) | (_) | |  | |_  \___  | | | | ||  __/
\____/ \__\___|_|  |_| |_|_.__/ \__,_|_|  \__, | \____/_/\_\ .__/ \___/|_|   \__|     |_/ |_|_|_| \___|
                                           __/ |           | |                                         
                                          |___/            |_|                                         
-->
<html>
	<head>
		<title>Blankiball Bierball Turnier</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta name="description" content="Merke dir - Sternburg Bier">
        <meta name="author" content="Hermann Blankenstein">
		<link rel="stylesheet" href="assets/css/main.css" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
        <link href="images/icon/sterni1.png" rel="shortcut icon" type="image/png">
	</head>
	<body class="is-preload">

<!-- Wrapper -->
<div id="wrapper">

<?php

//##################################################################
//IMPORT PHP-DOCS
include_once 'database/db_connection.php'; //Datenbanklogin
include_once 'variables.php'; //Variablen einbinden (Turniernummer)
include_once 'database/db_update.php'; 
db_update($conn, $TurnierID); //db_update.php AUSFÜHREN
foreach (glob("website_print_functions/*.php") as $filename){
    include_once $filename;
}
//##################################################################

//Anmeldung
$bn = $_POST["bn"];
$pw = $_POST["pw"];

$LoggedIn = False;
foreach ($conn->query("SELECT * FROM System_Benutzer_in WHERE fk_rechte <= 15") as $row) {
    if ($bn == $row["Benutzername"] && $pw == $row["Passwort"]) {
        $LoggedIn = True;
    }
}

//TEST-MODUS
include_once 'website_functionalities/test_turnier_mode.php';
if($test_turnier_id == 0){ //FALL: NORMALES TURNIER
    echo"<form method='post' action='#'>
    <button  name='content' class='button primary'>Testmodus starten</button> 
    <select name='test_turnier_id'>
        <option value='0'><i>$TurnierName</i></option>";
        
        foreach ($testTurniere as &$value){
            $index = $value[0];
            $tName = $value[2];
            echo "<option value=$index>$tName</option>";
        }
        echo"
    </select>
    <!-- <input type='hidden' name='test_turnier_id' value='1'/> -->
    <input type='hidden' name='bn' value='$bn'/>
    <input type='hidden' name='pw' value='$pw'/>
</form>";
}


?>

<!-- ########################## -->
<!-- ########  HEADER  ######### -->
<!-- ########################## -->
<header id="header"> 
    <div > <!-- class="logo" -->
        <img src="images/hermann_logo/pilsener.png" width="200" height=auto border="10" alt="Home">
    </div>
    <div class="content">
        <div class="inner">
            <h1>Backstage-Bereich</h1>
        </div>
    </div>
    <!--<div style="display: block; margin-top: 40vh;" id="LogIn">-->
    <?php
    //echo "</div>";
    if ($LoggedIn) {
        echo "<h3><i>Login erfolgreich</i></h3>";
        include_once 'database/db_backup.php'; //DB-Backup machen -> wichtig dass das nur im eingeloggten Modus passiert weil db_backup den Pfad zur Backupdatei ausgibt und man mit der Datei die gesamte DB bekommen könnte
        echo"<div class='height: 50px;'>
            <div class='height: 1px;'>
                <nav>
                    <ul class='height: 100px;'>
                        <!-- <li><a href='/'>Startseite</a></li> -->
                        <li><a href='#info'>Infos</a></li>
                        <li><a href='#verlauf'>Verlauf</a></li>
                        <li><a href='#daten_bearbeiten'>Edit Data</a></li>
                    </ul>
                </nav>  
            </div>
        </div>";
        
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<a href='/' class='button'>Zurück zur Startseite</a>";
        }else{ //Testturniere
            echo "<a href='/?test_turnier_id=$test_turnier_id' class='button'>Zurück zur Startseite</a>";
        }
        /*echo"
        <form action='/' method='POST'>
            <input type='hidden' name='test_turnier_id' value='$test_turnier_id'/>
            <button type='submit'>Zurück zur Startseite</button>
            
        </form>
        ";*/
        
        

    echo"
    </header>

    <!-- Main -->
    <div id='main'>
        <!-- ########  Daten bearbeiten  ######### -->
            <article id='daten_bearbeiten'>
                <div style='text-align: center'> 
                    <h2>Daten bearbeiten</h2> <!--class='major'-->
                    <a href='#teams_bearbeiten' class='button primary'>Teams bearbeiten</a>
                    <br/><br/>
                    <a href='#begegnungen_bearbeiten' class='button primary'>Begegnungen bearbeiten</a>
                    <br/><br/>
                    <a href='#platzhalter' class='button primary'>Beliebige Daten bearbeiten</a>
                    <br/><br/>
                    <a href='#turnier_phase' class='button primary'>Turnierphase</a>
                    <br/><br/>
                    <a href='#bullerei_kommt' class='button primary'>Bullerei kommt</a>
                    <h5><br/></h5>
                    <h5><br/></h5>
                    <a href='#' class='button'>Zurück</a>
                    <h5><br /></h5>  
                </div>
            </article>

            
            <article id='verlauf'>
                <div style='text-align: center'> 
                    <h2>Verlauf</h2> <!--class='major'-->
                    <a href='#traffic' class='button primary'>Traffic</a>
                    <br/><br/>
                    <a href='#letzte_aenderung' class='button primary'>DB-Verlauf</a>
                    <h5><br/></h5>
                    <h5><br/></h5>
                    <a href='#' class='button'>Zurück</a>
                    <h5><br /></h5>  
                </div>
            </article>


        <!-- ########  Begegnungen bearbeiten  ######### -->
            <article id='begegnungen_bearbeiten'>
                <h1>Begegnungen bearbeiten</h1> <!--class='major'-->
                <h2 class='major'>Hinzufügen</h2>
                    <label for='demo-category'>Team 1 wählen:</label>
                        <select name='Phase' id='phase'>           
                            <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
                            //Turnier-Finalstufen finden
                            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                                $turnier_phase = $rowTurnierPhase['name'];
                                $turnier_phase_ID = $rowTurnierPhase['id'];
                                echo "<option value=$turnier_phase_ID>$turnier_phase</option>";
                            }
                    echo "</select>
                    <label for='demo-category'>Team 2 wählen:</label>
                        <select name='Phase' id='phase'>           
                            <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
                            //Turnier-Finalstufen finden
                            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                                $turnier_phase = $rowTurnierPhase['name'];
                                $turnier_phase_ID = $rowTurnierPhase['id'];
                                echo "<option value=$turnier_phase_ID>$turnier_phase</option>";
                            }
                    echo "</select>
                    <label for='demo-category'>Finallevel wählen:</label>
                        <select name='Phase' id='phase'>           
                            <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
                            //Turnier-Finalstufen finden
                            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                                $turnier_phase = $rowTurnierPhase['name'];
                                $turnier_phase_ID = $rowTurnierPhase['id'];
                                echo "<option value=$turnier_phase_ID>$turnier_phase</option>";
                            }
                    echo "</select>
                    <br/><br/>
                <h2 class='major'>Löschen</h2>
                <select name='Phase' id='phase'>           
                            <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
                            //Turnier-Finalstufen finden
                            $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition ASC, id ASC';
                            $resultBegegnung = $conn->query($sqlBegegnung);
                            while ($rowBegegnung = $resultBegegnung->fetch_assoc()) {
                                $begegnungID = $rowBegegnung['id'];
                                $ko_finallevel = $rowBegegnung['ko_finallevel'];
                                //HEIMTEAM
                                $fk_heimteam = $rowBegegnung['fk_heimteam'];
                                $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE id = '. $fk_heimteam .'';
                                $resultTeam = $conn->query($sqlTeam);
                                while ($rowTeam = $resultTeam->fetch_assoc()) {
                                    $team1 = $rowTeam['name'];
                                    $team1_kuerzel = $rowTeam['kuerzel'];
                                }
                                //AUSWÄRTSTEAM
                                $fk_auswaertsteam = $rowBegegnung['fk_auswaertsteam'];
                                $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE id = '. $fk_auswaertsteam .'';
                                $resultTeam = $conn->query($sqlTeam);
                                while ($rowTeam = $resultTeam->fetch_assoc()) {
                                    $team2 = $rowTeam['name'];
                                    $team2_kuerzel = $rowTeam['kuerzel'];
                                }
                                
                                echo "<option value=$begegnungID>$ko_finallevel | $team1 ($team1_kuerzel) - $team2 ($team2_kuerzel)</option>";
                            }
                    echo "</select>
                
                <a href='#daten_bearbeiten' class='button'>Zurück</a>
                <h5><br /></h5>  
            </article>

        <!-- ########  PLATZHALTER  ######### -->
            <article id='platzhalter'>
                <h2>Hier gibt es (noch) nichts zu sehen.</h2> <!--class='major'-->
                <span class='image main'><img src='images/fotos/lennard_REDACTED.JPG' alt='' /></span>
                <p>Geh woanders hin.</p>
                <a href='#' class='button'>Zurück</a>
                <h5><br /></h5>  
            </article>   
        
        <!-- ABMELDEN -->
        <article id='abmelden'>
            "; printTeamAbmelden($conn, $TurnierID); echo "
            <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
            <p></br></p>
        </article>

        <!-- BULLEREI KOMMT -->
        <article id='bullerei_kommt'>
            <div style='text-align: center'>";
                printBullereiKommt($conn, $websiteId);
                echo"
                <a href='#' class='button'>Zurück</a>
                <h5><br /></h5>  
            </div>
        </article>


        <!-- ########################## -->
        <!-- ########  Telefonnummern  ######### -->
        <!-- ########################## -->
        <article id='tel'>
            <a href='#info' class='button'>Zurück</a>
            <h5><br /></h5>
            <h1>Telefonnummern</h1> <!--class='major'-->
            <h3>Hier eine Übersicht aller Telefonnumern, um alle in eine Whatsapp-Gruppe hinzuzufügen.</h3>
            <h5><br /></h5>
            <form action='website_functionalities/vcard.php' method='POST'>
                <button id='btn_login_Absenden' class='button primary' value='Absenden' type='submit'>Kontakte aufs Handy importieren</button>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
            </form>
            <h5><br /></h5>
            <p>Bitte sensibel mit den Daten umgehen! Haben bisher noch nicht mal eine Datenschutzerklärung und keine Lust auf Stress^^</p>
            <h5><br /></h5>";
            $sqlTelefon = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team IN (SELECT id FROM Turnier_Team WHERE fk_turnier = '. $TurnierID .') ORDER BY ID DESC';
            $resultTelefon = $conn->query($sqlTelefon);
            while ($rowTelefon = $resultTelefon->fetch_assoc()) {
                $spielername = $rowTelefon['name'];
                $telefonnummer = $rowTelefon['telefonnummer'];
                $teamID = $rowTelefon['fk_team'];
                $timestamp = $rowTelefon['timestamp'];
                    $sqlTeamname = 'SELECT * FROM `Turnier_Team` WHERE id = '. $teamID .'';
                    $resultTeamname = $conn->query($sqlTeamname);
                    while ($rowTeamname = $resultTeamname->fetch_assoc()) {
                        $teamname = $rowTeamname['name'];
                    }
                echo"<p><b>$spielername</b> ( Telefonnummer: <b>$telefonnummer</b> | Team: <b>$teamname</b> | Spieler registriert seit: $timestamp )</p>";
            }
            echo"<a href='#info' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>
        
        <!-- ########################## -->
        <!-- ########  ER-DIAGRAMM  ######### -->
        <!-- ########################## -->
        <article id='er_diagram'>
            <a href='#info' class='button'>Zurück</a>
            </br></br>
            <h2>Unsere Datenbank als ER-Diagramm</h2> <!--class='major'-->
            <p>Hinweis: Einige Attribut-Namen haben sich mittlerweile geändert, es sind neue dazugekommen und einige wurden entfernt. Aber die Grundstruktur stimmt noch.</p>
            <span class='image main'><img src='images/er_diagram.jpg' alt='' /></span>
            <a href='#info' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article> 

        <!-- ########################## -->
        <!-- ########  INFO zur dbupdate  ######### -->
        <!-- ########################## -->
        <article id='info_zur_dbupdate'>
            <h2>Automatische Berechnungen</h2> <!--class='major'-->
            <p>In die Website ist ein Berechnungs-Script integriert, das bei jeder Ausführung der Website einmal durchläuft und alle Datensätze aktualisiert. Hier gibt es einen kurzen Überblick, welche Daten dieses Script verändert.</p>
            <p>...</p>
            <p>...</p>
            <a href='#' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        

        <!-- ########################## -->
        <!-- ########  Teams bearbeiten  ######### -->
        <!-- ########################## -->
        <article id='teams_bearbeiten'>
            <div style='text-align: center'> 
                <h2>Teams bearbeiten</h2> <!--class='major'-->
                <a href='#abmelden' class='button primary'>Team abmelden</a>
                <br/><br/>
                <a href='#changeteam' class='button primary'>Sonstiges (Gruppe, Bearbeitungsrechte)</a>
                <h5><br /></h5>
                <h5><br /></h5>
                <a href='#daten_bearbeiten' class='button'>Zurück</a>
                <h5><br /></h5>  
            </div>
        </article>


        <!-- ########################## -->
        <!-- ########  INFO  ######### -->
        <!-- ########################## -->
        <article id='info'>
            <h2>Infos</h2> <!--class='major'-->
            <a href='#tel' class='button primary'>Telefonnummern</a>
            </br></br>
            <a href='#teampasswort' class='button primary'>Team-Passwörter</a>
            </br></br>
            <a href='#warteliste' class='button primary'>Warteliste</a>
            </br></br>
            <a href='#er_diagram' class='button primary'>ER-Diagramm</a>
            </br></br>
            <a href='#' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        <!-- ########################## -->
        <!-- ########  WARTELISTE  ######### -->
        <!-- ########################## -->
        <article id='warteliste'>
            <h2>Warteliste</h2> <!--class='major'-->";
            
            $sqlWarteliste = 'SELECT * FROM Turnier_Team WHERE fk_warteliste IN (SELECT id FROM `Turnier_Warteliste` WHERE fk_turnier = '. $TurnierID .')';
            $resultWarteliste = $conn->query($sqlWarteliste);
            $zeahler = 1;
            while ($rowWarteliste = $resultWarteliste->fetch_assoc()) {
                $a=$rowWarteliste["name"];
                $teamId = $rowWarteliste["id"];
                $b=printKuerzelWithLink($conn, $teamId);	
                $ausgabeString = "";				
                $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";					
                $sqlSpieler = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team = ' . $rowWarteliste["id"] . ' ORDER BY ID'; //WHERE Freischaltung = 1    
                $resultSpieler = $conn->query($sqlSpieler);
                while ($rowSpieler = $resultSpieler->fetch_assoc()) {
                    $x=$rowSpieler["name"];
                    $ausgabeString .=  " $x ";
                    $ausgabeString .=  "&#x007C;";
                }
                $zeahler++;
                $ausgabeString = substr($ausgabeString, 0, -8);
                echo "<li>$ausgabeString</li>";
            }

            echo"
            <a href='#info' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>


        <!-- ########################## -->
        <!-- ########  TEAM-PASSWORT  ######### -->
        <!-- ########################## -->
        <article id='teampasswort'>
            <h2>Team-Passwörter</h2> <!--class='major'-->";
            
            $sqlPasswort = 'SELECT * FROM Turnier_Team WHERE fk_turnier = '. $TurnierID .'';
            $resultPasswort = $conn->query($sqlPasswort);
            $zeahler = 1;
            while ($rowPasswort = $resultPasswort->fetch_assoc()) {
                $a=$rowPasswort["name"];
                $teamId = $rowPasswort["id"];
                $passwort = $rowPasswort["password"];
                $b=printKuerzelWithLink($conn, $teamId);	
                $ausgabeString = "";				
                $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";					
                $zeahler++;
                echo "<li>$ausgabeString | Passwort: $passwort</li>";
            }

            echo"
            <h5><br /></h5> 
            <a href='#info' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        <!-- ########################## -->
        <!-- ########  TURNIER-PHASE  ######### -->
        <!-- ########################## -->
        <article id='turnier_phase'>
            <a href='#daten_bearbeiten' class='button'>Zurück</a>
            <h5><br /></h5>  
            <h1>Turnier-Phase</h1> <!--class='major'-->
            <form action='website_datachange/edit_variables.php' method='POST' onSubmit='return checkAGB2()'>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <div class='field'>
                    <h3>Bitte den Abschnitt komplett lesen, bevor du die Turnierphase änderst!</h3>
                    <p>Im Backend wird bei jedem Aufruf der Website ein php-Script ausgeführt, was die gesamte Datenbank durchgeht und überprüft, ob etwas geupdated werden muss. Das sind Dinge wie ein Team, was noch keine Gruppe bekommen hat, einer Gruppe zuordnen oder das Geiwnnerteam einer Finalstufe in die nächste Finalstufe weiterleiten.</p>
                    <p>Durch dieses Script entstehen aber einige Gefahren. Es könnte zum Beispiel passieren, dass sich während dem Halbfinale noch ein Team registiert und sich dadurch der gesamte Turnierbaum verschiebt. Wenn eine bestimmte Zahl von Teams überschritten wird, entscheidet die Datenbank auch, zum Beispiel die Anzahl der Gruppen zu ändern, dabei werden alle Teams neu in Gruppen zusammengewürfelt. Aus diesem Grund gibt es auf dieser Seite hier einen 'Schalter', der nur bestimmte Funktionen des php-Scripts zulässt.</p>
                    <p>Hier erhälst du einen kurzen Überblick, was die einzelnen Positionen des 'Schalters' bedeuten.</p>
                    <h3></h3>";
                    $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                    $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                    while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                        $turnier_phase_name = $rowTurnierPhase['name'];
                        $turnier_phase_description = $rowTurnierPhase['description'];
                        echo"<label for='demo-category'><h3>'$turnier_phase_name'</h3></label>";
                        echo"<p>$turnier_phase_description</p>";
                    }
                    //Aktuelle Turnierphase herausfinden - erstmal ID
                    $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                    $resultTurnier = $conn->query($sqlTurnier);
                    while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                        $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                    }
                    //Jetzt Name dazu finden
                    $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` WHERE id = '. $turnier_phase_ID .' ORDER BY logical_order';
                    $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                    while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                        $turnier_phase_name = $rowTurnierPhase['name'];
                    }
                    echo"<label for='demo-category'>Aktuelle Turnierphase: <h2 style='color: green'><i>$turnier_phase_name</i></h2></label>
                    <label for='demo-category'>Neue Turnierphase auswählen:</label>
                    <select name='Phase' id='phase'>           
                        <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
                        //Turnier-Finalstufen finden
                        $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                        $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                        while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                            $turnier_phase = $rowTurnierPhase['name'];
                            $turnier_phase_ID = $rowTurnierPhase['id'];
                            echo "<option value=$turnier_phase_ID>$turnier_phase</option>";
                        }
                echo"</select>
                </div>
                <label for='demo-category'>Login</label>
                <input type='text' id='bnid' name='bn' class='Eingabe' placeholder='Benutzername' style='color: white' required>
                <input type='password' id='pwid' name='pw' class='Eingabe' placeholder='Passwort' style='color: white' required>
                <script type='text/javascript'>
                    function checkAGB2() {
                        if (document.getElementById('demo-human-registergame').checked) {
                            return true;
                        }
                        alert('Du musst unten noch das Häkchen setzen!');
                        return false;
                    }
                </script>  
                <div>
                    <div class='field half'>
                        <input type='checkbox' id='demo-human-registergame' name='demo-human-registergame' unchecked>
                        <label for='demo-human-registergame'>Ich habe die Regeln der Datenbank gelesen und verstanden.</label>
                        <h5><br/></h5>  
                    </div>
                </div>
                <ul class='actions'>
                    <li><input name='action' type='submit' value='Tunierphase ändern' class='primary' /></li>
                    <li><input name='action' type='reset' value='Abbrechen' /></li>
                </ul>
            </form>


            <h5><br /></h5>
            <a href='#daten_bearbeiten' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        <!-- ########################## -->
        <!-- ########  Letzte DB-Änderungen  ######### -->
        <!-- ########################## -->
        <article id='letzte_aenderung'>
            <a href='#' class='button'>Zurück</a>
            <h5><br /></h5>
            <h2>Letzte DB-Änderungen</h2> <!--class='major'-->
            <p>Hier werden alle Datenbankänderungen dokumentiert, egal ob es um Löschung, Änderung oder Einfügen geht. Wenn ein Team ständig versucht, Dinge zu bearbeiten, die es nicht bearbeiten soll, siehst du das hier und kannst dem Team die Rechte wegnehmen. Die Änderungen sind in SQL formuliert. Falls du nicht weißt, wie SQL funktioniert, klicke einfach <a href='https://studyflix.de/informatik/structured-query-language-606'>hier</a></p>"; 
            $sqlSystem_Data_DB_Verlauf = 'SELECT * FROM `System_Data_DB_Verlauf` ORDER BY ID desc';
            $resultSystem_Data_DB_Verlauf = $conn->query($sqlSystem_Data_DB_Verlauf);
            while ($rowSystem_Data_DB_Verlauf = $resultSystem_Data_DB_Verlauf->fetch_assoc()) {
                $data_db_verlauf_timestamp = $rowSystem_Data_DB_Verlauf['timestamp'];
                $data_db_verlauf_who = $rowSystem_Data_DB_Verlauf['fk_who'];
                $data_db_verlauf_content = $rowSystem_Data_DB_Verlauf['content'];
                echo"<hr>";
                echo"<p><b>$data_db_verlauf_who:</b> $data_db_verlauf_content ($data_db_verlauf_timestamp)</p>";
            }
            echo"<a href='#' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        <!-- ########################## -->
        <!-- ########  Traffic  ######### -->
        <!-- ########################## -->
        <article id='traffic'>
            <a href='#' class='button'>Zurück</a>
            <h5><br /></h5>
            <h2>Website-Traffic</h2> <!--class='major'-->
            <p>Hier werden Website-Funktionalitäten getrackt.</p>"; 
            $sql = 'SELECT * FROM `System_Traffic` ORDER BY id desc';
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $traffic_timestamp = $row['timestamp'];
                $traffic_who = $row['fk_who'];
                $traffic_kategorie_Id = $row['fk_kategorie'];
                    $sqlKat = 'SELECT * FROM `System_Traffic_Kategorien` WHERE id = '.$traffic_kategorie_Id.' ORDER BY id desc';
                    $resultKat = $conn->query($sqlKat);
                    while ($rowKat = $resultKat->fetch_assoc()) {
                        $traffic_kategorie = $rowKat['name'];
                    }
                $traffic_text = $row['text'];
                echo"<hr>";
                echo"<p><b>$traffic_kategorie </b> $traffic_who $traffic_text ($traffic_timestamp)</p>";
            }
            echo"<a href='#' class='button'>Zurück</a>
            <h5><br /></h5>  
        </article>

        <!-- ########################## -->
        <!-- ########  CHANGETEAM  ######### -->
        <!-- ########################## -->
        <article id='changeteam'>
            <div id='LogIn2'>
            <h2>Teams bearbeiten</h2>
            <p>Hier kannst du jedes Attribut der Teams ändern, also zum Beispiel Gruppe oder auch Bearbeitungsrechte für die Website.</p>";

            printGroupsAsTable($TurnierID, $conn, $LoggedIn, 0, 0);

            echo "<form action='website_datachange/edit_teams.php' method='POST' onSubmit='return checkAGBchangeTeam()'>
            <div class='field'>
                <label for='demo-category'>Team wählen</label>
                <select name='team' id='teams_waehlen' required>
                    <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'><i>Team wählen</i></option>";
                    $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
                    $resultTeam = $conn->query($sqlTeam);
                    $zaehler = 1;
                    while ($rowTeam = $resultTeam->fetch_assoc()) {
                        $teamName = $rowTeam['name'];
                        $teamKuerzel = $rowTeam['kuerzel'];
                        $teamId = $rowTeam['id'];
                        echo "<option value=$teamId>$zaehler. $teamKuerzel | $teamName</option>";	
                        $zaehler++;	
                    }
                    //TODO: Kann ich irgendwie alle Attribute als Dropdown haben???
            echo"</select>
            <h5><br/></h5>
            <select name='gruppe' id='teams_waehlen' >
                <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'><i>Gruppe wählen</i></option>";
                $sqlGruppe = 'SELECT * FROM `Turnier_Gruppe` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
                $resultGruppe = $conn->query($sqlGruppe);
                $zaehler = 1;
                while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                    $gruppeName = $rowGruppe['name'];
                    $gruppeId = $rowGruppe['id'];
                    echo "<option value=$gruppeId>$zaehler. $gruppeName</option>";
                    $zaehler++;		//TODO: value = id? dann ist es eindeutig und es wäre theoretisch nicht so schlimm wenn es zwei gleiche Kürzel gibt						
                }
            echo"</select>
            <p>Nur auswählen wenn Gruppe geändert werden soll.</p>
            </div>
            <h5><br/></h5>
            <label for='demo-category'>Login</label>
            <input type='text' id='registergame' name='bn' class='Eingabe' placeholder='Dein Team-Kürzel' style='color: white' required>
            <input type='password' id='registergame' name='pw' class='Eingabe' placeholder='Dein Team-Passwort' style='color: white' required>
            <h5><br/></h5>                                 
            <script type='text/javascript'>
                function checkAGBchangeTeam() {
                    if (document.getElementById('demo-human-changeteam').checked) {
                        return true;
                    }
                    alert('Du musst unten noch das Häkchen setzen!');
                    return false;
                }
            </script>  
            <div>
                <div class='field half'>
                    <input type='checkbox' id='demo-human-changeteam' name='demo-human-changeteam' unchecked>
                    <label for='demo-human-changeteam'>Ergebnisse nicht gelogen auf Ehre.</label>
                    <h5><br/></h5>  
                </div>
            </div>
            <p><button id='btn_login2' name='action' value='change_group' type='submit'>Gruppe ändern</button></p>
            <p><button id='btn_login2' name='action' value='rechte_weg' type='submit'>Bearbeitungsrechte wegnehmen</button></p>
            <p><button id='btn_login2' name='action' value='rechte_geben' type='submit'>Bearbeitungsrechte zurückgeben</button></p>
            </form>
            <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
            <p></br></p>
        </article>

    </div>
        ";
    }
    else{
        echo "
        <div class='content'>
            <div class='inner'>
                <h3><i>Login fehlgeschlagen</i></h3>
                <a href='/#login' class='button primary'>Zum Login</a>
                <a href='/' class='button'>Zurück zur Startseite</a>
            </div>
        </div>
    </header>
    <!-- Main -->
    <div id='main'>

    </div>
    ";
    }
    $conn->close();
    ?>           
                
                
<!-- ########################## -->
<!-- ########  FOOTER  ######### -->
<!-- ########################## -->  
<footer id="footer">
    <a href="https://www.strato.de/apps/CustomerService#/skl" class="button">Strato Kunden Login</a>
    <p><br/></p> 
    <p class="copyright">Bei Fragen, wende dich an <a>kummerkasten@REDACTED.de</a></p>
</footer>
                    






</div>






<!-- ########################## -->
<!-- ########  BG  ######### -->
<!-- ########################## -->  
    <div id="bg"></div>



<!-- ########################## -->
<!-- ########  SCRIPTS  ######### -->
<!-- ########################## -->  
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/browser.min.js"></script>
    <script src="assets/js/breakpoints.min.js"></script>
    <script src="assets/js/util.js"></script>
    <script src="assets/js/main.js"></script>




	</body>
</html>

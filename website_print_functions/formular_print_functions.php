<?php 
    function printEditGames($TurnierID, $test_turnier_id){
    //echo "<script>console.log('TurnierID2: $TurnierID')</script>";
?>  
    <script type="text/javascript">
        function checkAGBchangeGame() {
            if (document.getElementById('demo-human-changegame').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
    </script> 
    <div id="LogIn2">
    <?php
    $action = $_POST['action']; 
    //echo "<script>console.log('action: $action')</script>";

    $begegnungId = 0;
    $begegnungId = $_POST['begegnungId'];

    $heimteam = $_POST['heimteam']; 
    $auswaertsteam = $_POST['auswaertsteam']; 
    
    echo "<div style='text-align: center;'>";
    if($action == 'add'){ //SPIEL EINTRAGEN
        //echo "<script>console.log('begegnungId: $begegnungId')</script>";
        echo "<h2>Ergebnis eintragen</h2>";   
        $begegnungsAction = $_POST['begegnungsAction']; 
    }else if($action == 'editOrDelete'){ //SPIEL ÄNDERN/LÖSCHEN
        echo "<h2>Ergebnis bearbeiten</h2>";
        $spielId = $_POST['spielId'];
        echo "<script>console.log('spielId: $spielId')</script>";
        $biereheimteam = $_POST['biereheimteam'];
        $biereauswaertsteam = $_POST['biereauswaertsteam'];
    }else if($action == 'final'){ //FINALISIEREN
        echo "<h2>Begenung finalisieren</h2>";
    }else if($action == 'unfinal'){ //FINALISIEREN
        echo "<h2>Begenung un-finalisieren</h2>";
    }else if($action == 'final_group'){ //FINALISIEREN
        echo "<h2>Gesamte Gruppe finalisieren</h2>";
        $groupId = $_POST['groupId'];
    }
    echo "<p></br></p>";

    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBchangeGame()''>";
    }else{ //Testturniere
        echo "<form action='website_datachange/edit_games.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGBchangeGame()''>";
    }

    echo ""; ?> <!-- ?id=$spielId -->

    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>

    <!-- FALL: HINZUFÜGEN, ÄNDERN ODER LÖSCHEN -->
    <?php if($action == 'add' || $action == 'editOrDelete'){ ?>
        <ul class="actions">
            <?php echo "<p style='font-size: 2.25rem'>$heimteam</p>"; ?>
            <li><input type="number" min="0" max="3" type="text" id="registergame_Flaschen1" name="Flaschen1" class="Eingabe" value='<?php echo $biereheimteam ?>' placeholder="Flaschen 1*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
            <li><h1>:</h1></li>
            <li><input type="number" min="0" max="3" id="registergame_Flaschen2" name="Flaschen2" class="Eingabe" value='<?php echo $biereauswaertsteam ?>' placeholder="Flaschen 2*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
            <?php echo "<p style='font-size: 2.25rem'>$auswaertsteam</p>"; ?>
            <!-- values übergeben -->
            <input type='hidden' name='heimteam' value='<?php echo $heimteam ?>'/>
            <input type='hidden' name='auswaertsteam' value='<?php echo $auswaertsteam ?>'/>
            <input type='hidden' name='spielId' value='<?php echo $spielId ?>'/> <!-- nur für editOrDelete relevant -->
        </ul>
        <p>Hinweis: Wenn du das Spielergebnis löschen möchtest, weil es nur versehentlich eingetragen wurde, kannst du beide Felder oben frei lassen.</p>

    <!-- FALL: FINALISIEREN -->
    <?php }else if($action == 'final' || $action == 'final_group'){ ?>
        <p>Hier kannst du die Begegnung zwischen den beiden Teams finalisieren. Dadurch weiß die Website, dass ihr keine weiteren Spiele gegeneinander mehr macht und das Gewinnerteam für die nächsten Spiele berechnet werden kann.</p>
    <?php }else if($action == 'unfinal'){ ?>
        <p>Falls dieser Spielstand doch noch einmal bearbeitet werden soll, kann die Finalisierung hier aufgehoben werden.</p>
    <?php } ?>

    <!-- Für add und final relevant -->
    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>

    <!-- LOGIN  -->
    <h5><br/></h5>
    <label for="demo-category">Login</label>
    <input type="text" id="changegame_bn" name="bn" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
    <input type="password" id="changegame_pw" name="pw" class="Eingabe" placeholder="Dein Team-Passwort" style="color: white" required>
    <h5><br/></h5>                                 

    <!-- FALL: HINZUFÜGEN, ÄNDERN ODER LÖSCHEN -->
    <?php if($action == 'add' || $action == 'editOrDelete'){ ?>
            <!-- CHECK BOX -->
            <div>
                <div class="field half">
                    <input type="checkbox" id="demo-human-changegame" name="demo-human-changegame" unchecked>
                    <label for="demo-human-changegame">Ergebnisse nicht gelogen auf Ehre.</label>
                    <h5><br/></h5>  
                </div>
            </div>
            <?php 
                if($action == 'add'){
                    ?> <input type='submit' name='action' value='Eintragen'/> <?php
                }else{ //edit
                    ?> <input type='submit' name='action' value='Ändern'/> <?php
                    ?> <input type='submit' name='action' value='Löschen'/> <?php
                }
            ?>    
            </form>

    <!-- FALL: FINALISIEREN -->
    <?php }else if($action == 'final'){ ?> 
        <!-- CHECK BOX -->
        <div>
            <div class="field half">
                <input type="checkbox" id="demo-human-changegame" name="demo-human-changegame" unchecked>
                <label for="demo-human-changegame">Nach dem Finalisieren kann dieser Spielstand nur noch durch Admins bearbeitet werden.</label>
                <h5><br/></h5>  
            </div>
        </div>
        <input type='submit' name='action' value='Finalisieren'/>
        </form>

    <!-- FALL: UNFINALISIEREN -->
    <?php } else if($action == 'unfinal'){ ?> 
        <!-- CHECK BOX -->
        <div>
            <div class="field half">
                <input type="checkbox" id="demo-human-changegame" name="demo-human-changegame" unchecked>
                <label for="demo-human-changegame">Nur Admins können unfinalisieren!</label>
                <h5><br/></h5>  
            </div>
        </div>
        <input type='submit' name='action' value='Unfinalisieren'/>
        </form>

    <!-- FALL: GESAMTE GRUPPE FINALISIEREN -->
    <?php }else if($action == 'final_group'){ ?> 
        <!-- Für final_group relevant -->
        <input type='hidden' name='groupId' value='<?php echo $groupId ?>'/>

        <!-- CHECK BOX -->
        <div>
            <div class="field half">
                <input type="checkbox" id="demo-human-changegame" name="demo-human-changegame" unchecked>
                <label for="demo-human-changegame">Nur Admins können gesamte Gruppen finalisieren!</label>
                <h5><br/></h5>  
            </div>
        </div>
        <input type='submit' name='action' value='Gruppe_Finalisieren'/>
        <input type='submit' name='action' value='Gruppe_Uninalisieren'/>
        </form>
    <?php }else{
        echo "</form>";
    }
    echo "</div>";
}    
?>



<?php
function printTeamAnmelden($TurnierID, $test_turnier_id){
    ?>
    <title>Adressbuch</title>
    <div id="LogIn">
    <h1>Melde dein Team an</h1>
    <h3 style='color: green'>Teams bestehen immer aus genau 3 Spieler*innen!</h3>
    <p>Gib deine Telefonnummer an um Teil der Blankiball-Whatsapp-Gruppe zu werden. Bitte gebt <b>mindestens eine Nummer pro Team</b> an, damit wir euch erreichen können.</p>
    <p>Achtung: Der Name deines Teams und die Namen aller Mitspieler*innen werden auf der Website öffentlich für jede Person einsehbar sein. Die Telefonnummern werden zwar nicht direkt auf der Website veröffentlicht, es wird aber eine Whatsapp/Telegram/Signal-Gruppe mit allen Turnierteilnehmer*innen erstellt. In der Gruppe werden für alle anderen Personen alle Nummern sichtbar sein. Bitte gib deine Nummer nur ein, wenn du damit einverstanden bist. Fragt am besten auch eure Teammitglieder*innen ob sie damit einverstanden sind.</p> 
    <p>&#9733; = required</p>
    <?php
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='website_datachange/edit_teams.php' method='POST' onSubmit='return checkAGB()'>";
    }else{ //Testturniere
        echo "<form action='website_datachange/edit_teams.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGB()'>";
    }
    ?>
    <input type="text" id="teamname" name="Teamname" class="Eingabe" placeholder="Dein legendärer Teamname &#9733;" style="color: white" maxlength="40" required><br/>
    <input type="text" id="spieler1" name="Spieler1" class="Eingabe" placeholder="1. Bierballer*in &#9733;" style="color: white" maxlength="40" required><br/>
    <input type="text" id="tel1" name="tel1" class="Eingabe" placeholder="1. Telefonnummer &#9733;" style="color: white" maxlength="40" required><br/>
    <input type="text" id="spieler2" name="Spieler2" class="Eingabe" placeholder="2. Bierballer*in &#9733;" style="color: white" maxlength="40" required><br/>
    <input type="text" id="tel2" name="tel2" class="Eingabe" placeholder="2. Telefonnummer" style="color: white" maxlength="40"><br/>
    <input type="text" id="spieler3" name="Spieler3" class="Eingabe" placeholder="3. Bierballer*in &#9733;" style="color: white" maxlength="40" required><br/>
    <input type="text" id="tel3" name="tel3" class="Eingabe" placeholder="3. Telefonnummer" style="color: white" maxlength="40"><br/>
    <h5>Die Einträge dürfen nicht länger als 20 Buchstaben sein.</h5>
    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
    <p></br></p>
    <h4>E-Mail</h4>
    <p>Die Mail-Adresse, die du hier angibst, kann später genutzt werden, um z.B. euer Passwort zurückzusetzen oder euer Team wieder abzumelden. Außerdem bekommst du nach erfolgreichem Anmelden eine Mail mit Bestätigung und einer Übersicht deiner angemeldeten Daten (auch euer Passwort).</p>
    <input type="text" id="mail" name="Mail" class="Eingabe" placeholder="Mail-Adresse &#9733;" style="color: white" required><br/>
    </br>
    <h4>Kürzel* & Passwort</h4>
    <p>*Wähle Kürzel deines Teamnamens (2-4 Buchstaben) und ein Passwort/PIN-Code. Das Kürzel wird im Spielplan als Abkürzung benutzt und später kann dein Team mit Kürzel & Passwort auf der Website die Ergebnisse eintragen. Wichtig: Bitte nutze <b>wirklich wirklich kein Passwort, was du woanders schon benutzt</b> weil unsere Website nicht komplett sicher ist. Und außerdem brauchen alle deine Teammitglieder das Passwort.</p>
    <input type="text" id="kuerzel" name="Kuerzel" class="Eingabe" placeholder="Team-Kürzel* wählen (2-4 Buchstaben) &#9733;" style="color: white" maxlength="5" required><br/>
    <input type="password" id="passwort" name="Passwort" class="Eingabe" placeholder="Passwort* wählen &#9733;" style="color: white" required><br/>
    <h5><br/></h5>
    <h4>Woher hast du von uns erfahren? (Freiwillig)</h4>
    <select name='woher_erfahren'>
        <option value='...'><i>...</i></option>
        <option value='Freund*innen'><i>Freund*innen</i></option>
        <option value='Social Media'><i>Social Media</i></option>
        <option value='Google bzw. andere Suchmaschine'><i>Google bzw. andere Suchmaschine</i></option>
        <option value='Sonstiges'><i>Sonstiges</i></option>
    </select>
    <div class="h-captcha" data-sitekey="f3591a3b-4fdc-490c-99b0-a0b84ba5d938"></div>
    <h5><br/></h5>
    <title>[ untitled ]</title>                                
    <script type="text/javascript">
        function checkAGB() {
        if (document.getElementById('human').checked) {
            return true;
        }
        alert('Du musst unten noch das Häkchen setzen, du Hermann!');
        return false;
    }
    </script> 
    <div>
        <div class="field half">
            <input type="checkbox" id="human" name="human" unchecked>
            <label for="human">Ich stimme den <a style="color: white" href="#regeln">Regeln</a> des Turniers und der <a style="color: white" href="#datenschutzerklaerung">Datenschutzerklärung</a> zu und melde mein Team an...</label>
        </div>
    </div>
    <p></br></p>
    <input type='hidden' name='action' value='Anmelden'/>
    <p><button value="Absenden" type="submit">Anmelden</button></p>
    </form>
    <?php
}

function printTeamAbmelden($conn, $TurnierID){
    ?>
    <title>Adressbuch</title>
    <div id="LogIn">
    <h2>Melde dein Team ab</h2>
    <p></p>
    <form action="website_datachange/edit_teams.php" method="POST" onSubmit="return checkAGBabmelden()">
    <select name='Team_zum_abmelden' id='teams_waehlen' >
        <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'><i>Team wählen</i></option>";
        <?php
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
        $resultTeam = $conn->query($sqlTeam);
        while ($rowTeam = $resultTeam->fetch_assoc()) {
            $teamId = $rowTeam['id'];
            $teamName = $rowTeam['name'];
            $teamKuerzel = $rowTeam['kuerzel'];
            echo "<option value=$teamId>$teamKuerzel | $teamName</option>";	//TODO: value = id? dann ist es eindeutig und es wäre theoretisch nicht so schlimm wenn es zwei gleiche Kürzel gibt						
        } ?>
    </select>
    <p></br></p>
    <h4>Kürzel/Account & Passwort</h4>
    <input type="text" id="luerzel" name="bn" class="Eingabe" placeholder="Kürzel/Account" style="color: white" required><br/>
    <input type="password" id="passwort" name="pw" class="Eingabe" placeholder="Passwort" style="color: white" required><br/>
    <h5><br/></h5>
    <title>[ untitled ]</title>                                
    <script type="text/javascript">
        function checkAGBabmelden() {
        if (document.getElementById('team_abmelden').checked) {
            return true;
        }
        alert('Du musst unten noch das Häkchen setzen, du Hermann!');
        return false;
    }
    </script> 
    <div>
        <div class="field half">
            <input type="checkbox" id="team_abmelden" name="team_abmelden" unchecked>
            <label for="team_abmelden">Ich bin mir sicher, dass ich mein Team abmelden möchte und das Team nicht am Turnier teilnehmen soll.</label>
        </div>
    </div>
    <p></br></p>
    <input type='hidden' name='action' value='Abmelden'/>
    <p><button id="btn_login_Abmelden" value="Absenden" type="submit">Abmelden</button></p>
    
    </form>
    <?php
}


function printSpielerInfoLogin($TurnierID, $conn, $spielerId){
    ?>
    <title>Adressbuch</title>
    <div id="LogIn">
    <p></br></p>
    <h1>Spieler*in-Info</h1>
    <p>Bitte logge dich ein, um mehr Infos zu einem konkreten Spieler zu erhalten. (zum Beispiel die Telefonnummer)</p>
    <h3>Diese Funktion kann nur von Schiedsrichter*innen genutzt werden!</h3>
    <?php
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='/?spielerId=$spielerId#spielerinfo' method='POST' onSubmit='return checkAGBspielerinfo()'>";
    }else{ //Testturniere
        echo "<form action='/?spielerId=$spielerId&test_turnier_id=$test_turnier_id#spielerinfo' method='POST' onSubmit='return checkAGB()'>";
    }
    ?>
    <script type="text/javascript">
        function checkAGBspielerinfo() {
        if (document.getElementById('spielerinfo').checked) {
            return true;
        }
        alert('Du musst unten noch das Häkchen setzen, du Hermann!');
        return false;
    }
    </script> 
    <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
    <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <p></p>
    <div>
        <div class="field half">
            <input type="checkbox" id="spielerinfo" name="spielerinfo" unchecked>
            <label for="spielerinfo">Ich werde die Telefnummern nicht an Dritte weitergeben und bin auch nicht Jeff Bezos.</label>
        </div>
    </div>
    <p></p>
    <button value="Anmelden" type="submit">Anmelden</button>
    </form>
    <?php
}

function printBullereiKommt($conn, $websiteId){
    ?>
    <title>Adressbuch</title>
    <div id="LogIn">
    <h2>Website temporär offline nehmen</h2>
    <p>Für den Fall, dass die Website aus irgendeinem Grund offline genommen werden soll, ist das hier möglich. Bitte nutze diese Funktion nicht aus Spaß, da die Website dann wirklich deaktiviert ist und erst durch einen Administrator wieder aktiviert werden muss.</p>
    <form action="website_datachange/edit_website_bullerei.php" method="POST" onSubmit="return checkAGBbullerei()">
    <h4>Kürzel/Account & Passwort</h4>
    <input type="text" id="luerzel" name="bn" class="Eingabe" placeholder="Kürzel/Account" style="color: white" required><br/>
    <input type="password" id="passwort" name="pw" class="Eingabe" placeholder="Passwort" style="color: white" required><br/>
    <h5><br/></h5>
    <title>[ untitled ]</title>                                
    <script type="text/javascript">
        function checkAGBbullerei() {
        if (document.getElementById('bullerei').checked) {
            return true;
        }
        alert('Du musst unten noch das Häkchen setzen, du Hermann!');
        return false;
    }
    </script> 
    <div>
        <div class="field half">
            <input type="checkbox" id="bullerei" name="bullerei" unchecked>
            <label for="bullerei">Ich bin mir sicher, dass ich mir *was auch immer* nicht einbilde.</label>
        </div>
    </div>
    <p></br></p>
    <input type='hidden' name='action' value='take_offline'/>
    <?php echo "<input type='hidden' name='websiteId' value='$websiteId'/>"; ?>
    <p><button id="btn_login_Bullerei" value="Absenden" type="submit">Website offline nehmen</button></p>
    </form>
    <?php
}
?>

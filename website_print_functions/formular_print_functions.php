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
    <script type="text/javascript">
        // Override to allow captcha-only submit via cb_action=check
        try {
            (function(){
                var old = window.checkAGB;
                window.checkAGB = function(){
                    try {
                        var submitter = document.activeElement;
                        if (submitter && submitter.name === 'cb_action' && submitter.value === 'check') { return true; }
                    } catch(e){}
                    if (typeof old === 'function') { return old(); }
                    return true;
                };
            })();
        } catch(e){}
    </script> 
    <div id="LogIn2">
    <?php
    $action = isset($_POST['action']) ? $_POST['action'] : null; 
    //echo "<script>console.log('action: $action')</script>";

    $begegnungId = isset($_POST['begegnungId']) ? $_POST['begegnungId'] : 0;

    $heimteam = isset($_POST['heimteam']) ? $_POST['heimteam'] : null; 
    $auswaertsteam = isset($_POST['auswaertsteam']) ? $_POST['auswaertsteam'] : null; 
    
    echo "<div style='text-align: center;'>";
    if($action == 'add'){ //SPIEL EINTRAGEN
        //echo "<script>console.log('begegnungId: $begegnungId')</script>";
        echo "<h2>Ergebnis eintragen</h2>";   
        $begegnungsAction = isset($_POST['begegnungsAction']) ? $_POST['begegnungsAction'] : null; 
    }else if($action == 'editOrDelete'){ //SPIEL ÄNDERN/LÖSCHEN
        echo "<h2>Ergebnis bearbeiten</h2>";
        $spielId = isset($_POST['spielId']) ? $_POST['spielId'] : null;
        echo "<script>console.log('spielId: $spielId')</script>";
        $biereheimteam = isset($_POST['biereheimteam']) ? $_POST['biereheimteam'] : null;
        $biereauswaertsteam = isset($_POST['biereauswaertsteam']) ? $_POST['biereauswaertsteam'] : null;
    }else if($action == 'final'){ //FINALISIEREN
        echo "<h2>Begenung finalisieren</h2>";
    }else if($action == 'unfinal'){ //FINALISIEREN
        echo "<h2>Begenung un-finalisieren</h2>";
    }else if($action == 'final_group'){ //FINALISIEREN
        echo "<h2>Gesamte Gruppe finalisieren</h2>";
        $groupId = isset($_POST['groupId']) ? $_POST['groupId'] : null;
    }
    echo "<p></br></p>";

    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBchangeGame()'>";
    }else{ //Testturniere
        echo "<form action='website_datachange/edit_games.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGBchangeGame()'>";
    }

    echo ""; ?> <!-- ?id=$spielId -->

    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>

    <!-- FALL: HINZUFÜGEN, ÄNDERN ODER LÖSCHEN -->
    <?php if($action == 'add' || $action == 'editOrDelete'){ ?>
        <style>
            .score-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: clamp(0.35rem, 1.2vw, 0.75rem);
                flex-wrap: nowrap;
                white-space: nowrap;
                list-style: none;
                padding: 0;
                margin: 0;
                width: 100%;
            }
            .score-row > li {
                flex-shrink: 1;
                min-width: 0;
            }
            .score-row .team-label {
                flex: 1 1 0;
                font-size: clamp(1.25rem, 4vw, 2.25rem);
                text-align: center;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .score-row .score-input {
                flex: 0 1 90px;
                min-width: 0;
            }
            .score-row .score-input input {
                width: 100%;
                min-width: 0;
                text-align: center;
            }
            .score-row .score-separator {
                flex: 0 0 auto;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .score-note {
                text-align: center;
                margin: 0.35rem 0 0.75rem;
            }
        </style>
        <?php echo '<h1>' . htmlspecialchars($heimteam, ENT_QUOTES, 'UTF-8') . ' vs. ' . htmlspecialchars($auswaertsteam, ENT_QUOTES, 'UTF-8') . '</h1>'; ?>
        <ul class="actions score-row fixed">
            <li class="team-label"><?php echo $heimteam; ?></li>
            <li class="score-input"><input type="number" min="0" max="3" id="registergame_Flaschen1" name="Flaschen1" class="Eingabe" value='<?php echo $biereheimteam ?>' placeholder="Flaschen 1*" style="color: black" required autocomplete="off" data-lpignore="true" data-1p-ignore data-keevault-ignore></li>
            <li class="score-separator"><span style='font-size: clamp(1.4rem, 4vw, 2.25rem)'>:</span></li>
            <li class="score-input"><input type="number" min="0" max="3" id="registergame_Flaschen2" name="Flaschen2" class="Eingabe" value='<?php echo $biereauswaertsteam ?>' placeholder="Flaschen 2*" style="color: black" required autocomplete="off" data-lpignore="true" data-1p-ignore data-keevault-ignore></li>
            <li class="team-label"><?php echo $auswaertsteam; ?></li>
        </ul>
        <div class="score-note"><b>(Ausgetrunkene Flaschen)</b></div>
        <!-- values ?bergeben -->
        <input type='hidden' name='heimteam' value='<?php echo $heimteam ?>'/>
        <input type='hidden' name='auswaertsteam' value='<?php echo $auswaertsteam ?>'/>
        <input type='hidden' name='spielId' value='<?php echo $spielId ?>'/> <!-- nur f?r editOrDelete relevant -->
        <div class="note">Falls ein Spielstand wieder gelöscht werden soll, müsst ihr die Orga ansprechen (Löschen geht nur mit Admin-Accounts)</div>
    <!-- FALL: FINALISIEREN -->
    <?php }else if($action == 'final' || $action == 'final_group'){ ?>
        <div class="note">Hier kannst du die Begegnung zwischen den beiden Teams finalisieren. Dadurch weiß die Website, dass ihr keine weiteren Spiele gegeneinander mehr macht und das Gewinnerteam für die nächsten Spiele berechnet werden kann.</div>
    <?php }else if($action == 'unfinal'){ ?>
        <p>Falls dieser Spielstand doch noch einmal bearbeitet werden soll, kann die Finalisierung hier aufgehoben werden.</p>
    <?php } ?>

    <!-- Für add und final relevant -->
    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>

    <!-- LOGIN  -->
    <script>
        try {
            document.getElementById('changegame_bn')?.setAttribute('autocomplete','username');
            document.getElementById('changegame_pw')?.setAttribute('autocomplete','current-password');
        } catch (e) {}
    </script>
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
                <div class="field half">
                    <input type="checkbox" id="finalize-after-entry" name="finalize_after_entry" value="1">
                    <label for="finalize-after-entry">Begegnung direkt finalisieren.</label>
                    <p class="note" style="margin: 0.35rem 0 0;">Mit diesem Haken wird der Website gesagt, dass an dieser Stelle kein weiteres Match ansteht und direkt die Nachfolgespiele berechnet werden können. Falls ihr noch weitere Spiele zwischen den beiden Teams hier eintragen wollt, lasst die Checkbox frei.</p>
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
function printTeamAnmelden($TurnierID, $test_turnier_id, $teilnahmebeitrag){
    ?>
    <title>Adressbuch</title>
    <id="LogIn">
    <?php
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $prev = isset($_SESSION['register_form_data']) ? $_SESSION['register_form_data'] : [];
    $teilnahmebeitragValue = 0.0;
    $teilnahmebeitragText = null;
    if (is_string($teilnahmebeitrag)) {
        $teilnahmebeitrag = str_replace(',', '.', $teilnahmebeitrag);
    }
    if (is_numeric($teilnahmebeitrag)) {
        $teilnahmebeitragValue = (float)$teilnahmebeitrag;
        if (floor($teilnahmebeitragValue) == $teilnahmebeitragValue) {
            $teilnahmebeitragText = number_format($teilnahmebeitragValue, 0, ',', '.');
        } else {
            $teilnahmebeitragText = rtrim(rtrim(number_format($teilnahmebeitragValue, 2, ',', '.'), '0'), ',');
        }
    }
    $captchaFeedback = [
        'shouldShow' => false,
        'message' => null,
        'remaining' => 3,
        'ok' => false,
        'reloadNotice' => null,
    ];
    if (!empty($_SESSION['captcha_attempted_register'])) {
        $captchaFeedback['remaining'] = isset($_SESSION['captcha_remaining_register'])
            ? (int)$_SESSION['captcha_remaining_register']
            : 3;
        $captchaFeedback['message'] = isset($_SESSION['flash_error_register'])
            ? $_SESSION['flash_error_register']
            : null;
        $captchaFeedback['ok'] = ($captchaFeedback['message'] && stripos($captchaFeedback['message'], 'best') !== false);
        $captchaFeedback['shouldShow'] = $captchaFeedback['ok'] || $captchaFeedback['remaining'] <= 2;
        if ($captchaFeedback['message'] && stripos($captchaFeedback['message'], 'Captcha 3x fehlgeschlagen') !== false) {
            $captchaFeedback['reloadNotice'] = $captchaFeedback['message'];
            $captchaFeedback['shouldShow'] = true;
        }
        unset($_SESSION['captcha_attempted_register'], $_SESSION['captcha_remaining_register']);
    }
    ?>
    <?php if (!empty($captchaFeedback['reloadNotice'])) { ?>
        <div class="cb-status-global" style="margin:10px 0;padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;">
            <?php echo htmlspecialchars($captchaFeedback['reloadNotice'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>
    <h1>Melde dein Team an</h1>
    <?php if ($captchaFeedback['shouldShow']) {
        $attemptLabel = ($captchaFeedback['remaining'] === 1) ? 'Versuch' : 'Versuche';
        $borderColor = $captchaFeedback['ok'] ? '#27ae60' : '#c0392b';
        $bgColor = $captchaFeedback['ok'] ? '#ecf9f0' : '#ffeaea';
        $textColor = $captchaFeedback['ok'] ? '#27ae60' : '#c0392b';
        echo '<div class="cb-status-global" style="margin:10px 0;padding:10px;border:1px solid '. $borderColor .';border-radius:6px;background:'. $bgColor .';color:'. $textColor .';">';
        if ($captchaFeedback['message']) {
            echo htmlspecialchars($captchaFeedback['message'], ENT_QUOTES, 'UTF-8');
            if (stripos($captchaFeedback['message'], 'Verbleibende Versuch') === false) {
                echo '<br />Verbleibende '. $attemptLabel .': '. $captchaFeedback['remaining'];
            }
        } else {
            echo 'Verbleibende '. $attemptLabel .': '. $captchaFeedback['remaining'];
        }
        echo '</div>';
    } ?>
    <h3>Kurz das wichtigste:</h3>
    <ul>
        <li>3 Spieler*innen pro Team</li>
        <?php
            if ($teilnahmebeitragValue > 0 && $teilnahmebeitragText !== null) {
                echo "<li><b>" . $teilnahmebeitragText . "&nbsp;&euro; Teilnahmegeb&uuml;hr pro Team - nach Anmeldung &uuml;berweisen per Paypal an @REDACTED &#10145;&#65039; Verwendungszweck: *Euer Teamname*</b></li>";
            }
        ?>
        
        <li>Mindestens eine Telefonnummer angeben, damit wir euch erreichen können</li>
        <li>Bitte eure <b>richtigen Namen</b> verwenden, damit wir wissen, wer ihr seid</li>
    </ul>
    <!--<h3 style='color: green'>Teams bestehen immer aus genau 3 Spieler*innen!</h3>
    <p>Gib deine Telefonnummer an um Teil der Blankiball-Whatsapp-Gruppe zu werden. Bitte gebt <b>mindestens eine Nummer pro Team</b> an, damit wir euch erreichen können.</p>
    <p>Achtung: Der Name deines Teams und die Namen aller Mitspieler*innen werden auf der Website öffentlich für jede Person einsehbar sein. Die Telefonnummern werden zwar nicht direkt auf der Website veröffentlicht, es wird aber eine Whatsapp/Telegram/Signal-Gruppe mit allen Turnierteilnehmer*innen erstellt. In der Gruppe werden für alle anderen Personen alle Nummern sichtbar sein. Bitte gib deine Nummer nur ein, wenn du damit einverstanden bist. Fragt am besten auch eure Teammitglieder*innen ob sie damit einverstanden sind.</p> 
    -->
    <p>&#9733; = Pflichtfeld</p>
    <?php
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='website_datachange/edit_teams.php' method='POST' onSubmit='return checkAGB(event)'>";
    }else{ //Testturniere
        echo "<form action='website_datachange/edit_teams.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGB(event)'>";
    }
    ?>
    <input type="text" id="teamname" name="Teamname" class="Eingabe" placeholder="Dein legendärer Teamname &#9733;" style="color: white" maxlength="40" required value="<?php echo isset($prev['Teamname']) ? htmlspecialchars($prev['Teamname'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="spieler1" name="Spieler1" class="Eingabe" placeholder="1. Bierballer*in &#9733;" style="color: white" maxlength="40" required value="<?php echo isset($prev['Spieler1']) ? htmlspecialchars($prev['Spieler1'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="tel1" name="tel1" class="Eingabe" placeholder="1. Telefonnummer &#9733;" style="color: white" maxlength="40" required value="<?php echo isset($prev['tel1']) ? htmlspecialchars($prev['tel1'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="spieler2" name="Spieler2" class="Eingabe" placeholder="2. Bierballer*in &#9733;" style="color: white" maxlength="40" required value="<?php echo isset($prev['Spieler2']) ? htmlspecialchars($prev['Spieler2'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="tel2" name="tel2" class="Eingabe" placeholder="2. Telefonnummer" style="color: white" maxlength="40" value="<?php echo isset($prev['tel2']) ? htmlspecialchars($prev['tel2'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="spieler3" name="Spieler3" class="Eingabe" placeholder="3. Bierballer*in &#9733;" style="color: white" maxlength="40" required value="<?php echo isset($prev['Spieler3']) ? htmlspecialchars($prev['Spieler3'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="text" id="tel3" name="tel3" class="Eingabe" placeholder="3. Telefonnummer" style="color: white" maxlength="40" value="<?php echo isset($prev['tel3']) ? htmlspecialchars($prev['tel3'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <i>Die Einträge dürfen nicht länger als 20 Buchstaben sein.</i>
    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
    <p></br></p>
    
    <h4>Kürzel* & Passwort</h4>
    <!--<p>*Wähle Kürzel deines Teamnamens (2-4 Buchstaben) und ein Passwort/PIN-Code. Das Kürzel wird im Spielplan als Abkürzung benutzt und später kann dein Team mit Kürzel & Passwort auf der Website die Ergebnisse eintragen. Wichtig: Bitte nutze <b>wirklich wirklich kein Passwort, was du woanders schon benutzt</b> weil unsere Website nicht komplett sicher ist. Und außerdem brauchen alle deine Teammitglieder das Passwort.</p>-->
    <p>Mit dem Passwort könnt ihr später eure Turnierergebnisse eintragen!</p>
    <input type="text" id="kuerzel" name="Kuerzel" class="Eingabe" placeholder="Team-Kürzel* wählen (2-4 Buchstaben) &#9733;" style="color: white" maxlength="5" required autocomplete="Kürzel" value="<?php echo isset($prev['Kuerzel']) ? htmlspecialchars($prev['Kuerzel'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <input type="password" id="passwort" name="Passwort" class="Eingabe" placeholder="Passwort* wählen &#9733;" style="color: white" required autocomplete="team-password" value="<?php echo isset($prev['Passwort']) ? htmlspecialchars($prev['Passwort'], ENT_QUOTES, 'UTF-8') : ''; ?>"><br/>
    <br/>
    <!--<h4>E-Mail</h4>
    <p>Die Mail-Adresse, die du hier angibst, kann später genutzt werden, um z.B. euer Passwort zurückzusetzen oder euer Team wieder abzumelden. Außerdem bekommst du nach erfolgreichem Anmelden eine Mail mit Bestätigung und einer Übersicht deiner angemeldeten Daten (auch euer Passwort).</p>
    
    <i>An die Mail bekommt ihr eure Team-Daten gesendet.</i>
    <i>Mit der E-Mail könnt ihr später euer Konto verwalten.</i>
    
    <input type="text" id="mail" name="Mail" class="Eingabe" placeholder="Mail-Adresse &#9733;" style="color: white" required><br/>-->
    
    <h5><br/></h5>
    <h4>Woher hast du von uns erfahren? (Freiwillig)</h4>
    <select name='woher_erfahren'>
        <?php $prevWoher = isset($prev['woher_erfahren']) ? (string)$prev['woher_erfahren'] : '...'; ?>
        <option value='...' <?php echo $prevWoher === '...' ? 'selected' : ''; ?>><i>...</i></option>
        <option value='Freund*innen' <?php echo $prevWoher === 'Freund*innen' ? 'selected' : ''; ?>><i>Freund*innen</i></option>
        <option value='Social Media' <?php echo $prevWoher === 'Social Media' ? 'selected' : ''; ?>><i>Social Media</i></option>
        <option value='Blankiball-Sticker' <?php echo $prevWoher === 'Blankiball-Sticker' ? 'selected' : ''; ?>><i>Blankiball-Sticker</i></option>
        <option value='Google bzw. andere Suchmaschine' <?php echo $prevWoher === 'Google bzw. andere Suchmaschine' ? 'selected' : ''; ?>><i>Google bzw. andere Suchmaschine</i></option>
        <option value='Sonstiges' <?php echo $prevWoher === 'Sonstiges' ? 'selected' : ''; ?>><i>Sonstiges</i></option>
    </select>


    <?php 
        // Neues Bild-Captcha einbinden (Registrierung)
        if (!empty($_SESSION['register_form_data'])) { unset($_SESSION['register_form_data']); }
        require_once __DIR__ . '/../website_functionalities/captcha_blanki.php';
        echo '<div id="anmelden-captcha"></div>';
        CaptchaBlanki::render('register');
        $captchaPassed = CaptchaBlanki::passed('register');
    ?>
    <h5><br/></h5>

    <?php
        if ($teilnahmebeitragValue > 0 && $teilnahmebeitragText !== null) {
            echo "<div style='text-align:center;'>";
            echo "<h1>Wichtig!</h1>";
            echo "<p>&#10071;Euer Team ist erst angemeldet, wenn ihr die <b>Teilnahmegeb&uuml;hr</b> von <b>" . $teilnahmebeitragText . "&nbsp;&euro;</b> pro Team &uuml;berwiesen habt&#10071;</p>";
            echo "<p>&#128176; &Uuml;berweisen per <b>Paypal an @REDACTED mit eurem Teamnamen als Verwendungszweck</b> &#128176;</p>";
            echo "</div>";
        }
    ?>
    

    <h5><br/></h5>
    <title>[ untitled ]</title>                                
    <script type="text/javascript">
        function checkAGB(evt) {
            try {
                var submitter = evt && (evt.submitter || document.activeElement);
                if (submitter && submitter.name === 'cb_action' && submitter.value === 'check') {
                    return true;
                }
            } catch (e) {}
            var human = document.getElementById('human');
            if (human && human.checked) {
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
    <?php $submitDisabledAttr = $captchaPassed ? '' : ' disabled'; ?>
    <p><button value="Absenden" type="submit"<?php echo $submitDisabledAttr; ?>>Anmelden</button></p>
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
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY id';
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
    $test_turnier_id = isset($_GET['test_turnier_id']) ? $_GET['test_turnier_id'] : 0;
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

function printBullereiKommt($conn, $websiteId, $TurnierID){
    ?>
    <title>Adressbuch</title>
    <div id="LogIn">
    <h2>Website temporär offline nehmen</h2>
    <p>Für den Fall, dass die Website aus irgendeinem Grund offline genommen werden soll, ist das hier möglich. Bitte nutze diese Funktion nicht aus Spaß, da die Website dann wirklich deaktiviert ist und erst durch einen Administrator wieder aktiviert werden muss.</p>
    <form action="website_datachange/edit_website_bullerei.php" method="POST" onSubmit="return checkAGBbullerei()">
    <h4>Kürzel/Account & Passwort</h4>
    <input type="text" id="kuerzel" name="bn" class="Eingabe" placeholder="Kürzel/Account" style="color: white" required><br/>
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
    <?php echo "<input type='hidden' name='TurnierID' value='$TurnierID'/>"; ?>
    <p><button id="btn_login_Bullerei" value="Absenden" type="submit">Website offline nehmen</button></p>
    </form>
    <?php
}
?>

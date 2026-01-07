<div style='display:grid; grid-template-columns: 1fr'>
    <div>"; if($platzierungen[0] != "platzhalter"){$x=$platzierungen[0];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</div>
</div>
<div style='display:grid; grid-template-columns: 1fr 1fr 1fr'>
    <div></div>
    <div style='background-color:red'><h1>1. &#10026;</h1></div>
    <div>"; if($platzierungen[1] != "platzhalter"){$x=$platzierungen[1];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"erererererererererererer</div>
</div>
<div style='display:grid; grid-template-columns: 1fr 1fr 1fr'>
    <div>"; if($platzierungen[2] != "platzhalter"){$x=$platzierungen[2];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</div>
    <div style='background-color:red'><h1>1. &#10026;</h1></div>
    <div style='background-color:red'><h1>2. &#10026;</h1></div>
</div>





<table style='text-align: center;'>
<thead>
<tr>
    <td style='background-color:transparent'></td>
    <td width='100%' style='background-color:transparent'>"; if($platzierungen[0] != "platzhalter"){$x=$platzierungen[0];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</td>
    <td style='background-color:transparent'></td>
</tr>
<tr>
    <td style='background-color:transparent'></td>
    <td style='background-color:red'><h1>1. &#10026;</h1></td>
    <td style='background-color:transparent'>"; if($platzierungen[1] != "platzhalter"){$x=$platzierungen[1];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</td>
</tr>
<tr>
    <td style='background-color:transparent'>"; if($platzierungen[2] != "platzhalter"){$x=$platzierungen[2];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</td>
    <td style='background-color:red'> </td>
    <td style='background-color:red'><h1>2. &#10026;</h1></td>
</tr>
<tr>
    <td style='background-color:red'><h1>3. &#10026;</h1></td>
    <td rowspan='3' style='background-color:red'><!--<img src='images/hermann_logo/export.png' width='90em' height=auto alt='Blankiball Sieger*innen-Treppe'>--></td>
    <td style='background-color:red'> </td>
</tr>
<tr>
    <td style='background-color:red'></br></td>
    <td style='background-color:red'></br></td>
</tr>
</thead>
<tbody>
</tbody>
</table>


//$argsWithSQL = func_get_args(); // now you can access these as $args[0], $args[1]
//$sql = $argsWithSQL[0]; //z.B. $sql = "INSERT INTO Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?)";
//$args = array_slice($argsWithSQL, 1); //erster arg wird abgeschnitten






//TEST
//$sql = "INSERT INTO System_Benutzer_in (Benutzername, Passwort, fk_rechte) VALUES (?, ?, ?)";
//$stmt = $conn->prepare($sql);

//$stmt->bind_param("sss", $name, $description, $fk_random);
/*$array_of_values = array($name, $description, $fk_random);
echo "array_of_values: $array_of_values[0] ; $array_of_values[1] ; $array_of_values[2] <br/>";
$types = "sss";
$stmt->bind_param($types, ...$array_of_values);*/ //This is called "argument unpacking", and is available since PHP 5.6
/*$stmt->bind_param("sss");
$stmt->bind_param($name);
$stmt->bind_param($description);
$stmt->bind_param($fk_random);*/

//$stmt->execute();
//printf("Datenätze eingefügt: %d.\n", $stmt->affected_rows);

<table class="table" class="th-text-center">
     <thead>
     </thead>
     <tbody>
          <tr>
            <td>Gruppen-phase</td>
            <td>Montag - Donnerstag</td>
          </tr>
          <tr>
            <td>Achtelfinale</td>
            <td>Freitag</td>
          </tr>
          <tr>
            <td>Viertelfinale</td>
            <td>Samstag</td>
          </tr>
          <tr>
            <td>Halbfinale & Finale</td>
            <td>Sonntag</td>
          </tr>
     </tbody>
</table>


<table class="table" class="th-text-center">
     <thead>
          <tr>
                <td>Montag</td>
                <td>Dienstag</td>
                <td>Mittwoch</td>
                <td>Donnerstag</td>
                <td>Freitag</td>
                <td>Samstag</td>
                <td>Sonntag</td>
            </tr>
     </thead>
     <tbody>
          <tr>
                <td>Gruppen-phase 1</td>
                <td>Gruppen-phase 2</td>
                <td>Gruppen-phase 3</td>
                <td>Gruppen-phase 4</td>
                <td>Achtel-finale</td>
                <td>Viertel-finale</td>
                <td>Halb-finale & Finale</td>
           </tr>
     </tbody>
</table>


      /*
      <input type="hidden" name="id" value="<?php echo $row['id']; ?>"/>
      <select name="taskOption">
        <option value="1">First</option>
        <option value="2">Second</option>
        <option value="3">Third</option>
      </select>
      */
      //$stmt = $conn->prepare("UPDATE `Spiel` SET `biereheimteam` = '$flaschen1', `biereauswaertsteam` = '$flaschen2' WHERE `Spiel`.`id` = '$spielID';");
      //$stmt->execute();

      //RETURNT DIE ID DES GERADE ANGELEGTEN TEAMS UND GIBT SIE IN KONSOLE AUS
      //$teamID = $conn->insert_id;	//Variable insert_id von conn wird aufgerufen
      //echo '<script>console.log('.$teamID.')</script>';

      //$stmt->close();
  }
  
  
    //echo 'Der Eintrag war erfolgreich';
//} else {
//    echo 'Ihre Angaben sind fehlerhaft.';
//}
//echo '<a href="adressbuch.html">Zurück</a>';



<!-- ########################## -->
<!-- ########  REGISTERGAME - NEW  ######### -->
<!-- ########################## -->
<article id="addgame">
    <?php 
    $begegnungId = $_POST['begegnungId']; 
    $heimteam = $_POST['heimteam']; 
    $auswaertsteam = $_POST['auswaertsteam']; 
    $begegnungsAction = $_POST['begegnungsAction']; 
    ?>
    <div id="LogIn2">
    <h2>Ergebnisse eintragen</h2>
    <p>Die <b>Reihenfolge</b> deines Eintrags ist wichtig! <i>Als erstes</i> steht das Team aus der <i>Zeile</i> und <i>als zweites</i> das Team aus der <i>Spalte</i> (Das ist natürlich nur für die Gruppenphase relevant, weil es in der KO-Phase keine Zeilen und Spalten mehr gibt)</p>
    <form action="website_datachange/registergame.php" method="POST" onSubmit="return checkAGBaddGame()">
    <ul class="actions">
        <!--TEAM 1 WÄHLEN-->
        <!--<li><input type="text" id="registergame_Team1" name="Team1" class="Eingabe" placeholder="Kürzel 1" style="color: white" required></li>-->
        <?php echo "<p>$heimteam</p>"; ?>
        <li><input type="number" min="0" max="3" type="text" id="registergame_Flaschen1" name="Flaschen1" class="Eingabe" placeholder="Flaschen 1*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
        <li><h1>:</h1></li>
        <!--TEAM 2 WÄHLEN-->
        <!--<li><input type="text" id="registergame_Team2" name="Team2" class="Eingabe" placeholder="Kürzel 2" style="color: white" required></li>-->
        <?php echo "<p>$auswaertsteam</p>"; ?>
        <li><input type="number" min="0" max="3" id="registergame_Flaschen2" name="Flaschen2" class="Eingabe" placeholder="Flaschen 2*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
        <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
        <input type='hidden' name='heimteam' value='<?php echo $heimteam ?>'/>
        <input type='hidden' name='auswaertsteam' value='<?php echo $auswaertsteam ?>'/>
        <input type='hidden' name='begegnungsAction' value='<?php echo $begegnungsAction ?>'/>
    </ul> 
    <p><i>* Bei "Ausgetrunkene Flaschen" einfach eintragen, wie viele Flaschen pro Team geleert wurden. Wenn ihr gewonnen habt und das Verliererteam trotzdem ein Bier geleert hat, wäre der Spielstand zum Beispiel 3:1 und ihr würdet einmal 3 und einmal 1 Flasche eintragen.</i></p>
    <h5><br/></h5>
    <label for="demo-category">Login</label>
    <input type="text" id="registergame_Login_Kuerzel" name="Login_Kuerzel" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
    <input type="password" id="registergame_Login_Passwort" name="Login_Passwort" class="Eingabe" placeholder="Dein Team-Passwort" style="color: white" required>
    <h5><br/></h5>                                 
    <script type="text/javascript">
        function checkAGBaddGame() {
            if (document.getElementById('demo-human-addgame').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
    </script>  
    <div>
        <div class="field half">
            <input type="checkbox" id="demo-human-addgame" name="demo-human-addgame" unchecked>
            <label for="demo-human-addgame">Ergebnisse nicht gelogen auf Ehre.</label>
            <h5><br/></h5>  
        </div>
    </div>
    <p><button id="btn_login_Absenden_2" value="Absenden" type="submit">Eintragen</button></p>
    </form>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- ########################## -->
<!-- ########  REGISTERGAME  ######### -->
<!-- ########################## -->
<article id="registergame">
    <div id="LogIn2">
    <h2>Ergebnisse eintragen</h2>
    <p>Die <b>Reihenfolge</b> deines Eintrags ist wichtig! <i>Als erstes</i> steht das Team aus der <i>Zeile</i> und <i>als zweites</i> das Team aus der <i>Spalte</i> (Das ist natürlich nur für die Gruppenphase relevant, weil es in der KO-Phase keine Zeilen und Spalten mehr gibt)</p>
    <form action="website_datachange/registergame.php" method="POST" onSubmit="return checkAGB2()">
    <ul class="actions">
        <!--TEAM 1 WÄHLEN-->
        <!--<li><input type="text" id="registergame_Team1" name="Team1" class="Eingabe" placeholder="Kürzel 1" style="color: white" required></li>-->
        <select name="Team1" id="teams_waehlen" >
            <option value="auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss"><i>Team 1 wählen</i></option>
            <?php
            $sqlTeam = 'SELECT * FROM `Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY kuerzel';
            $resultTeam = $conn->query($sqlTeam);
            while ($rowTeam = $resultTeam->fetch_assoc()) {
                $teamName = $rowTeam['name'];
                $teamKuerzel = $rowTeam['kuerzel'];
                echo "<option value=$teamKuerzel>$teamKuerzel | $teamName</option>";	//TODO: value = id? dann ist es eindeutig und es wäre theoretisch nicht so schlimm wenn es zwei gleiche Kürzel gibt						
            }
            ?>
        </select>
        <li><input type="number" min="0" max="3" type="text" id="registergame_Flaschen1" name="Flaschen1" class="Eingabe" placeholder="Flaschen 1*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
        <li><h1>:</h1></li>
        <!--TEAM 2 WÄHLEN-->
        <!--<li><input type="text" id="registergame_Team2" name="Team2" class="Eingabe" placeholder="Kürzel 2" style="color: white" required></li>-->
        <select name="Team2" id="teams_waehlen_2" >
            <option value="auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss"><i>Team 2 wählen</i></option>
            <?php
            $sqlTeam = 'SELECT * FROM `Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY kuerzel';
            $resultTeam = $conn->query($sqlTeam);
            while ($rowTeam = $resultTeam->fetch_assoc()) {
                $teamName = $rowTeam['name'];
                $teamKuerzel = $rowTeam['kuerzel'];
                echo "<option value=$teamKuerzel>$teamKuerzel | $teamName</option>";	//TODO: value = id? dann ist es eindeutig und es wäre theoretisch nicht so schlimm wenn es zwei gleiche Kürzel gibt						
            }
            ?>
        </select>
        <li><input type="number" min="0" max="3" id="registergame_Flaschen2" name="Flaschen2" class="Eingabe" placeholder="Flaschen 2*" style="color: black" required><p><i>Ausgetrunkene Flaschen*</i></p></li>
    </ul> 
    <p><i>* Bei "Ausgetrunkene Flaschen" einfach eintragen, wie viele Flaschen pro Team geleert wurden. Wenn ihr gewonnen habt und das Verliererteam trotzdem ein Bier geleert hat, wäre der Spielstand zum Beispiel 3:1 und ihr würdet einmal 3 und einmal 1 Flasche eintragen.</i></p>
    <h5><br/></h5>  
    <div class="field">
        <label for="demo-category">Gruppenphase oder KO-Phase</label>
        <select name="Phase" id="phase">           
            <option value="auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss">-</option>
            <option value="0">Gruppenphase</option>
            <?php
            //Turnier-Finalstufen finden
            $sqlTurnier = 'SELECT * FROM `Turnier` WHERE id = ' . $TurnierID . ' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $start_ko_finallevel=$rowTurnier["start_ko_finallevel"];									
            }
            $ko_finallevel = $start_ko_finallevel;
            while ($ko_finallevel > 0) {
                $sqlFinallevel = 'SELECT * FROM `KO_Finallevel` WHERE id = ' . $ko_finallevel . ' ORDER BY ID';
                $resultFinallevel = $conn->query($sqlFinallevel);
                while ($rowFinallevel = $resultFinallevel->fetch_assoc()) {
                     $FinallevelName = $rowFinallevel['name'];	
                     echo "<option value=$ko_finallevel>$FinallevelName</option>";								
                }
                $ko_finallevel--;									
            }
            ?>
            <!--<option value="ko_phase">KO-Phase</option>-->
        </select>
    </div>
    <h5><br/></h5>
    <label for="demo-category">Login</label>
    <input type="text" id="registergame_Login_Kuerzel" name="Login_Kuerzel" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
    <input type="password" id="registergame_Login_Passwort" name="Login_Passwort" class="Eingabe" placeholder="Dein Team-Passwort" style="color: white" required>
    <h5><br/></h5>                                 
    <script type="text/javascript">
        function checkAGB2() {
            if (document.getElementById('demo-human-registergame').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
    </script>  
    <div>
        <div class="field half">
            <input type="checkbox" id="demo-human-registergame" name="demo-human-registergame" unchecked>
            <label for="demo-human-registergame">Ergebnisse nicht gelogen auf Ehre.</label>
            <h5><br/></h5>  
        </div>
    </div>
    <p><button id="btn_login_Absenden_2" value="Absenden" type="submit">Eintragen</button></p>
    </form>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

















<h2>Hier gibt es (noch) nichts zu sehen.</h2> <!--class="major"-->
    <span class="image main"><img src="images/fotos/lennard_REDACTED.JPG" alt="" /></span>
    <p>Geh woanders hin.</p>




<h2 class="major">Info</h2>
    <p>Hier erhälst du einige Infos über den Turnierablauf, die Geschichte des Turniers und News!</p>
    <ul class="actions">
        <li><a href="#allgemeine_info" class="button">Über Blankiball</a></li>
        <li><a href="#platzhalter" class="button">Zeitplan</a></li>
        <li><a href="#news" class="button">News</a></li>
        <li><a href="#map" class="button">Location</a></li>
    </ul>
    <span class="image main"><img src="images/blanki_schnee.jpg" alt="" /></span>





<h2 class="major">Impressum</h2>
    <p>Das Turnier ist ausschließlich ein privates Zusammentreffen und keine öffentliche Veranstaltung. Diese Website ist keine offizielle Veranstaltungswebsite und Inhaber der Website sind keine Veranstalter.</p>
    <p></br></p>



<h2>KnockOut-Phase</h2>
    <ul class="actions">
        <li><a href="#spielplan" class="button">Zurück zur Übersicht</a></li>
        <li><a href="#registergame" class="button primary">Ergebnisse eintragen</a></li>
    </ul>
    <p>Hinweis: Solange die Gruppenphase noch nicht abgeschlossen ist zählt das was hier steht nicht weil die Website einfach automatisch die Teams mit den meisten Punkten hier einträgt, was sich natürlich nochmal ändern kann.</p>
    <?php //printKO_PhaseTabellen($TurnierID, $conn, $LoggedIn); ?>

<h2>Punktetabelle</h2>
    <ul class="actions">
        <li><a href="#spielplan" class="button">Zurück zur Übersicht</a></li>
        <li><a href="#registergame" class="button primary">Ergebnisse eintragen</a></li>
    </ul>                        
    <?php printPunktetabelleGruppenphase($TurnierID, $conn, $LoggedIn); ?>

<h2>Spielplan der Gruppenphase</h2>
    <p style="font-size: 0.8rem;">Hier erfahrt ihr, gegen wen ihr als Team antreten müsst. Macht euch die Termine für die Spiele einfach untereinander aus.</p>
    <ul class="actions">
        <li><a href="#spielplan" class="button">Zurück zur Übersicht</a></li>
        <li><a href="#registergame" class="button primary">Ergebnisse eintragen</a></li>
    </ul>
    <p style="font-size: 0.8rem;">Hinweis: "3:1" bedeutet nicht, dass vier Spiele gemacht wurden, sondern der Spielstand bezieht sich auf ein Spiel, bei dem das Gewinnerteam 3 Flaschen getrunken hat und das Verliererteam aber trotzdem eine Flasche geleert hat. Würde das Verliererteam keine Flasche leeren, wäre der Spielstand "3:0".</p>   
    <h3 style="font-size: 0.8rem;">Ein Spielstand ist nicht korrekt? Dann tippe einfach auf ihn, gib das Passwort deines Teams ein und ändere oder lösche den Spielstand.</h3>
    <p style="font-size: 0.8rem;"><i>Lesrichtung des Spielstands von Zeile nach Spalte</i></p>



<input type='text' id='changefunction1' name='function' class='Eingabe' placeholder='Function (einfach freilassen)' value='$function' style='color: white'>



<div class="content">
        <div class="inner">
            <h1>Der Spielplan <img src="images/icon/sterni1.png" width="40" height="40" border="10" alt="Home"></h1>
            <p>Das Turnier ist unterteilt in Gruppenphase und KO-Phase:</p>
        </div>
    </div>
    <nav>
        <ul>
            <h2 class="major">1. Gruppenphase</h2>
            <p>In der Gruppenphase wird jedes Team in eine Gruppe mit zwei bis drei anderen Teams gesteckt. Innerhalb dieser Gruppe treten alle Teams gegeneinander an. Nur die besten schaffen es in die KO-Phase.</p>
            <ul class="actions">
                <li><a href="#gruppenphase" class="button">Zum Spielplan</a></li>
                <li><a href="#punktetabelle" class="button">Zur Punktetabelle</a></li>
            </ul>
            <h2 class="major">2. KO-Phase</h2>
            <p>In der KO-Phase treten jetzt die besten Teams aus der Gruppenphase in einem vorher festgelegten System gegeneinander an. Sie heißt KO-Phase weil man durch eine Niederlage direkt ausscheidet.</p>	
            <ul class="actions">
                <li><a href="#kophase" class="button">Zur KO-Phase</a></li>
            </ul>						
        </ul>
        <p></br></p>
        
    </nav>



<h2>06.09. - 12.09.21</h2>
            <h1>Das Offizielle Blankiball - Turnier</h1>
            <p>Das größte Bierballturnier Berlins gesponsort von <a href="https://roehrli.de">Röhrli</a></p>


<h6 style="color: white"><br /></h6>
    <h3><a style="color: white;font-size:15px;" href="https://open.spotify.com/user/11129583931/playlist/3K13BWkhzAVwHdRM2F6P8Z">Der offizielle S<img src="images/icon/spoti.png" width="15" height="15" border="5" alt="Home">undtrack zum Turnier<br/></a></h3>
    <h6 style="color: white"><br /></h6>
    <img src="images/icon/insta.png" width="20" height="20" border="0" alt="Home">
    <h4><a style="color: white" href="https://www.instagram.com/REDACTED_official/?hl=de/">@REDACTED_official</a><br />
    <a style="color: white" href="https://www.instagram.com/explore/tags/REDACTED/">#REDACTED</a><br />
    <a style="color: white" href="https://www.instagram.com/roehrlitrinkhalme/?hl=de/">@roehrlitrinkhalme</a><br />
    <a style="color: white" href="https://www.instagram.com/sternburg.brauerei/?hl=de/">@sternburg.official</a><br />
    <a style="color: white" href="https://www.instagram.com/gretarthouse/?hl=de/">@gretarthouse</a></h4>                         
    <h6 style="color: white"><br /></h6>
    <a href="http://2019.REDACTED.de" class="button">Website von 2020</a>
    <h6 style="color: white"><br/></h6>

    <hr>

    
    <p class="copyright">&copy; Blankiball. <a href="#impressum">Impressum</a></p> 
    <p></p> 
    <p class="copyright"><a href="#platzhalter">Platzhalter</a> ... <a href="#elements">Testumgebung</a> ... <a href="#backstage">Backstage</a> ... <a href="#login">Login</a></p> 




<h2 class="major">Über uns</h2>
    <span class="image main"><img src="images/fotos/zoe_wirft_REDACTED.JPG" alt="" /></span>
    <p>Das Blankiball-Turnier ist Berlins größtes (privates) Bierball-Turnier. Einmal jährlich finden sich Spieler*innen aus ganz Berlin (natürlich alles Freund*innen und Bekannte - keine Veranstaltung) im <a style="color: white" href=http://www.berlin.de/senuvk/umwelt/stadtgruen/gruenanlagen/de/gruenanlagen_plaetze/pankow/blankensteinpark/index.shtml><u>Blankensteinpark</u></a> zusammen und spielen gegeneinander um den Titel des/der Bierball-Königs*in.</p>
    
    <h2 class="major">Geschichte</h2>
    <span class="image main"><img src="images/fotos/nico_jonas_REDACTED.JPG" alt="" /></span>
    <p>Gegründet wurde die Blankiball-Tradition 2017, wo es zunächst noch lokal von Heinrich-Hertz-Gymnasiast*innen ausgetragen wurde. Zwei Jahre in Folge wurde das Turnier noch unter dem Namen "Heinrich-Hertz-Bierball-Turnier" ausgetragen, an die 100 Anmeldungen erreichte das Turnier 2018. Mit Steigerung der Popularität nahmen auch berlinweit Spieler*innen am Turnier teil und mittlerweile wurde es zum Blankiball-Turnier umbenannt (setzt sich zusammen aus Blankensteinpark und Bierball).</p>
    
    <h2 class="major">Mitspielen</h2>
    <span class="image main"><img src="images/fotos/bakthaan_jonas_alex.jpg" alt="" /></span>
    <p>Es ist prinzipiell jedem*r erlaubt mitzuspielen, solange er/sie noch nüchtern genug ist um ein Bier zu halten. Außerdem können nur Teams aus genau drei Spieler*innen antreten und es muss sich an die <a style="color: white" href="index.php#regeln"><u>offiziellen Regeln</u></a> gehalten werden. Das Bier muss selbst bezahlt werden. Anmelden kann man sich ganz einfach <a style="color: white" href="#anmelden"><u>hier</u></a>.</p>
    
    <h2 class="major">Turnierablauf</h2>
    <span class="image main"><img src="images/fotos/team1_REDACTED.JPG" alt="" /></span>
    <p>Ein durchschnittliches Blankiballturnier dauert ca. 1 Woche und der/die durchschnittliche Teilnehmer*in hat ca. 5 Bier pro Tag und 30 pro komplettem Tunier zu trinken. Ein Turnier durchläuft Gruppenphase und KO-Phase aber nur die besten und geübtesten Teams schaffen es bis zu den Finalspielen. Falls du mit deinem Team antreten möchtest, solltest du dir möglichst die ganze Woche freihalten, die Spiele finden in der Regel nachmittags bis abends statt. Die genauen Zeiten machen sich die Kontrahent*innen einfach untereinander aus.</p>
    
    <h2 class="major">Anforderungen</h2>
    <span class="image main"><img src="images/fotos/bakthaan_REDACTED.jpg" alt="" /></span>
    <p>Anders als bei anderen Sportturnieren kommt es beim Blankiballturnier auf sehr unterschiedliche Kompetenzen an. Neben dem schnellen Trinken werden vom Spieler*in Fähigkeiten im gezieltem Werfen und reaktionsschnellem und geradem Laufen verlangt. Je weiter die Rundenzahl vorschreitet, desto schwieriger kann es für das Team werden, eine konstante Trefferquote durchzuhalten.</p>
    



#changecontent
<?php
    $contentID = $_POST['contentID'];
    $content = $_POST['content']; //?id=$contentID
    echo "<p>Content mit der ID $contentID wird bearbeitet</p>
        <form action='website_datachange/changecontent.php' method='POST' onSubmit='return checkAGB3()''>
        <input type='text' id='changecontent1' name='content' class='Eingabe' value='$content' style='color: white' required>
        <input type='hidden' name='contentID' value='$contentID'/>";
    ?>
    <h5><br/></h5>
    <p>Bitte bestätige noch einmal deine Anmeldedaten:</p>
    <input type="text" id="changecontent_Login_bn" name="bn" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
    <input type="password" id="changecontent_Login_pw" name="pw" class="Eingabe" placeholder="Dein Team-Passwort" style="color: white" required>
    <h5><br/></h5>                                 
    <script type="text/javascript">
        function checkAGB3() {
            if (document.getElementById('demo-human-changecontent').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
    </script>  
    <div>
        <div class="field half">
            <input type="checkbox" id="demo-human-changecontent" name="demo-human-changecontent" unchecked>
            <label for="demo-human-changecontent">Ich verstehe, dass gelöschte oder veränderte Elemente nicht wiederhergestellt werden können.</label>
        </div>
    </div>
    <h5><br/></h5>
    <!--<p><button id='btn_login3' name='gameId' type='submit'>Ändern</button></p>-->
    <?php //$id = $_POST['action'];   
        echo "<input type='submit' name='action' value='Ändern'/>";
        echo "<input type='submit' name='action' value='Löschen'/>"; ?> <!--  || value=$gameID -->
    </form>




<!-- ########################## -->
<!-- ########  REGELN OLD  ######### -->
<!-- ########################## -->
<article id="regelnold">					
    <h1>Offizielle Regeln</h1>
    <h2>Spiel</h2>
    <?php if ($LoggedIn) {
        echo "<a style='color: green' href='#'>Hier neue Regel einfügen</a>";                      
    }?>  
        <ul class="alt">                   
            <?php
                $sql = 'SELECT * FROM `Content_Regeln` WHERE Kategorie = "Spiel" ORDER BY ID';
                $result = $conn->query($sql);
                while ($row = $result->fetch_assoc()) {
                        $a=$row["Regel"];
                        $content_id=$row["ID"];
                        echo "<li>$a</li>";
                        if ($LoggedIn) {
                            echo "<a style='color: green' href='#'>Bearbeiten</a>    
                            <form method='post' action='#changecontent'>
                                <button style='color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;background:none;outline: none;border-top: none;' class='height: 1px;' name='action' value='' class='button primary'>Bearbeiten</button>
                                <input type='hidden' name='content_ID' value='$content_id'/>
                                <input type='hidden' name='content' value='$a'/>
                            </form> ";               
                        }
                }
            ?>
        </ul>
        <!-- TODO: Nicht die Kategoerien einzeln sondern GROUP BY?-->
        <!-- TODO: Auch Button: HINZUFÜGEN-->
    <h2>Teams</h2>
    <?php if ($LoggedIn) {
        echo "<a style='color: green' href='#'>Hier neue Regel einfügen</a>";                      
    }?>
        <ul class="alt"> 
            <?php
                $sql = 'SELECT * FROM `Content_Regeln` WHERE Kategorie = "teams" ORDER BY ID';
                $result = $conn->query($sql);
                while ($row = $result->fetch_assoc()) {
                        $a=$row["Regel"];
                        echo "<li>$a</li>";
                        if ($LoggedIn) {
                            echo "<a style='color: green' href='#'>Bearbeiten</a>";                      
                        }
                }
            ?>
        </ul>
    <h2>Was noch gesagt werden muss</h2>
    <?php if ($LoggedIn) {
        echo "<a style='color: green' href='#'>Hier neue Regel einfügen</a>";                      
    }?> 
        <ul class="alt"> 
            <?php
                $sql = 'SELECT * FROM `Content_Regeln` WHERE Kategorie = "Was noch gesagt werden muss" ORDER BY ID';
                $result = $conn->query($sql);
                while ($row = $result->fetch_assoc()) {
                        $a=$row["Regel"];
                        echo "<li>$a</li>";
                        if ($LoggedIn) {
                            echo "<a style='color: green' href='#'>Bearbeiten</a>";                      
                        }
                }
            ?>
        </ul>
    <ul class="actions">
            <li><a href="#" class="button">Zurück zur Startseite</a></li>
    </ul>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
                             
 
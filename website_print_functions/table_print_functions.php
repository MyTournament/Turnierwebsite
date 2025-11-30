<?php
    function helloHermann($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        $test = "baum";
        echo "Hallo Hermann $test";
        echo "$TurnierID";
    }

    function printTeamInfo($TurnierID, $conn, $teamId){ //NICHT IM CMS
        //Teamnamen herausfinden
        if($teamId != NULL){
            $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $teamId . ' ORDER BY id';
            $result = $conn->query($sql);
            $teamName = " ";
            if (!empty($row = $result->fetch_assoc())) {
                $teamName = $row['name'];
                $teamKuerzel = $row['kuerzel'];
                $gruppeId = $row['fk_gruppe'];
                $endplatzierung = $row['endplatzierung'];
            }
            echo "<h1>$teamName ($teamKuerzel)</h1>";
            //TEAMMITGLIEDER RAUSFINDEN
            $sql = 'SELECT * FROM Turnier_Spieler_in WHERE fk_team = ' . $teamId . ' ORDER BY id';
            $result = $conn->query($sql);
            echo "<ul class='alt'>";
            $zaehler = 1;
            while (!empty($row = $result->fetch_assoc())) {
                $spielerId = $row['id'];
                $spielerNameWithLink = printSpielerWithLink($conn, $spielerId);
                echo "<li>$zaehler. Spieler*in: <b>$spielerNameWithLink</b></li>";
                $zaehler++;
            }
            echo "</ul>";
            echo "<br/>";
            
            echo "<h2>Gruppe</h2>";
            if($gruppeId != NULL){
                $sql = 'SELECT * FROM Turnier_Gruppe WHERE id = ' . $gruppeId . ' ORDER BY id';
                $result = $conn->query($sql);
                $gruppenName = " ";
                if (!empty($row = $result->fetch_assoc())) {
                    $gruppenName = $row['name'];
                }
                echo "<p><b>$gruppenName</b></p>";
            }else{
                echo "<p><i>noch keiner Gruppe zugeteilt</i></p>";
            }
            
            
            echo "<br/>";

            echo "<h2>Spiele</h2>";
            echo "
            <table class='withBorderCollapse'>
                <thead>
                    <tr>
                        <th>Finallevel</th>
                        <th>Team A</th>
                        <th>Spiele</th>
                        <th>Team B</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>";
            $sql = 'SELECT * FROM Turnier_Begegnung WHERE `status` NOT IN (3,6) AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY id';
            $result = $conn->query($sql);
            while (!empty($row = $result->fetch_assoc())) {
                $begegnungId = $row['id'];
                $heimteamID=$row["fk_heimteam"];
                $auswaertsteamID=$row["fk_auswaertsteam"];
                $ko_finallevel=$row["ko_finallevel"];

                //Namen der Teams finden
                //Team 1
                $sqlTeam1 = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $heimteamID . ' ORDER BY ID';
                $result1 = $conn->query($sqlTeam1); 
                $rowTeam1 = $result1->fetch_assoc();
                $heimteam = $rowTeam1["name"];
                //$heimteamkuerzel = $rowTeam1["kuerzel"];
                $teamId1 = $rowTeam1["id"];
                //Team 2
                $sqlTeam2 = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $auswaertsteamID . ' ORDER BY ID';
                $result2 = $conn->query($sqlTeam2);
                $rowTeam2 = $result2->fetch_assoc();
                $auswaertsteam = $rowTeam2["name"];
                //$auswaertsteamkuerzel = $rowTeam2["kuerzel"];
                $teamId2 = $rowTeam2["id"];
                
                
                //FINALLLEVEL
                $sqlFinallevel = 'SELECT * FROM `Turnier_KO_Finallevel` WHERE id = ' . $ko_finallevel . ' ORDER BY ID';
                $resultFinallevel = $conn->query($sqlFinallevel); 
                $rowFinallevel = $resultFinallevel->fetch_assoc();
                $finallevel_name = $rowFinallevel["name"];
                echo "<td>$finallevel_name</td>"; //Heimteam kommt ganz links hin
                
                //Ausgeben
                echo "<td>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
                
                //Spiele zu den Begegnungen finden
                $status = $row['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                printGames($TurnierID, $conn, $begegnungId, 0, $status);
                
                echo "</td><td>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td></tr><tr>"; //Auswärtsteam kommt ganz rechts hin		
                $zaehler++;
            }

            $sqlSiegesquote = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $teamId . ' ORDER BY ID';
            $resultSiegesquote = $conn->query($sqlSiegesquote); 
            $rowSiegesquote = $resultSiegesquote->fetch_assoc();
            $siegesquote = $rowSiegesquote['siegesquote'];

            echo"   </tr>
                </tbody>
            </table>";
            echo "<br/>";

            echo "<h2>Siegesquote</h2>";
            if($siegesquote !== NULL){
                echo "<p><b>".round($siegesquote)." %</b></p>";
            }else{
                echo "<p><i>noch keine Spiele gespielt</i></p>";
            }
            echo "<br/>";

            echo "<h2>Endplatzierung</h2>";
            if($endplatzierung !== NULL && $endplatzierung !== 0){
                echo "<p><b>$endplatzierung</b></p>";
            }else{
                echo "<p><i>noch nicht bestimmt</i></p>";
            }

            echo "<br/>";
            echo "<a href='/website_functionalities/generate_team_certificate/generate_team_certificate.php?teamId=$teamId&turnierId=$TurnierID' class='button primary'>Teamzertifikat zum Drucken</a>";
            echo "<br/>";
            
            echo "<br/>";
        }
        
    }


    function printSpielerInfo($TurnierID, $conn, $spielerId){ //NICHT IM CMS

        /*
        //FALL: Team-Login -> Bearbeitungsrechte nur für eigene Begegnungen
        $sqlLogin = "SELECT * FROM `Team` WHERE kuerzel = '$bn' AND password = '$pw' ORDER BY ID";
        $resultLogin = $conn->query($sqlLogin);
        $spielGehoertZuTeam = 0; //false
        $teamBearbeitungsrecht = 0;
        while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
            $successfulLogin = 2;
            $teamBearbeitungsrecht = $rowLogin["bearbeitungsrechte"];
            //echo "<script>console.log('Du bist eingeloggt als Team.')</script>";
            //checken ob Begegnung zu Team-Kürzel passt, das sich eingeloggt hat
            $sql = "SELECT * FROM Team, Begegnung WHERE Begegnung.id = '$begegnungId' AND ((Begegnung.fk_heimteam IN (SELECT id FROM Team WHERE kuerzel = '$bn' AND `password` = '$pw')) OR (Begegnung.fk_auswaertsteam IN (SELECT id FROM Team WHERE kuerzel = '$bn' AND `password` = '$pw')))"; // AND fk_turnier = '$TurnierID'
            $result = $conn->query($sql);
            while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
            $spielGehoertZuTeam = 1;
            //echo "<script>console.log('Das Spiel gehört zu deinem Team. Das ist gut.')</script>";
            }
        }
        */
        //LOGIN
            $TurnierID = isset($_POST['TurnierID']) ? $_POST['TurnierID'] : $TurnierID;

            $stmt = $conn->prepare("SELECT * FROM `System_Benutzer_in` ORDER BY ID");
            $stmt->execute();
            $benutzerliste = $stmt->get_result();
            
            $bn = $_POST['bn'];
            $pw = $_POST['pw'];
            $successfulLogin = 0; //false
            $teamBearbeitungsrecht = 0; // Veraltet?
            foreach ($benutzerliste as $b){
                if(
                    $b['Benutzername'] == $bn and
                    $b['Passwort'] == $pw and
                    $b['fk_rechte'] <= 15
                ){
                    $successfulLogin = 1;
                    $teamBearbeitungsrecht = 1;
                    $rechte = $b['fk_rechte'];
                }
            }
        

        //Teamnamen herausfinden
        if($spielerId != NULL && ($successfulLogin == 1 || $successfulLogin == 2)){
            $sql = 'SELECT * FROM Turnier_Spieler_in WHERE id = ' . $spielerId . ' ORDER BY id';
            $result = $conn->query($sql);
            $spielerName = " ";
            while (!empty($row = $result->fetch_assoc())) {
                $spielerName = $row['name'];
                $tel = $row['telefonnummer'];
                $fk_team = $row['fk_team'];
            }
            echo "<h1>$spielerName</h1>";
            //TEAM RAUSFINDEN
            $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $fk_team . ' ORDER BY id';
            $result = $conn->query($sql);
            $teamName = " ";
            while (!empty($row = $result->fetch_assoc())) {
                $teamName = $row['name'];
                $teamId = $row['id'];
            }
            $teamKuerzel = printKuerzelWithLink($conn, $teamId);
            echo "Team: <b>$teamName ($teamKuerzel)</b>";
            echo "<br/>";

            if($tel != NULL && $tel != "" && $tel != " "){
                echo "Telefonnummer: <b>$tel</b>";
            }else{
                echo "Telefonnummer: <b><i>Keine Nummer hinterlegt</i></b>";
            }
            
            echo "<br/><br/>";
            echo"
            <form action='website_functionalities/vcard.php' method='POST'>
                <button id='btn_login_Absenden' class='button primary' value='Absenden' type='submit'>Kontakt aufs Handy importieren</button>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='spielerId' value='$spielerId'/>
            </form>
            ";
        }else{
            echo "<h1>Hoppala!</h1>";
            echo "<h2>Da ist wohl etwas schiefgelaufen. Wahrscheinlich stimmt etwas mit deinem Login nicht.</h2>";
        }
        
    }

    function printTeams($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY ID'; //WHERE Freischaltung = 1
        $resultTeam = $conn->query($sqlTeam);
        $zeahler = 1;
        
        while ($rowTeam = $resultTeam->fetch_assoc()) {
            $a=$rowTeam["name"];
            $name_link=$rowTeam["name_link"];
            if($name_link != NULL){
                $a=$name_link;
            }
            $teamId = $rowTeam["id"];
            $b=printKuerzelWithLink($conn, $teamId);	
            $ausgabeString = "";				
            $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";					
            $sqlSpieler = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team = ' . $rowTeam["id"] . ' ORDER BY ID'; //WHERE Freischaltung = 1    
            $resultSpieler = $conn->query($sqlSpieler);
            while ($rowSpieler = $resultSpieler->fetch_assoc()) {
                $spielerId=$rowSpieler["id"];
                $x=$rowSpieler["name"];
                $y=printSpielerWithLink($conn, $spielerId);
                $ausgabeString .=  " $y ";
                $ausgabeString .=  "&#x007C;";
            }
            $zeahler++;
            $ausgabeString = substr($ausgabeString, 0, -8);
            echo "<li>$ausgabeString</li>";
        }
    }
    function printSchiedsrichterInnen($TurnierID, $conn, $LoggedIn, $gameEditMode,$expertenmodus){
        echo"
        <ul class='alt'>";
        $sql = 'SELECT * FROM `System_Benutzer_in` WHERE fk_rechte <= 20 ORDER BY id ASC';
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $Benutzername = $row['Benutzername'];
            echo "<li>$Benutzername</li>";
        }
        echo"</ul>";
    }
    function printGroupsAsTable($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        echo"<table class='withBorderCollapse'>
                <thead>
                    <tr>
                        <td><i>Gruppenname</i></td>
                        <td><i>Teams</i></td>
                    </tr>
                </thead>
                <tbody>";
        $sqlGroup = 'SELECT * FROM `Turnier_Gruppe` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id'; //WHERE Freischaltung = 1
        $resultGroup = $conn->query($sqlGroup);
        while ($rowGroup = $resultGroup->fetch_assoc()) {
            echo "<tr>";
            $groupName= $rowGroup['name'];
            $groupId= $rowGroup['id'];
            echo"<td>Gruppe <b>$groupName</b>:</td>";
            //Teams zur Gruppe finden
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = '.$groupId.' ORDER BY ID'; //WHERE Freischaltung = 1
            $resultTeam = $conn->query($sqlTeam);
            while ($rowTeam = $resultTeam->fetch_assoc()) {
                $teamId= $rowTeam['id'];
                echo"<td>";$return = printKuerzelWithLink($conn, $teamId);echo"$return</td>";
            }
            echo "</tr>";
        }
        echo "</tbody>
        </table>";
    }
    function printTurnierbaum($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        //Start-Finalstufe rausfinden
        $sql = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID;
        $result_sql = $conn->query($sql);
        while ($row_sql = $result_sql->fetch_assoc()) {
            $start_ko_finallevel = $row_sql["start_ko_finallevel"];
        }
        $ko_finallevel = $start_ko_finallevel; //Zähler
        echo "<div style='text-align: center'>";
        echo "
        <table style='text-align: center;'>
            <thead>
                <tr>";
                    while($ko_finallevel>1){
                        $sql = 'SELECT * FROM Turnier_KO_Finallevel WHERE id = ' . $ko_finallevel;
                        $result_sql = $conn->query($sql);
                        while ($row_sql = $result_sql->fetch_assoc()) {
                            $name = $row_sql["name"];
                            echo "<td><i>$name</i></td>";
                        }
                        $ko_finallevel--;
                    }
                    echo "<td><i>Gewinnerteam</i></td>";
                    $ko_finallevel = $start_ko_finallevel; //zurücksetzen
        echo"
                </tr>
            </thead>
            <tbody>
            ";
            //Einen Tree erstellen
            $recursiveTree = recursiveTreeHelper($ko_finallevel);
            //echo "$recursiveTree";

            //SQL-ABFRAGE IN (doppeltes) ARRAY SCHREIBEN
            $treeArray = array(); //Array erstellen
            //array_push($treeArray, "test");
            //$treeArray[] = "test";
            //$treeArray[] = "abc";
            //$test = $treeArray[0];
            //$test2 = $treeArray[1];
            //echo "$test $test2";
            
            $index = 0;
            while ($ko_finallevel > 0){
                $zaehlerForKoPosition = 1;
                while($zaehlerForKoPosition < pow(2,($ko_finallevel-2))+1){ //Zähler bis zu 2^x (x=Finalstufe, zB Stufe 4 hat 2^(4-1)=8 ) ||| -2 weil ja 2 und nicht 1 das Finale ist
                    //Begegnung (eine) finden, die zum Zähler passt + restliche Bed. (zB der vorherigen  Stufe & des aktuellen Turniers)
                    $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = ' . $ko_finallevel . ' AND ko_turnierbaumposition = '. $zaehlerForKoPosition .' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition ASC, id ASC'; //AND NOT fk_siegerteam = NULL 
                    $resultBegegnung = $conn->query($sqlBegegnung);
                    $siegerGefunden = false;
                    $zumindestBegegnungGefunden = false;
                    while (!empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                        if($rowBegegnung['status'] == 5){
                            //ID ablesen
                            $teamId = $rowBegegnung['fk_siegerteam'];
                            //Namen zur ID finden
                            if($teamId != NULL){
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $teamId .'';
                                $resultTeam = $conn->query($sqlTeam);
                                while ($rowTeam = $resultTeam->fetch_assoc()) {
                                    $teamId = $rowTeam['id'];
                                    $teamString = $rowTeam['name'];
                                    $teamString .= " (";
                                    $teamString .= printKuerzelWithLink($conn, $teamId);
                                    $teamString .= ")";
                                }
                            }
                            //INS ARRAY EINFÜGEN
                            $treeArray[$index][$zaehlerForKoPosition-1] = $teamString;

                            $siegerGefunden = true;
                        }
                        
                        //SONDERFALL: ERSTES FINALLEVEL - hier können keine Gewinner abgelesen werden, sondern es müssen einfach das Heimteam und Auswärtsteam abgelesen werden
                        //dieser Sonderfall soll auch dann passieren, wenn die Begegnung noch nicht final ist
                        if($ko_finallevel == $start_ko_finallevel){
                            //TEAM 1
                            $teamId1 = $rowBegegnung['fk_heimteam'];
                            //Namen zur ID finden
                            if($teamId1 != NULL){
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $teamId1 .'';
                                $resultTeam = $conn->query($sqlTeam);
                                while ($rowTeam = $resultTeam->fetch_assoc()) {
                                    $teamId = $rowTeam['id'];
                                    $teamString1 = $rowTeam['name'];
                                    $teamString1 .= " (";
                                    $teamString1 .= printKuerzelWithLink($conn, $teamId);
                                    $teamString1 .= ")";
                                }
                            }
                            //TEAM 2
                            $teamId2 = $rowBegegnung['fk_auswaertsteam'];
                            //Namen zur ID finden
                            if($teamId2 != NULL){
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $teamId2 .'';
                                $resultTeam = $conn->query($sqlTeam);
                                while ($rowTeam = $resultTeam->fetch_assoc()) {
                                    $teamId = $rowTeam['id'];
                                    $teamString2 = $rowTeam['name'];
                                    $teamString2 .= " (";
                                    $teamString2 .= printKuerzelWithLink($conn, $teamId);
                                    $teamString2 .= ")";
                                }
                            }
                            //INS ARRAY EINFÜGEN
                            $treeArray[$index-1][$zaehlerForKoPosition*2-1] = $teamString1;
                            $treeArray[$index-1][$zaehlerForKoPosition*2] = $teamString2;

                            $zumindestBegegnungGefunden = true;
                        }
                        
                    }
                    if($siegerGefunden == false){ //Falls keine Begegnung gefunden wurde, wird ein Platzhalter eingefügt
                        $treeArray[$index][$zaehlerForKoPosition-1] = "...";
                    } 
                    if($zumindestBegegnungGefunden == false){
                        //SONDERFALL: ERSTES FINALLEVEL - hier können keine Gewinner abgelesen werden, sondern es müssen einfach das Heimteam und Auswärtsteam abgelesen werden
                        if($ko_finallevel == $start_ko_finallevel){
                            $treeArray[$index-1][$zaehlerForKoPosition*2-1] = "...";
                            $treeArray[$index-1][$zaehlerForKoPosition*2] = "...";
                        }
                    }
                    $zaehlerForKoPosition++;
                }
                $index++;
                $ko_finallevel--;
            }
            $ko_finallevel = $start_ko_finallevel; //zurücksetzen
            
            //Immer das erste Element des jeweiligen Unterarrays rausnehmen, je nachdem welche Number gerade im Tree steht
            for ($i = 0; $i < strlen($recursiveTree); $i++) {
                echo "<tr>";
                //echo "<td></td>";
                $c = $recursiveTree[$i];
                $zaehler=1;
                while($zaehler <= $ko_finallevel){
                    if($c == $zaehler){
                        $value = array_shift($treeArray[$c-2]); //array_pop(array_reverse($treeArray[$c-1]));
                        if($value != NULL){
                            echo "<td style='background-color: green'>$value</td>";
                        }else{
                            echo "<td style='background-color: green'><i>...</i></td>";
                        }
                        
                    }else{
                        echo "<td style='background-color:black'></td>";
                    }
                    $zaehler++;
                }
                echo "</tr>";
            }
        echo"
            </tbody>
        </table>
        ";  
        echo "</div>";      
    }

    //$recursiveTreeTest = recursiveTreeHelper(4);
    //echo "$recursiveTreeTest";
    function recursiveTreeHelper($limit){ //returnt zB 121312141213121 wenn limit=4
        return recursiveTree(1, 1, $limit);
        
    }
    function recursiveTree($max_now, $previous_chain, $limit){
        if($max_now < $limit){
            $new_max = $max_now+1;
            $new_chain = "$previous_chain"."$new_max"."$previous_chain";
            return recursiveTree($new_max, $new_chain, $limit);
        }else{
            return $previous_chain;
        }
    }

    function trigger_sieger_innen_treppe($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        $platzierungen = []; //Array erstellen
        $zeahler = 0;
        while($zeahler<3){
            $actPlatzierung = $zeahler+1;
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND endplatzierung > 0 AND endplatzierung = '.$actPlatzierung.' ORDER BY endplatzierung ASC'; //AND NOT endplatzierung = NULL
            $resultTeam = $conn->query($sqlTeam);
            $platzierungen[$zeahler] = "platzhalter";
            while (!empty($rowTeam = $resultTeam->fetch_assoc())) {
                $platzierungen[$zeahler] = $rowTeam['name']; //Gruppengröße
            }
            $zeahler++;
        }
        if($platzierungen[0]!="platzhalter"||$platzierungen[1]!="platzhalter"||$platzierungen[2]!="platzhalter"){
            print_sieger_innen_treppe($platzierungen);
            //echo "1";
        }
    }
    function print_sieger_innen_treppe($platzierungen){
        //class='table' class='th-text-center' 
        echo"
        <br/><br/>
        <h2>Die Ergebnisse stehen fest!</h2>
        <br/><br/>
        <div style='align-items: center'> <!-- max-width:1000px; -->
            <div style='display:grid; grid-template-columns: 1fr'>
                <div></div>
                <div style='position:relative;'>"; if($platzierungen[0] != "platzhalter"){$x=$platzierungen[0];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</div>
            </div>
            <div style='display:grid; grid-template-columns: 1fr 1fr 1fr'>
                <div></div>
                <div style='background-color:red'><h1>1. &#10026;</h1></div>
                <div></div>
                <div style='position:absolute; right: 2%'>"; if($platzierungen[1] != "platzhalter"){$x=$platzierungen[1];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</div>
            </div>
            <div style='display:grid; grid-template-columns: 1fr 1fr 1fr'>
                <div></div>
                <div style='background-color:red'></div>
                <div style='background-color:red'><h1>2. &#10026;</h1></div>
                <div style='position:absolute; left: 2%;'>"; if($platzierungen[2] != "platzhalter"){$x=$platzierungen[2];echo "<h3>$x</h3>";}else{echo "<h3><i>noch nicht bestimmt</i></h3>";} echo"</div>
            </div>
            <div style='display:grid; grid-template-columns: 1fr 1fr 1fr'>
                <div style='background-color:red'><h1>3. &#10026;</h1></div>
                <div style='background-color:red'></div>
                <div style='background-color:red'></div>
            </div>
        </div> 


        
        <br/>
        <a href='#rangliste' class='button primary'>Gesamte Platzierung</a>
        <br/><br/><br/>
        ";
    }

    function print_platzierungen($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        // Phase-Titel vorbereiten
        $phaseLabels = array(0 => 'Gruppenphase (ausgeschieden)');
        $sqlLevels = 'SELECT id, name FROM Turnier_KO_Finallevel';
        $resLevels = $conn->query($sqlLevels);
        while ($resLevels && ($rl = $resLevels->fetch_assoc())) {
            $phaseLabels[(int)$rl['id']] = $rl['name'];
        }
        $phaseLabels['offen'] = 'Noch laufend';

        // Daten einsammeln
        $teamsByPlacement = array();
        $limit = 0;
        // Endplatzierung + platziert_level gemeinsam auslesen, damit die Trennlinien nach platziert_level gesetzt werden k��nnen
        $sqlTeam = 'SELECT id, name, endplatzierung, platziert_level FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY endplatzierung ASC';
        $resultTeam = $conn->query($sqlTeam);
        while ($resultTeam && ($rowTeam = $resultTeam->fetch_assoc())) {
            $limit++;
            if ($rowTeam['endplatzierung'] !== NULL) {
                $teamsByPlacement[(int)$rowTeam['endplatzierung']] = array(
                    'name' => $rowTeam['name'],
                    'id' => (int)$rowTeam['id'],
                    'level' => isset($rowTeam['platziert_level']) ? (int)$rowTeam['platziert_level'] : NULL
                );
            }
        }

        echo "<ul class='alt' style='margin-bottom: 0;'>";
        $currentPhase = '__none__';
        for ($platzierungsZaehler = 1; $platzierungsZaehler <= $limit; $platzierungsZaehler++){
            $teamName = "<i>noch nicht bestimmt</i>";
            $teamId = NULL;
            $phaseKey = 'offen';
            if (isset($teamsByPlacement[$platzierungsZaehler])) {
                $teamName = $teamsByPlacement[$platzierungsZaehler]['name'];
                $teamId = $teamsByPlacement[$platzierungsZaehler]['id'];
                $phaseKey = ($teamsByPlacement[$platzierungsZaehler]['level'] === NULL) ? 'offen' : $teamsByPlacement[$platzierungsZaehler]['level'];
            }
            $phaseLabel = (isset($phaseLabels[$phaseKey])) ? $phaseLabels[$phaseKey] : 'KO-Level ' . $phaseKey;

            // Visuelle Trennung pro Phase
            if ($phaseKey !== $currentPhase) {
                if ($currentPhase !== '__none__') {
                    echo "<li style='list-style:none; margin:0.35rem 0; padding:0;'><hr style='border:1px solid #d22; opacity:0.7; margin:0.2rem 0;'></li>";
                }
                echo "<li style='list-style:none; color:#b00; font-size:0.75rem; letter-spacing:0.08em; text-transform:uppercase; margin:0.25rem 0 0.05rem;'>$phaseLabel</li>";
                $currentPhase = $phaseKey;
            }

            $line = $platzierungsZaehler . '. ';
            if ($teamId !== NULL) {
                $kuerzel = printKuerzelWithLink($conn, $teamId);
                $line .= $teamName . " ($kuerzel)";
            } else {
                $line .= $teamName;
            }
            echo "<li>$line</li>";
        }
        echo "</ul>";
        echo "<br/>";
        echo "<div class='note'>Die Endplatzierung bleibt zwischen den Phasen fix: alle Gruppenphasen-Aussteiger stehen unter den KO-Leveln. Im Losing Bracket dürfen Teams nur innerhalb ihres KO-Levels (bzw. innerhalb der Gruppenphasen-Aussteiger) ihre Reihenfolge verschieben. Wertung: Punkte &gt; Flaschen &gt; Spiele (aufsteigend).</div>";
    }

    function printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, $action, $test_turnier_id){
        if($gameEditMode == 1){
            echo "<h2 style='color:#00FF00'>Bearbeitungsmodus</h2>";
            echo "<div class='note' style='font-size: 0.6rem;'>";
            echo "<ul class='alt'>";
            echo "<li style='color:#00FF00'><button style='background-color:#7700FF;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' class='button primary'>+</button> Über die Plus-Buttons kannst du neue Spielstände hinzufügen.</li>";
            //echo "<li style='color:#00FF00'><button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>3:0</button> Ein Spielstand ist nicht korrekt? Dann tippe einfach auf ihn, gib das Passwort deines Teams ein und ändere oder lösche den Spielstand.</li>";
            
            // TODO move those single line SELECT statements to a central method, that gives back the array from result...->fetch_assoc(). So we can reuse it in the whole code.
            $sqlGetNurOberesDreieckInGruppenphase = "SELECT nurOberesDreieckInGruppenphase FROM Turnier_Main WHERE id = ". $TurnierID;
            $resultNurOberesDreieck = $conn->query($sqlGetNurOberesDreieckInGruppenphase);
            $rowNurOberesDreieck = $resultNurOberesDreieck->fetch_assoc();
            $nurOberesDreieck = $rowNurOberesDreieck['nurOberesDreieckInGruppenphase'];
            
            if($nurOberesDreieck === 1 || $action === "#kophase") { 
                    echo "<li style='color:#00FF00'><button style='background-color:green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&check;</button> Sobald ihr alle Spiele gegen ein bestimmtes Team eingetragen habt müsst ihr noch einmal das grüne Häkchen anklicken, damit die Website weiß, dass sie auf keine Spiele mehr warten muss und schon die Teams schon für die kommenden Spiele berechnen kann.</li>";}
            else {  echo "<li style='color:#00FF00'><button style='background-color:green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&check;</button> Mit diesen Buttons markiert ihr ein Spiel als final, also entweder weil alle Spiele dieser Begegnung bespielt wurden oder weil das Match ergebnislos bleibt. </li>";}
            echo "<li style='color:#00FF00'><button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&#9733;</button> Dieser Button zeigt an, dass ein Spiel als final markiert wurde. Solltet ihr nachträglich doch noch ein Spiel eintragen wollen, könnt ihr euch an einen Administrator wenden.</li>";
            //grey: #888888
            if(!$expertenmodus){
                echo "
                    <form style='color:#00FF00' method='post' action=?test_turnier_id=$test_turnier_id$action>      
                        <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>E</button>
                        <p>Expertenmodus (nur für Admins - kann genutzt werden um Begegnungen zu verändern/zu sperren)</p>  
                        <input type='hidden' name='gameEditMode' value='1'/>
                        <input type='hidden' name='expertenmodus' value='1'/>
                    </form>
                ";
            }else{
                echo "
                    <form style='color:#00FF00' method='post' action=?test_turnier_id=$test_turnier_id$action>      
                        <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>V</button>
                        <p>Expertenmodus verlassen</p>  
                    </form>
                    <input type='hidden' name='gameEditMode' value='1'/>
                    <input type='hidden' name='expertenmodus' value='0'/>
                ";
            }
            echo "</ul>";
            echo "</div>";
            
            echo "
            <form style='color:#00FF00' method='post' action=?test_turnier_id=$test_turnier_id$action>      
                <button  style='background-color:green;' name='content' class='button primary'>Bearbeitungsmodus verlassen</button>  
                <input type='hidden' name='gameEditMode' value='0'/>
            </form> ";    
        }else{
            //Aktuelle Turnierphase herausfinden - erstmal ID
                $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                $resultTurnier = $conn->query($sqlTurnier);
                while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                    $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                }

                //SPIELPLAN
                if($turnier_phase_ID == 7 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
                    //Button um Bearbeitungsmodus zu aktivieren -> nur wenn Turnierphase dazu passt
                    echo"
                    <form method='post' action=?test_turnier_id=$test_turnier_id$action>
                        <button  name='content' class='button primary'>✏️<!--&#9998;--> Ergebnisse eintragen</button>     
                        <input type='hidden' name='gameEditMode' value='1'/>
                    </form>
                    ";
                }else{
                    echo"<li class='button disabled'><a href='#'>✏️ Ergebnisse eintragen</a></li>";
                }
            
            
        }
    }

    function printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status){
        //Heim- und Gegner-Namen herausfinden
        $sqlTeams = 'SELECT * FROM Turnier_Begegnung WHERE id = '. $begegnungId .';';
        $resultTeams = $conn->query($sqlTeams);
        while ( !empty( $rowTeams = $resultTeams->fetch_assoc() ) ){
            $heimteamId=$rowTeams['fk_heimteam'];
            $auswaertsteamId=$rowTeams['fk_auswaertsteam'];
        }
        //Heimteam-Namen herausfinden
        $sqlHeimteam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $heimteamId .';';
        $resultHeimteam = $conn->query($sqlHeimteam);
        while ( !empty( $rowHeimteam = $resultHeimteam->fetch_assoc() ) ){
            $heimteam=$rowHeimteam['kuerzel'];
            
        }
        //Auswärtsteam-Namen herausfinden
        $sqlAusw = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $auswaertsteamId .';';
        $resultAusw = $conn->query($sqlAusw);
        while ( !empty( $rowAusw = $resultAusw->fetch_assoc() ) ){
            $auswaertsteam=$rowAusw['kuerzel'];
            
        }
        
        $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
        $resultSpiel = $conn->query($sqlSpiel);	
        while ($rowSpiel = $resultSpiel->fetch_assoc()) {
            $a=$rowSpiel["biereheimteam"];
            $b=$rowSpiel["biereauswaertsteam"];
            // ########TODO:: Punktestand in richtiger Reihenfolge???
            //echo " $a:$b ";
            //$gameID=$rowSpiel["id"];
            //echo " <a class='height: 1px;' name='gameId' href='#changegame' value='$gameID' class='button primary'>$a:$b</a> "; <!--value=$gameID-->
            $spielId = $rowSpiel['id'];
            if($gameEditMode == 1 && $status != '5'){ //editMode & noch nicht final
                ?>
                <form method='post' action='#changegame' style='margin: 0 0 0 0;'>
                    <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'><?php echo $a?>:<?php echo $b?></button>
                    <input type='hidden' name='action' value='editOrDelete'/>
                    <input type='hidden' name='spielId' value='<?php echo $spielId ?>'/>
                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                    <input type='hidden' name='biereheimteam' value='<?php echo $a ?>'/>
                    <input type='hidden' name='biereauswaertsteam' value='<?php echo $b ?>'/>
                    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                    <input type='hidden' name='heimteam' value='<?php echo $heimteam ?>'/>
                    <input type='hidden' name='auswaertsteam' value='<?php echo $auswaertsteam ?>'/>
                </form>
                <?php
            }else{
                echo "$a:$b "; //FALL: SCHON FINAL 
            }
        }

        if($gameEditMode == 1 && $status == '5'){ //SCHON FINAL
            //BEGEGNUNG UNFINALISIEREN
            ?>
            <form method='post' action='#changegame' style='margin: 0 0 0 0;'>
                <button style='text-align="center";background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&#9733;</button>
                <input type='hidden' name='action' value='unfinal'/>
                <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
            </form>
            <?php
        }else{ //NOCH NICHT FINAL
            //Fall, dass es noch keine Spiele gibt
            $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
            $resultSpiel = $conn->query($sqlSpiel);	
            //if (empty($rowSpiel = $resultSpiel->fetch_assoc())) {
                if($gameEditMode == 1){
                    ?>
                    <form method='post' action='#changegame' style='margin: 0 0 0 0;'>
                        <button style='text-align="center";background-color:#7700FF; padding: 0 0.3rem; height: 2rem; width: 2rem; line-height: 1.3rem; font-size: 1.3rem; text-align: center;'>
                            +
                        </button>
                        <input type='hidden' name='action' value='add'/>
                        <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                        <input type='hidden' name='heimteam' value='<?php echo $heimteam ?>'/>
                        <input type='hidden' name='auswaertsteam' value='<?php echo $auswaertsteam ?>'/>
                    </form>
                    <?php
                }else{
                    //do nothing
                }
            //}	
            if($gameEditMode == 1){
                //BEGEGNUNG FINAL MACHEN
                ?>
                <form method='post' action='#changegame' style='margin: 0 0 0 0;'>
                    <button style='text-align="center";background-color:green; padding: 0 0.3rem; height: 1.1rem; line-height: 1rem; font-size: 1rem;'>
                        &#10003; 
                    </button>
                    <input type='hidden' name='action' value='final'/>
                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                </form>
                <?php
            }else{ //do nothing 
            }
        }
    }

    function printSpielplanGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
        try {
            //Button, mit dem man den Bearbeitungsmodus starten kann
            printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#gruppenphase", $test_turnier_id);
            
            $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . " ORDER BY id";
            $resultGruppe = $conn->query($sqlGruppe);
            while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                $gruppenname=$rowGruppe['name'];
                //SCHALTER -> soll in Gruppenphasentabelle nur obere Hälfte gefüllt werden -> 1 -> dann soll nämlich erste Zeile und erste Spalte wegfallen
                $sqlSchalter = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID . '';
                $resultSchalter = $conn->query($sqlSchalter);
                while ($rowSchalter = $resultSchalter->fetch_assoc()) {
                    $schalterDreieck = $rowSchalter['nurOberesDreieckInGruppenphase'];
                    $loescheErsteZeileUndSpalte = $rowSchalter['loescheErsteZeileUndSpalte'];
                }
                //MÖGLICHKEIT GANZE GRUPPE ZU FINALISIEREN
                if($gameEditMode == 1){
                    //GANZE GRUPPE FINALISIEREN
                    ?>
                    <form method='post' action='#changegame'>
                        <button style='background-color:blue;height: 0rem;line-heigth: 0rem' class='height: 1px;' name='action' value='' class='button primary'><h2>Gruppe <?php echo $gruppenname ?> &#9733;</h2></button>
                        <input type='hidden' name='action' value='final_group'/>
                        <input type='hidden' name='groupId' value='<?php echo $rowGruppe["id"] ?>'/>
                        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                    </form>
                    <?php
                }else{
                    echo "<h2>Gruppe $gruppenname &#9733;</h2>";
                }
                echo "
                <table class='withBorderCollapse'>
                    <thead>
                        <tr>
                            <th />";
                            // Erste Zeile füllen
                            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY ID';
                            $resultTeam = $conn->query($sqlTeam);
                            if($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1){
                                $rowTeam = $resultTeam->fetch_assoc(); //Falls Schalter = 1 soll erste Spalte gekickt werden
                            }
                            while ($rowTeam = $resultTeam->fetch_assoc()) {
                                $teamId=$rowTeam["id"];	
                                //$kuerzel=$rowTeam["kuerzel"];				
                                //echo "<th class='text-center'>$kuerzel</th>";	
                                echo "<th style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap;'>";
                                $return = printKuerzelWithLink($conn, $teamId);
                                echo "$return";
                                echo "</th>";				
                            }
                            
                echo "  </tr>
                    </thead>
                    <tbody>
                        <tr>";
                            $resultTeamZeile = $conn->query($sqlTeam);
                            if($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1){ //FALLS NUR DREIECK ANGEZEIGT WERDEN SOLL WIRD LETZTE ZEILE ENTFERNT
                                $count = 0; //zählen wie viele Zeilen es gäbe damit am ende die Anzahl-1 angezeigt werden kann
                                while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                                    $count++;
                                }
                                $count--;
                                if ($count > 0) {
                                    $resultTeamZeile = $conn->query($sqlTeam . ' LIMIT ' . $count);
                                } else {
                                    $resultTeamZeile = $conn->query($sqlTeam . ' LIMIT 0');
                                }
                            }
                            while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                                //$kuerzel=$rowTeamZeile["kuerzel"];	
                                $teamId=$rowTeamZeile["id"];					
                                echo "<td style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap; vertical-align: middle;'>";
                                $return = printKuerzelWithLink($conn, $teamId);
                                echo "$return";
                                echo "</td>";
                                // Ab hier Spiel-Ergebnisse
                                $resultTeamSpalte = $conn->query($sqlTeam);
                                if($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1){
                                    $rowTeam = $resultTeamSpalte->fetch_assoc(); //Falls Schalter = 1 soll erste Spalte gekickt werden
                                }
                                while ($rowTeamSpalte = $resultTeamSpalte->fetch_assoc()) {
                                    // Erst alle Begegnungen filtern und dann dazu die passenden Spiele suchen
                                    $leereZeile = 1;
                                    //CHECKEN OB ES KEINE BEGEGNUNG GIBT - WENN JA DANN "-" ausgeben
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` NOT IN (3,6) AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    if ( empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'>";  // Tabellen-Feld eröffnen
                                        echo " - ";
                                    }
                                    //SONST BEGEGNUNGEN AUSGEBEN
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` NOT IN (3,6) AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td style='text-align:center; padding: 0.05em 0.2em !important; white-space: nowrap;'>";   // Tabellen-Feld eröffnen
                                        $begegnungId = $rowBegegnung['id'];
                                        $status = $rowBegegnung['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                                        printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status);
                                    }
                                    echo "</td>"; // Tabellen-Feld schließen
                                }
                                echo "</tr>"; // nächste Zeile
                            }
                    echo "</tr>
                    </tbody>
                </table>";
            }
        }catch (Throwable  $e) { 
            print "<i style='color: red'>Detail: " . $e->getMessage() . "</i>";
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***Fehler bei printSpielplan()*** ###</i>";
        }
    }

    // Losing Bracket: like group phase, but only group named 'Losing Bracket'
    function printSpielplanLosingBracket($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
        try {
            printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#losingbracket", $test_turnier_id);

            // Schalter aus Turnier_Main wie in der Gruppenphase
            $schalterDreieck = 0; $loescheErsteZeileUndSpalte = 0;
            $sqlSchalter = 'SELECT nurOberesDreieckInGruppenphase, loescheErsteZeileUndSpalte FROM Turnier_Main WHERE id = ' . $TurnierID;
            $resultSchalter = $conn->query($sqlSchalter);
            if ($resultSchalter && ($rowSchalter = $resultSchalter->fetch_assoc())) {
                $schalterDreieck = (int)$rowSchalter['nurOberesDreieckInGruppenphase'];
                $loescheErsteZeileUndSpalte = (int)$rowSchalter['loescheErsteZeileUndSpalte'];
            }

            // Teilnehmer-Teams für LB dynamisch aus Begegnungen (ko_finallevel=20), nur dieses Turnier
            $teams = [];
            $sqlTeamsLB = 'SELECT DISTINCT t.id FROM Turnier_Team t WHERE t.geloescht = 0 AND t.fk_turnier = ' . $TurnierID . ' AND (t.id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 20) OR t.id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 20)) ORDER BY t.id';
            $resTeamsLB = $conn->query($sqlTeamsLB);
            while ($resTeamsLB && ($rt = $resTeamsLB->fetch_assoc())) { $teams[] = (int)$rt['id']; }

            echo "<h2>Gruppe Losing Bracket &#9733;</h2>";
            // Wenn noch keine Teams im LB sind, Hinweis anzeigen und abbrechen
            if (count($teams) === 0) {
                echo "<div class='note'>Noch keine Spiele im Losing‑Bracket vorhanden. Die Begegnungen werden automatisch erzeugt, sobald die ersten Teams ausgeschieden sind.</div>";
                return;
            }
            echo "<table class='withBorderCollapse'><thead><tr><th />";
            $headerStart = ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1) ? 1 : 0;
            for ($i = $headerStart; $i < count($teams); $i++) {
                $tid = $teams[$i];
                echo "<th style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap;'>";
                $return = printKuerzelWithLink($conn, $tid);
                echo $return;
                echo "</th>";
            }
            echo "</tr></thead><tbody>";

            $rowLimit = count($teams);
            if ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1 && $rowLimit > 0) { $rowLimit = $rowLimit - 1; }

            for ($ri = 0; $ri < $rowLimit; $ri++) {
                $rowTid = $teams[$ri];
                echo "<tr>";
                echo "<td style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap; vertical-align: middle;'>";
                $return = printKuerzelWithLink($conn, $rowTid);
                echo $return;
                echo "</td>";

                $colStart = ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1) ? 1 : 0;
                for ($ci = $colStart; $ci < count($teams); $ci++) {
                    $colTid = $teams[$ci];
                    if ($rowTid === $colTid || ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1 && $ci <= $ri)) {
                        echo "<td style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'> - </td>";
                        continue;
                    }
                    $sqlBeg = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` NOT IN (3,6) AND ((fk_heimteam = ' . $rowTid . ' AND fk_auswaertsteam = ' . $colTid . ') OR (fk_heimteam = ' . $colTid . ' AND fk_auswaertsteam = ' . $rowTid . ')) AND ko_finallevel = 20 ORDER BY ID';
                    $resBeg = $conn->query($sqlBeg);
                    if ($resBeg && empty($resBeg->fetch_assoc())) {
                        echo "<td style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'> - </td>";
                    } else {
                        // erneut iterieren für Ausgabe
                        $resBeg = $conn->query($sqlBeg);
                        echo "<td style='text-align:center; padding: 0.05em 0.2em !important; white-space: nowrap;'>";
                        while ($resBeg && ($rb = $resBeg->fetch_assoc())) {
                            $begegnungId = (int)$rb['id'];
                            $status = $rb['status'];
                            printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status);
                        }
                        echo "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen. Fehlermeldung: ***Fehler bei printSpielplan(): $msg*** ###</i>";
        }
    }

    function printPunktetabelleLosingBracket($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
        // Flag laden, ob KO-Loser im LB erlaubt sind
        $sqlFlagLB = 'SELECT losingbracket_open_for_ko_losers FROM Turnier_Main WHERE id = ' . (int)$TurnierID . ' LIMIT 1';
        $resFlagLB = $conn->query($sqlFlagLB);
        $lbOpenForKoLosers = 0;
        if ($resFlagLB && ($rf = $resFlagLB->fetch_assoc())) {
            $lbOpenForKoLosers = isset($rf['losingbracket_open_for_ko_losers']) ? (int)$rf['losingbracket_open_for_ko_losers'] : 0;
        }

        echo "<h2>Punktetabelle</h2>";
        echo "<br/><br/>";
        echo "<table class='withBorderCollapse'><thead><tr><th>Team</th><th>Abk.</th><th>Sp.</th><th>Fl.</th><th>Pkt.</th><th>Sieg%</th></tr></thead><tbody>";

        // Phase-Labels wie Rangliste
        $phaseLabels = array(0 => 'Gruppenphase (ausgeschieden)');
        $sqlLevels = 'SELECT id, name FROM Turnier_KO_Finallevel';
        $resLevels = $conn->query($sqlLevels);
        while ($resLevels && ($rl = $resLevels->fetch_assoc())) {
            $phaseLabels[(int)$rl['id']] = $rl['name'];
        }

        // Teilnehmer im Losing Bracket (ko_finallevel = 20)
        $teams = array();
        $sqlTeamsLB = 'SELECT DISTINCT t.id, t.name, t.kuerzel, t.gruppenphase_manuelle_platzierung, t.siegesquote, t.platziert_level '
                    . 'FROM Turnier_Team t '
                    . 'WHERE t.geloescht = 0 AND t.fk_turnier = ' . (int)$TurnierID . ' '
                    . 'AND (t.id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 20) '
                    . 'OR t.id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 20)) '
                    . 'ORDER BY t.id';
        $resTeamsLB = $conn->query($sqlTeamsLB);
        while ($resTeamsLB && ($rt = $resTeamsLB->fetch_assoc())) {
            $teams[] = array(
                'id' => (int)$rt['id'],
                'name' => $rt['name'],
                'kuerzel' => $rt['kuerzel'],
                'siegesquote' => $rt['siegesquote'],
                'man_pos' => isset($rt['gruppenphase_manuelle_platzierung']) ? (int)$rt['gruppenphase_manuelle_platzierung'] : 0,
                'platziert_level' => isset($rt['platziert_level']) ? (int)$rt['platziert_level'] : 0,
            );
        }

        if (count($teams) === 0) {
            echo "<tr><td colspan='5' style='text-align:center; opacity:.8;'>Noch keine Teams im Losing‑Bracket erfasst.</td></tr>";
            echo "</tbody></table>";
            return;
        }

        // Live-Berechnung (nur LB-Spiele: ko_finallevel = 20)
        $stats = array();
        foreach ($teams as $t) {
            $tid = (int)$t['id'];
            $spiele = 0; $flaschen = 0; $punkte = 0;

            // Heimspiele
            $sqlH = 'SELECT id FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND fk_heimteam = ' . $tid . ' ORDER BY id';
            $resH = $conn->query($sqlH);
            while ($resH && ($rb = $resH->fetch_assoc())) {
                $bid = (int)$rb['id'];
                $sqlS = 'SELECT biereheimteam, biereauswaertsteam FROM Turnier_Spiel WHERE fk_begegnung = ' . $bid . ' ORDER BY id';
                $resS = $conn->query($sqlS);
                while ($resS && ($rs = $resS->fetch_assoc())) {
                    $spiele++;
                    $a = (int)$rs['biereheimteam'];
                    $b = (int)$rs['biereauswaertsteam'];
                    $flaschen += $a;
                    if ($a > $b) { $punkte++; }
                }
            }

            // Auswärtsspiele
            $sqlA = 'SELECT id FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND fk_auswaertsteam = ' . $tid . ' ORDER BY id';
            $resA = $conn->query($sqlA);
            while ($resA && ($rb = $resA->fetch_assoc())) {
                $bid = (int)$rb['id'];
                $sqlS = 'SELECT biereheimteam, biereauswaertsteam FROM Turnier_Spiel WHERE fk_begegnung = ' . $bid . ' ORDER BY id';
                $resS = $conn->query($sqlS);
                while ($resS && ($rs = $resS->fetch_assoc())) {
                    $spiele++;
                    $a = (int)$rs['biereheimteam'];
                    $b = (int)$rs['biereauswaertsteam'];
                    $flaschen += $b;
                    if ($b > $a) { $punkte++; }
                }
            }

            $stats[] = array(
                'id' => $tid,
                'name' => $t['name'],
                'man_pos' => $t['man_pos'],
                'spiele' => $spiele,
                'flaschen' => $flaschen,
                'punkte' => $punkte,
                'siegesquote' => $t['siegesquote'],
                'platziert_level' => isset($t['platziert_level']) ? (int)$t['platziert_level'] : 0,
            );
        }

        // Sortierung: erst Abschnittsreihenfolge (Halbfinale, Viertel, ... , Gruppenphase), dann Punkte/Flaschen/Spiele/id
        usort($stats, function($a, $b){
            $wa = ($a['platziert_level'] === 0) ? 1000 : $a['platziert_level'];
            $wb = ($b['platziert_level'] === 0) ? 1000 : $b['platziert_level'];
            if ($wa !== $wb) return ($wa < $wb) ? -1 : 1;
            if ($a['punkte'] !== $b['punkte']) return ($a['punkte'] > $b['punkte']) ? -1 : 1;
            if ($a['flaschen'] !== $b['flaschen']) return ($a['flaschen'] > $b['flaschen']) ? -1 : 1;
            if ($a['spiele'] !== $b['spiele']) return ($a['spiele'] > $b['spiele']) ? -1 : 1;
            return ($a['id'] < $b['id']) ? -1 : 1;
        });

        // Ausgabe mit Trennlinien zwischen Gruppenphase-Aussteiger (platziert_level=0) und KO-Leveln, falls Flag aktiv
        $currentSection = '__none__';
        foreach ($stats as $row) {
            $teamId = (int)$row['id'];
            $name = htmlspecialchars($row['name']);
            $spiele = (int)$row['spiele'];
            $flaschen = (int)$row['flaschen'];
            $punkte = (int)$row['punkte'];
            $plLevel = isset($row['platziert_level']) ? (int)$row['platziert_level'] : 0;

            // Nur wenn KO-Loser erlaubt, nach platziert_level trennen
            if ($lbOpenForKoLosers === 1) {
                $sectionKey = $plLevel;
                if ($sectionKey !== $currentSection) {
                    // Mini-Header pro Abschnitt
                    if (isset($phaseLabels[$plLevel])) {
                        $label = $phaseLabels[$plLevel];
                    } else {
                        $label = ($plLevel === 0) ? 'Gruppenphase (ausgeschieden)' : 'KO-Level ' . $plLevel;
                    }
                    echo "<tr><td colspan='6' style='padding:0.15rem 0.3rem; color:#b00; font-size:0.75rem; letter-spacing:0.08em; text-transform:uppercase; font-weight:bold; border-top:1px solid #d22;'>";
                    echo htmlspecialchars($label);
                    echo "</td></tr>";
                    $currentSection = $sectionKey;
                }
            }

            echo "<tr>";
            echo "<td style=\"text-align:left; padding: 0.1em 0.75em !important;\">$name</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">"; $return = printKuerzelWithLink($conn, $teamId); echo $return; echo "</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$spiele</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$flaschen</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$punkte</td>";
            $siegq = isset($row['siegesquote']) ? $row['siegesquote'] : NULL;
            if ($siegq === NULL || $siegq === '') {
                $siegqOut = '<i>-</i>';
            } else {
                $val = (float)$siegq;
                if ($val > 0 && $val <= 1) { $val = $val * 100.0; }
                $siegqOut = round($val) . ' %';
            }
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$siegqOut</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
        echo"<a href='#rangliste' class='button primary'>🏆 Zur Rangliste</a>";
        echo"<br/><br/>";
        echo "<div class='note'>Im Losing Bracket zählen alle Spiele des Turniers. Die Gesamt-Rangliste bleibt phasenweise fix: Gruppen-Aussteiger bleiben unter den KO-Leveln. Innerhalb eines Levels können die Losing-Bracket-Spiele die Reihenfolge verschieben. Sortierung: Punkte &gt; Flaschen &gt; Spiele (aufsteigend).</div>";
        
    }

    function printPunktetabelleGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
            $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
        $resultGruppe = $conn->query($sqlGruppe);
        while ($rowGruppe = $resultGruppe->fetch_assoc()) {
            $gruppenname=$rowGruppe['name'];
            echo "<h2>Gruppe $gruppenname</h2>"; ?>
            <table class='withBorderCollapse'>
                <thead>
                    <tr>
                    <!-- TODO: align='right' fixen -->
                        <th text-align='right'>Team</th>
                        <th text-align='right'>Abk.</th>
                        <th text-align='right'>Sp.</th>
                        <th text-align='right'>Fl.</th>
                        <th text-align='right'>Pkt.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                    <?php $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
                    $resultTeamZeile = $conn->query($sqlTeam);
                    while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                        $name=$rowTeamZeile["name"];
                        //$kuerzel=$rowTeamZeile["kuerzel"];
                        $teamId=$rowTeamZeile["id"];
                        $gruppenphase_spiele=$rowTeamZeile["gruppenphase_spiele"];
                        $gruppenphase_flaschen=$rowTeamZeile["gruppenphase_flaschen"];
                        $gruppenphase_punkte=$rowTeamZeile["gruppenphase_punkte"]; ?>
                        <!-- AUSGEBEN -->				  
                        <td style="text-align='left';padding: 0.1em 0.75em !important;"><?php echo $name ?></td>
                        <td style="text-align:right;padding: 0.1em 0.75em !important;"><?php $return = printKuerzelWithLink($conn, $teamId); echo "$return"; ?></td> <!-- echo $kuerzel -->
                        <td style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_spiele ?></td> <!-- Anzahl der Spiele ausgeben -->
                        <td style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_flaschen ?></td> <!-- Anzahl der Flaschen ausgeben -->
                        <td style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_punkte ?></td> <!-- Anzahl der Punkte ausgeben -->
                        </tr> <!-- nächste Zeile -->
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        <?php
        } 
        echo"<div class='note'>Sortiert wird zuerst nach Punkten, Bei Gleichstand nach Flaschen, dann nach Spielen in verkehrter Reihenfolge. Falls dann immernoch Gleichstand sein sollte, zählt der direkte Vergleich. Sollte euch das auffallen, sagt am besten der Orga Bescheid, weil der direkte Vergleich manuell eingetragen werden muss</div>";
        echo"<br/><br/>";
        echo"<a href='#rangliste' class='button primary'>🏆 Zur Rangliste</a>";
        echo"<br/><br/>";
    }

    function printKO_PhaseTabellen($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
        //Button, mit dem man den Bearbeitungsmodus starten kann
        printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#kophase", $test_turnier_id);
        
        //$start_ko_finallevel herausfinden
        $sql = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID;
        $result_sql = $conn->query($sql);
        while ($row_sql = $result_sql->fetch_assoc()) {
            $start_ko_finallevel = $row_sql["start_ko_finallevel"];
            //echo '<script>console.log('.$start_ko_finallevel.')</script>';
        }
        $ko_finallevel = $start_ko_finallevel; //Zähler
        while ($ko_finallevel > 0) {
            //Überschrift aus Datenbank suchen
            $sqlFinallevel = 'SELECT * FROM `Turnier_KO_Finallevel` WHERE id = ' . $ko_finallevel . ' ORDER BY ID';
            $resultFinallevel = $conn->query($sqlFinallevel);
            while ($rowFinallevel = $resultFinallevel->fetch_assoc()) {
                $name = $rowFinallevel["name"];
                echo "<h3>$name</h3>";
            }
            echo "
            <table class='withBorderCollapse'>
                <thead>
                    <tr>
                        <th></th>
                        <th>Team A</th>
                        <th>Spiele</th>
                        <th>Team B</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>";
                        // Erst alle Begegnungen des aktuellen Turniers (Heim oder Auswärtsspiel) filtern und dann dazu die passenden Spiele suchen
                        $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` NOT IN (3,6) AND ko_finallevel = ' . $ko_finallevel . ' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') ORDER BY ko_turnierbaumposition';
                        $resultBegegnung = $conn->query($sqlBegegnung);
                        while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Gegegnung gibt
                            //IDs der Teams speichern
                            $ko_turnierbaumposition=$rowBegegnung["ko_turnierbaumposition"];
                            $heimteamID=$rowBegegnung["fk_heimteam"];
                            $auswaertsteamID=$rowBegegnung["fk_auswaertsteam"];
                            $begegnungId=$rowBegegnung["id"];
                            $siegerteam=$rowBegegnung["fk_siegerteam"];
                            //Namen der Teams finden
                            //Team 1
                            $sqlTeam1 = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $heimteamID . ' ORDER BY ID';
                            $result1 = $conn->query($sqlTeam1); 
                            while ($rowTeam1 = $result1->fetch_assoc()) {
                                $heimteam = $rowTeam1["name"];
                                //$heimteamkuerzel = $rowTeam1["kuerzel"];
                                $teamId1 = $rowTeam1["id"];
                            }
                            //Team 2
                            $sqlTeam2 = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $auswaertsteamID . ' ORDER BY ID';
                            $result2 = $conn->query($sqlTeam2); 
                            while ($rowTeam2 = $result2->fetch_assoc()) {
                                $auswaertsteam = $rowTeam2["name"];
                                //$auswaertsteamkuerzel = $rowTeam2["kuerzel"];
                                $teamId2 = $rowTeam2["id"];
                            }	
                            //Ausgeben
                            if($siegerteam == $heimteamID){
                                echo "<td>$ko_turnierbaumposition. <p style='font-size: 10px'>#$begegnungId</p></td><td style='background-color:green;word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
                            }else{
                                echo "<td>$ko_turnierbaumposition. <p style='font-size: 10px'>#$begegnungId</p></td><td style='word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
                            }
                            
                            
                            //Spiele zu den Begegnungen finden
                            $status = $rowBegegnung['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                            printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status);
                            /*
                            //Spiele zu den Begegnungen finden
                            $sqlSpiel = 'SELECT * FROM `Spiel` WHERE fk_begegnung = ' . $rowBegegnung["id"] . ' ORDER BY ID';
                            $resultSpiel = $conn->query($sqlSpiel);	
                            while (!empty($rowSpiel = $resultSpiel->fetch_assoc())) {
                                $bier_h=$rowSpiel["biereheimteam"];
                                $bier_a=$rowSpiel["biereauswaertsteam"];
                                if($gameEditMode == 1){ ?>
                                <form method='post' action='#changegame'>
                                    <button style='padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;background:none;outline: none;border-top: none;' class='height: 1px;' name='action' value='' class='button primary'><?php echo $bier_h?>:<?php echo $bier_a?></button>
                                    <input type='hidden' name='id' value='<?php echo $rowSpiel['id']; ?>'/>
                                </form>
                                <?php 
                                }else{
                                    echo " $bier_h:$bier_a ";
                                }
                            }
                            //Fall, dass es noch keine Spiele gibt
                            $sqlSpiel = 'SELECT * FROM `Spiel` WHERE fk_begegnung = ' . $rowBegegnung["id"] . ' ORDER BY ID';
                            $resultSpiel = $conn->query($sqlSpiel);	
                            //if (empty($rowSpiel = $resultSpiel->fetch_assoc())) {
                                if($gameEditMode == 1){
                                    echo "
                                    <form method='post' action='#addgame'>
                                        <button style='padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;background:none;outline: none;border-top: none;' class='height: 1px;' name='action' value='' class='button primary'>+</button>
                                        <input type='hidden' name='begegnungId' value='$begegnungId'/>
                                        <input type='hidden' name='heimteam' value='$heimteam'/>
                                        <input type='hidden' name='auswaertsteam' value='$auswaertsteam'/>
                                    </form>
                                    ";
                                }else{
                                    //donothing
                                }
                            //}	*/
                            //Ausgeben
                            if($siegerteam == $auswaertsteamID){
                                echo "</td><td style='background-color:green;word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }else{
                                echo "</td><td style='word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }

                            //EXPERTENMODUS: Begegnungen sperren
                            if($expertenmodus==1){
                                echo "<td style='word-wrap: break-word;'>
                                <form method='post' action='#begegnung_verwalten' style='margin: 0 0 0 0;'>
                                    <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>Sperren</button>
                                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                                </form>
                                </td>
                                ";
                            }
                            
                            echo "</tr><tr>";
                        }
            echo"   </tr>
                </tbody>
            </table>";
            $ko_finallevel--; //Zähler dekrementieren (nächste Finalstufe)
        }
        echo"<br/><br/>";
        echo"<a href='#rangliste' class='button primary'>🏆 Zur Rangliste</a>";
    }

    function printKuerzelWithLink($conn, $teamId){
        //KÜRZEL HERAUSFINDEN
        $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $teamId . ' ORDER BY id';
        $result = $conn->query($sql);
        $teamKuerzel = " ";
        while (!empty($row = $result->fetch_assoc())) {
            $teamKuerzel = $row['kuerzel'];
        }
        return "<a href='?teamId=$teamId#teaminfo'>$teamKuerzel</a>";
    }

    function printSpielerWithLink($conn, $spielerId){
        //KÜRZEL HERAUSFINDEN
        $sql = 'SELECT * FROM Turnier_Spieler_in WHERE id = ' . $spielerId . ' ORDER BY id';
        $result = $conn->query($sql);
        $name = " ";
        while (!empty($row = $result->fetch_assoc())) {
            $name = $row['name'];
        }
        return "<a href='?spielerId=$spielerId#spielerinfo_login'>$name</a>";
    }

    function history_auswahl($history, $TurnierName){
        //TEST-MODUS
        //if($test_turnier_id == 0){ //FALL: NORMALES TURNIER
        echo"
        <form method='post' action='#history_info'>
            <select name='history_turnier_id'>
                <option value='0'><i>$TurnierName</i></option>";
                
                foreach ($history as &$value){
                    $index = $value[0];
                    $tName = $value[2];
                    echo "<option value=$index>$tName</option>";
                }
                echo"
            </select>
            <button  name='content' class='button primary'>Zum Turnier</button> 
            <input type='hidden' name='bn' value=''/>
            <input type='hidden' name='pw' value=''/>
        </form>";
        //}
    }
?>

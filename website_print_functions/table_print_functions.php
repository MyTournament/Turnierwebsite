<?php
    function helloHermann($TurnierID, $conn, $LoggedIn, $gameEditMode){
        $test = "baum";
        echo "Hallo Hermann $test";
        echo "$TurnierID";
    }

    function printTeamInfo($TurnierID, $conn, $teamId){ //NICHT IM CMS
        //Teamnamen herausfinden
        if($teamId != NULL){
            $sql = 'SELECT * FROM Turnier_Team WHERE id = ' . $teamId . ' ORDER BY id';
            $result = $conn->query($sql);
            $teamName = " ";
            while (!empty($row = $result->fetch_assoc())) {
                $teamName = $row['name'];
                $teamKuerzel = $row['kuerzel'];
                $gruppeId = $row['fk_gruppe'];
                $endplatzierung = $row['endplatzierung'];
            }
            echo "<h1>$teamName ($teamKuerzel)</h1>";
            //TEAMMITGLIEDER RAUSFINDEN
            $sql = 'SELECT * FROM Turnier_Spieler_in WHERE fk_team = ' . $teamId . ' ORDER BY id';
            $result = $conn->query($sql);
            $spielerName = " ";
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
                while (!empty($row = $result->fetch_assoc())) {
                    $gruppenName = $row['name'];
                }
                echo "<p><b>$gruppenName</b></p>";
            }else{
                echo "<p><i>Noch keiner Gruppe zugeteilt</i></p>";
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
            $siege = 0; //für SIEGESQUOTE
            $niederlagen = 0;
            $sql = 'SELECT * FROM Turnier_Begegnung WHERE `status` <> 3 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY id';
            $result = $conn->query($sql);
            while (!empty($row = $result->fetch_assoc())) {
                $begegnungId = $row['id'];
                $heimteamID=$row["fk_heimteam"];
                $auswaertsteamID=$row["fk_auswaertsteam"];
                $ko_finallevel=$row["ko_finallevel"];
                //Namen der Teams finden
                //Team 1
                $sqlTeam1 = 'SELECT * FROM `Turnier_Team` WHERE id = ' . $heimteamID . ' ORDER BY ID';
                $result1 = $conn->query($sqlTeam1); 
                while ($rowTeam1 = $result1->fetch_assoc()) {
                    $heimteam = $rowTeam1["name"];
                    //$heimteamkuerzel = $rowTeam1["kuerzel"];
                    $teamId1 = $rowTeam1["id"];
                }
                //Team 2
                $sqlTeam2 = 'SELECT * FROM `Turnier_Team` WHERE id = ' . $auswaertsteamID . ' ORDER BY ID';
                $result2 = $conn->query($sqlTeam2); 
                while ($rowTeam2 = $result2->fetch_assoc()) {
                    $auswaertsteam = $rowTeam2["name"];
                    //$auswaertsteamkuerzel = $rowTeam2["kuerzel"];
                    $teamId2 = $rowTeam2["id"];
                }	

                //FINALLLEVEL
                $sqlFinallevel = 'SELECT * FROM `Turnier_KO_Finallevel` WHERE id = ' . $ko_finallevel . ' ORDER BY ID';
                $resultFinallevel = $conn->query($sqlFinallevel); 
                while ($rowFinallevel = $resultFinallevel->fetch_assoc()) {
                    $finallevel_name = $rowFinallevel["name"];
                }
                echo "<td>$finallevel_name</td>"; //Heimteam kommt ganz links hin

                //Ausgeben
                echo "<td>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
                
                //Spiele zu den Begegnungen finden
                $status = $row['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status);
                
                echo "</td><td>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td></tr><tr>"; //Auswärtsteam kommt ganz rechts hin		
                $zaehler++;

                //SIEGESQUOTE AUSRECHNEN
                    
                    $sqlSiegesquote = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
                    $resultSiegesquote = $conn->query($sqlSiegesquote); 
                    while ($rowSiegesquote = $resultSiegesquote->fetch_assoc()) {
                        $biereheimteam = $rowSiegesquote['biereheimteam'];
                        $biereauswaertsteam = $rowSiegesquote['biereauswaertsteam'];

                        if($teamId == $heimteamID){
                            if($biereheimteam > $biereauswaertsteam){
                                $siege++;
                            }else if($biereheimteam < $biereauswaertsteam){
                                $niederlagen++;
                            }
                        }else if($teamId == $auswaertsteamID){
                            if($biereheimteam > $biereauswaertsteam){
                                $niederlagen++;
                            }else if($biereheimteam < $biereauswaertsteam){
                                $siege++;
                            }
                        }
                    }
            }
            $siegesquote = ($siege/($siege+$niederlagen))*100;

            echo"   </tr>
                </tbody>
            </table>";
            echo "<br/>";

            echo "<h2>Siegesquote</h2>";
            echo "<p><b>$siegesquote %</b></p>";
            echo "<br/>";

            echo "<h2>Endplatzierung</h2>";
            if($endplatzierung!=NULL && $endplatzierung!=0){
                echo "<p><b>$endplatzierung</b></p>";
            }else{
                echo "<p><i>noch nicht bestimmt</i></p>";
            }

            echo "<br/>";
            echo "<a href='/website_functionalities/generate_team_certificate/generate_team_certificate.php?teamId=$teamId' class='button primary'>Teamzertifikat zum Drucken</a>";
            echo "<br/>";

            echo "<br/>";
        }
        
    }

    function printSpielerInfo($TurnierID, $conn, $spielerId){ //NICHT IM CMS
        //LOGIN
        $bn = $_POST['bn'];
        $pw = $_POST['pw'];
        $successfulLogin = 0; //false
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
        //FALL: Account-Login -> Bearbeitungsrechte für alle Begegnungen
        $sqlLoginAccount = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' AND fk_rechte <= 15 ORDER BY ID"; //
        $resultLoginAccount = $conn->query($sqlLoginAccount);
        while ( !empty( $rowLoginAccount = $resultLoginAccount->fetch_assoc() ) ){
            $successfulLogin = 1;
            $teamBearbeitungsrecht = 1;
            //echo "<script>console.log('Du bist eingeloggt mit deinem Account und hast damit volle Bearbeitungsrechte.')</script>";
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
            $sql = 'SELECT * FROM Turnier_Team WHERE id = ' . $fk_team . ' ORDER BY id';
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

    function printTeams($TurnierID, $conn, $LoggedIn, $gameEditMode){
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY ID'; //WHERE Freischaltung = 1
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
    function printSchiedsrichterInnen($TurnierID, $conn, $LoggedIn, $gameEditMode){
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
    function printGroupsAsTable($TurnierID, $conn, $LoggedIn, $gameEditMode){
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
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND fk_gruppe = '.$groupId.' ORDER BY ID'; //WHERE Freischaltung = 1
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
    function printTurnierbaum($TurnierID, $conn, $LoggedIn, $gameEditMode){
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
            $treeArray = [$eins][$zwei]; //Array erstellen
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
                    $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = ' . $ko_finallevel . ' AND ko_turnierbaumposition = '. $zaehlerForKoPosition .' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition ASC, id ASC'; //AND NOT fk_siegerteam = NULL 
                    $resultBegegnung = $conn->query($sqlBegegnung);
                    $siegerGefunden = false;
                    $zumindestBegegnungGefunden = false;
                    while (!empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                        if($rowBegegnung['status'] == 5){
                            //ID ablesen
                            $teamId = $rowBegegnung['fk_siegerteam'];
                            //Namen zur ID finden
                            if($teamId != NULL){
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE id = '. $teamId .'';
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
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE id = '. $teamId1 .'';
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
                                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE id = '. $teamId2 .'';
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

    function trigger_sieger_innen_treppe($TurnierID, $conn, $LoggedIn, $gameEditMode){
        $platzierungen = []; //Array erstellen
        $zeahler = 0;
        while($zeahler<3){
            $actPlatzierung = $zeahler+1;
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND endplatzierung > 0 AND endplatzierung = '.$actPlatzierung.' ORDER BY endplatzierung ASC'; //AND NOT endplatzierung = NULL
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

    function print_platzierungen($TurnierID, $conn, $LoggedIn, $gameEditMode){
        echo "<ul class='alt'>";
        $platzierungsZaehler = 1;
        $limit = 0;
        //zählen wie viele Teams es gibt
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY ID';
        $resultTeamZeile = $conn->query($sqlTeam);
        while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
            $limit++;
        }
        while($platzierungsZaehler <= $limit){
            $teamName = "<i>noch nicht bestimmt</i>";
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND endplatzierung = '. $platzierungsZaehler .' ORDER BY endplatzierung DESC'; //AND NOT endplatzierung = NULL
            $resultTeam = $conn->query($sqlTeam);
            while (!empty($rowTeam = $resultTeam->fetch_assoc())) {
                //$endplatzierung = $resultTeam['endplatzierung'];
                $teamName = $rowTeam['name'];
                $teamId = $rowTeam['id'];
                $teamKuerzel = printKuerzelWithLink($conn, $teamId);
                $teamName .= " ($teamKuerzel)";
            }
            echo "<li>$platzierungsZaehler. $teamName</li>";
            $platzierungsZaehler++;
        }
        echo "</ul>";
        
    }

    function printEditModeStuff($conn, $TurnierID, $gameEditMode, $action, $test_turnier_id){
        if($gameEditMode == 1){
            echo "<h2 style='color:#00FF00'>Bearbeitungsmodus</h2>";
            echo "<ul class='alt'>";
            echo "<li style='color:#00FF00'><button style='background-color:#7700FF;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' class='button primary'>+</button> Über die Plus-Buttons kannst du neue Spielstände hinzufügen.</li>";
            //echo "<li style='color:#00FF00'><button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>3:0</button> Ein Spielstand ist nicht korrekt? Dann tippe einfach auf ihn, gib das Passwort deines Teams ein und ändere oder lösche den Spielstand.</li>";
            echo "<li style='color:#00FF00'><button style='background-color:green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&check;</button> Sobald ihr alle Spiele gegen ein bestimmtes Team eingetragen habt, müsst ihr noch einmal das grüne Häkchen anklicken, damit die Website weiß, dass sie auf keine Spiele mehr warten muss und schon die Teams schon für die kommenden Spiele berechnen kann.</li>";
            echo "<li style='color:#00FF00'><button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&#9733;</button> Dieser Button zeigt an, dass ein Spiel als final markiert wurde. Solltet ihr nachträglich doch noch ein Spiel eintragen wollen, könnt ihr euch an einen Administrator wenden.</li>";
            echo "<li style='color:#00FFFF'><img src='images/icon/telegram.png' width='20' height='20' border='5' alt='Home'> Das ist dir alles viel zu kompliziert? Dann ist der <a href='https://telegram.me/REDACTEDbot'>offizielle Blankiball-Bot</a> was für dich!</li>";
            //grey: #888888
            echo "</ul>";
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
                if($turnier_phase_ID == 7 || $turnier_phase_ID == 11){
                    //Button um Bearbeitungsmodus zu aktivieren -> nur wenn Turnierphase dazu passt
                    echo"
                    <form method='post' action=?test_turnier_id=$test_turnier_id$action>
                        <button  name='content' class='button primary'>✏️<!--&#9998;--> Ergebnisse eintragen</button>     
                        <input type='hidden' name='gameEditMode' value='1'/>
                    </form>
                    ";
                }else{
                    echo"<li class='button disabled'><a href='#'>Ergebnisse eintragen</a></li>";
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
        $sqlHeimteam = 'SELECT * FROM Turnier_Team WHERE id = '. $heimteamId .';';
        $resultHeimteam = $conn->query($sqlHeimteam);
        while ( !empty( $rowHeimteam = $resultHeimteam->fetch_assoc() ) ){
            $heimteam=$rowHeimteam['kuerzel'];
            
        }
        //Auswärtsteam-Namen herausfinden
        $sqlAusw = 'SELECT * FROM Turnier_Team WHERE id = '. $auswaertsteamId .';';
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
                <button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&#9733;</button>
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
                        <button style='background-color:#7700FF;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>+</button>
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
                    <button style='background-color:green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&check;</button>
                    <input type='hidden' name='action' value='final'/>
                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                </form>
                <?php
            }else{ //do nothing 
            }
        }
    }

    function printSpielplanGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $test_turnier_id){
        try {
            //Button, mit dem man den Bearbeitungsmodus starten kann
            printEditModeStuff($conn, $TurnierID, $gameEditMode, "#gruppenphase", $test_turnier_id);
            
            $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
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
                            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY ID';
                            $resultTeam = $conn->query($sqlTeam);
                            if($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1){
                                $rowTeam = $resultTeam->fetch_assoc(); //Falls Schalter = 1 soll erste Spalte gekickt werden
                            }
                            while ($rowTeam = $resultTeam->fetch_assoc()) {
                                $teamId=$rowTeam["id"];	
                                //$kuerzel=$rowTeam["kuerzel"];				
                                //echo "<th class='text-center'>$kuerzel</th>";	
                                echo "<th class='text-center'>";
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
                                $resultTeamZeile = $conn->query($sqlTeam . ' LIMIT ' . $count);
                            }
                            while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                                //$kuerzel=$rowTeamZeile["kuerzel"];	
                                $teamId=$rowTeamZeile["id"];					
                                echo "<td style='text-align:left;padding: 0.1em 0.75em !important;'>";
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
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    if ( empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td style='text-align:left;background-color:#161819;padding: 0.1em 0.75em !important;'>"; // Tabellen-Feld eröffnen
                                        echo " - ";
                                    }
                                    //SONST BEGEGNUNGEN AUSGEBEN
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td style='text-align:left;padding: 0.1em 0.75em !important;'>"; // Tabellen-Feld eröffnen
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
        }catch (Throwable $e) {
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***Fehler bei printSpielplan()*** ###</i>";
        }
    }

    function printPunktetabelleGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $test_turnier_id){
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
                    <?php $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
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
                        <td style="text-align='rigth';padding: 0.1em 0.75em !important;"><?php $return = printKuerzelWithLink($conn, $teamId); echo "$return"; ?></td> <!-- echo $kuerzel -->
                        <td style="text-align='rigth';padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_spiele ?></td> <!-- Anzahl der Spiele ausgeben -->
                        <td style="text-align='rigth';padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_flaschen ?></td> <!-- Anzahl der Flaschen ausgeben -->
                        <td style="text-align='rigth';padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_punkte ?></td> <!-- Anzahl der Punkte ausgeben -->
                        </tr> <!-- nächste Zeile -->
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        <?php
        } 
    }

    function printKO_PhaseTabellen($TurnierID, $conn, $LoggedIn, $gameEditMode, $test_turnier_id){
        //Button, mit dem man den Bearbeitungsmodus starten kann
        printEditModeStuff($conn, $TurnierID, $gameEditMode, "#kophase", $test_turnier_id);
        
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
                        $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND ko_finallevel = ' . $ko_finallevel . ' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE fk_turnier = '. $TurnierID .') ORDER BY ko_turnierbaumposition';
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
                            $sqlTeam1 = 'SELECT * FROM `Turnier_Team` WHERE id = ' . $heimteamID . ' ORDER BY ID';
                            $result1 = $conn->query($sqlTeam1); 
                            while ($rowTeam1 = $result1->fetch_assoc()) {
                                $heimteam = $rowTeam1["name"];
                                //$heimteamkuerzel = $rowTeam1["kuerzel"];
                                $teamId1 = $rowTeam1["id"];
                            }
                            //Team 2
                            $sqlTeam2 = 'SELECT * FROM `Turnier_Team` WHERE id = ' . $auswaertsteamID . ' ORDER BY ID';
                            $result2 = $conn->query($sqlTeam2); 
                            while ($rowTeam2 = $result2->fetch_assoc()) {
                                $auswaertsteam = $rowTeam2["name"];
                                //$auswaertsteamkuerzel = $rowTeam2["kuerzel"];
                                $teamId2 = $rowTeam2["id"];
                            }	
                            //Ausgeben
                            if($siegerteam == $heimteamID){
                                echo "<td>$ko_turnierbaumposition.</td><td style='background-color:green;word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
                            }else{
                                echo "<td>$ko_turnierbaumposition.</td><td style='word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin
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
                                echo "</td><td style='background-color:green;word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td></tr><tr>"; //Auswärtsteam kommt ganz rechts hin
                            }else{
                                echo "</td><td style='word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td></tr><tr>"; //Auswärtsteam kommt ganz rechts hin
                            }
                            		
                        }
            echo"   </tr>
                </tbody>
            </table>";
            $ko_finallevel--; //Zähler dekrementieren (nächste Finalstufe)
        }
    }

    function printKuerzelWithLink($conn, $teamId){
        //KÜRZEL HERAUSFINDEN
        $sql = 'SELECT * FROM Turnier_Team WHERE id = ' . $teamId . ' ORDER BY id';
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
        <p>Wähle ein Turnier aus der folgenden Liste aus oder klicke unten auf die alte Website</p>
        <form method='post' action='#'>
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
            <input type='hidden' name='bn' value='$bn'/>
            <input type='hidden' name='pw' value='$pw'/>
        </form>";
        //}
    }
?>
<?php
    // ================================================================================================
    // KO-EINZUG-MODUS: KOMPATIBILITÄTS-PRÜFUNG (geteilt zwischen Turnier Settings und dem eigenen
    // "Einzug ins KO-System"-Menüpunkt, deshalb hier unbedingt/außerhalb jeder Rechte-Bedingung
    // definiert, statt lokal in einem der beiden Artikel).
    // ================================================================================================
    // Prüft, ob ein KO-Einzug-Modus (Zeile aus Turnier_KO_Einzug_Modus) zur aktuellen Gruppenanzahl
    // und Start-KO-Finalstufe passt. Gibt ['ok' => bool, 'grund' => string] zurück - $grund ist bei
    // ok=true die Anzahl Qualifikanten/Gruppe, bei ok=false die konkrete Unvereinbarkeits-Erklärung.
    function koEinzugModusKompatibel($modusRow, $anzahlGruppen, $startKoFinallevel) {
        $anzahlGruppen = (int)$anzahlGruppen;
        if ($anzahlGruppen < (int)$modusRow['min_anzahl_gruppen']) {
            return ['ok' => false, 'grund' => 'Braucht mindestens ' . (int)$modusRow['min_anzahl_gruppen'] . ' Gruppen (aktuell: ' . $anzahlGruppen . ').'];
        }
        if (!empty($modusRow['max_anzahl_gruppen']) && $anzahlGruppen > (int)$modusRow['max_anzahl_gruppen']) {
            return ['ok' => false, 'grund' => 'Erlaubt höchstens ' . (int)$modusRow['max_anzahl_gruppen'] . ' Gruppen (aktuell: ' . $anzahlGruppen . ').'];
        }
        if (!empty($modusRow['gruppenanzahl_muss_gerade_sein']) && $anzahlGruppen % 2 !== 0) {
            return ['ok' => false, 'grund' => 'Braucht eine gerade Anzahl Gruppen (aktuell: ' . $anzahlGruppen . ').'];
        }
        $totalStartTeams = (int)pow(2, max(1, (int)$startKoFinallevel - 1));
        if ($anzahlGruppen <= 0 || $totalStartTeams % $anzahlGruppen !== 0) {
            return ['ok' => false, 'grund' => 'Die ' . $totalStartTeams . ' Startplätze der gewählten K.-o.-Startstufe lassen sich nicht gleichmäßig auf ' . $anzahlGruppen . ' Gruppen aufteilen.'];
        }
        $platzierungenProGruppe = intdiv($totalStartTeams, $anzahlGruppen);
        if (!empty($modusRow['platzierungen_pro_gruppe']) && (int)$modusRow['platzierungen_pro_gruppe'] !== $platzierungenProGruppe) {
            return ['ok' => false, 'grund' => 'Braucht genau ' . (int)$modusRow['platzierungen_pro_gruppe'] . ' Qualifikanten pro Gruppe, aktuell qualifizieren aber ' . $platzierungenProGruppe . ' pro Gruppe (' . $totalStartTeams . ' Startplätze ÷ ' . $anzahlGruppen . ' Gruppen).'];
        }
        return ['ok' => true, 'grund' => $platzierungenProGruppe . ' Qualifikant(en) pro Gruppe.'];
    }

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
            $sql = 'SELECT * FROM Turnier_Begegnung WHERE `status` <> 3 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY id';
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
        // ====================================================================================
        // RECHTE-AUDIT: SPIELER*INNEN-INFOS NUR NOCH ÜBER DAS "backstage"-FLAG, KEIN ADMIN/CO-
        // ADMIN-SHORTCUT MEHR (Admin/Co-Admin haben das backstage-Flag in der Rollentabelle ohnehin)
        // ====================================================================================
            $TurnierID = isset($_POST['TurnierID']) ? $_POST['TurnierID'] : $TurnierID;

            include_once __DIR__ . '/../website_datachange/login_interface.php';
            $bn = $_POST['bn'];
            $pw = $_POST['pw'];
            $successfulLogin = 0; //false
            $teamBearbeitungsrecht = 0; // Veraltet?
            $rollenInfoSpielerinfo = getUserRollenInfo($conn, $bn, $pw);
            if ($rollenInfoSpielerinfo !== null && $rollenInfoSpielerinfo['flags']['backstage']) {
                $successfulLogin = 1;
                $teamBearbeitungsrecht = 1;
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
        // RECHTE-AUDIT: nur noch reines Flag rechte_alle_spiele=1, kein "OR fk_rolle IN (1,2)" mehr -
        // Admin/Co-Admin haben dieses Flag in der Rollentabelle ohnehin gesetzt und tauchen daher
        // automatisch mit auf, ohne dass hier nach Rollen-IDs gefragt werden muss.
        try {
            $sql = "SELECT DISTINCT sb.Benutzername FROM System_Benutzer_in sb
                    JOIN System_Benutzer_in_Relation_Rolle rel ON rel.fk_benutzer_in = sb.id
                    JOIN System_Benutzer_in_Rolle sbr ON sbr.id = rel.fk_rolle
                    WHERE sbr.rechte_alle_spiele = 1
                    ORDER BY sb.Benutzername ASC";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $Benutzername = $row['Benutzername'];
                echo "<li>$Benutzername</li>";
            }
        } catch (Throwable $e) {
            // Rollen-Tabellen nicht erreichbar - Liste bleibt leer
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
        $maxKoLevel = $start_ko_finallevel;
        $ko_finallevel = $start_ko_finallevel; //Zaehler
        echo "
        <section id='turnierbaum' class='bracket-section'>
            <style>
                :root {
                    --bracket-bg: #0d1626;
                    --bracket-card: linear-gradient(135deg, #0f1e34, #142740);
                    --bracket-line: #1f3a63;
                    --bracket-accent: #00c2ff;
                }
                .bracket-shell {
                    background: radial-gradient(circle at 15% 20%, rgba(0, 194, 255, 0.12), transparent 32%), radial-gradient(circle at 85% 0%, rgba(255, 117, 88, 0.08), transparent 26%), var(--bracket-bg);
                    border: 1px solid rgba(255,255,255,0.06);
                    border-radius: 16px;
                    padding: clamp(1rem, 2vw, 1.75rem);
                    margin: 0 auto 2rem;
                    max-width: min(100%, 1100px);
                    box-shadow: 0 24px 80px rgba(5, 12, 26, 0.45);
                }
                .bracket-scroll {
                    overflow-x: auto;
                    padding-bottom: 0.35rem;
                }
                .bracket-table {
                    width: min(100%, 980px);
                    margin: 0 auto;
                    table-layout: fixed;
                    border-collapse: separate;
                    border-spacing: 0.45rem 0.55rem;
                    color: #eaf1ff;
                    text-align: center;
                }
                .bracket-table thead td {
                    color: #8fb5ff;
                    letter-spacing: 0.08em;
                    font-size: 0.85rem;
                    text-transform: uppercase;
                    border: none;
                    padding: 0.25rem 0.35rem;
                    background: transparent;
                    white-space: nowrap;
                }
                .round-title {
                    writing-mode: vertical-rl;
                    transform: rotate(180deg);
                    line-height: 1.1;
                    padding: 0.45rem 0.3rem;
                }
                .bracket-table td {
                    vertical-align: middle;
                }
                .bracket-slot {
                    background: var(--bracket-card);
                    border: 1px solid rgba(255,255,255,0.06);
                    border-radius: 12px;
                    padding: 0.45rem 0.6rem;
                    font-weight: 700;
                    font-size: 1rem;
                    box-shadow: 0 10px 28px rgba(10, 18, 36, 0.35);
                    min-width: 150px;
                    white-space: nowrap;
                }
                .bracket-slot.placeholder {
                    color: #7a8ca8;
                    font-style: italic;
                }
                .bracket-slot.bracket-winner {
                    background: linear-gradient(135deg, #12436d, #0e8aa8);
                    border-color: rgba(0, 194, 255, 0.35);
                    color: #e9fbff;
                }
                .bracket-line {
                    padding: 0;
                    min-width: 18px;
                    border-left: 2px dashed var(--bracket-line);
                    opacity: 0.85;
                }
                @media (max-width: 900px) {
                    .bracket-shell { padding: 0.9rem 0.8rem; }
                    .bracket-table { border-spacing: 0.35rem 0.4rem; }
                    .bracket-slot { min-width: 130px; font-size: 0.95rem; padding: 0.4rem 0.5rem; }
                    .bracket-table thead td { font-size: 0.78rem; }
                }
                @media (max-width: 640px) {
                    .bracket-shell { margin: 0 -0.35rem 1.5rem; border-radius: 12px; }
                    .bracket-slot { min-width: 115px; font-size: 0.88rem; }
                    .bracket-table { border-spacing: 0.3rem 0.35rem; }
                }
            </style>
            <div class='bracket-shell'>
                <div class='bracket-scroll'>
        <table class='bracket-table'>
            <thead>
                <tr>";
                    while($ko_finallevel>1){
                        $sql = 'SELECT * FROM Turnier_KO_Finallevel WHERE id = ' . $ko_finallevel;
                        $result_sql = $conn->query($sql);
                        while ($row_sql = $result_sql->fetch_assoc()) {
                            $name = $row_sql["name"];
                            echo "<td class='round-title'><i>$name</i></td>";
                        }
                        $ko_finallevel--;
                    }
                    echo "<td class='round-title'><i>Gewinnerteam</i></td>";
                    $ko_finallevel = $start_ko_finallevel; //zuruecksetzen
        echo"
                </tr>
            </thead>
            <tbody>
            ";
            //Einen Tree erstellen
            $recursiveTree = recursiveTreeHelper($ko_finallevel);

            //SQL-ABFRAGE IN (doppeltes) ARRAY SCHREIBEN
            $treeArray = array(); //Array erstellen
            
            $index = 0;
            while ($ko_finallevel > 0){
                $zaehlerForKoPosition = 1;
                while($zaehlerForKoPosition < pow(2,($ko_finallevel-2))+1){ //Zaehler bis zu 2^x (x=Finalstufe, zB Stufe 4 hat 2^(4-1)=8 ) ||| -2 weil ja 2 und nicht 1 das Finale ist
                    //Begegnung (eine) finden, die zum Zaehler passt + restliche Bed. (zB der vorherigen  Stufe & des aktuellen Turniers)
                    $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = ' . $ko_finallevel . ' AND ko_turnierbaumposition = '. $zaehlerForKoPosition .' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition ASC, id ASC'; //AND NOT fk_siegerteam = NULL 
                    $resultBegegnung = $conn->query($sqlBegegnung);
                    $siegerGefunden = false;
                    $zumindestBegegnungGefunden = false;
                    while (!empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                        if($rowBegegnung['status'] == 5 || $rowBegegnung['status'] == 7){
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
                            //INS ARRAY EINFUEGEN
                            $treeArray[$index][$zaehlerForKoPosition-1] = $teamString;

                            $siegerGefunden = true;
                        }
                        
                        //SONDERFALL: ERSTES FINALLEVEL - hier koennen keine Gewinner abgelesen werden, sondern es muessen einfach das Heimteam und Auswaertsteam abgelesen werden
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
                            //INS ARRAY EINFUEGEN
                            $treeArray[$index-1][$zaehlerForKoPosition*2-1] = $teamString1;
                            $treeArray[$index-1][$zaehlerForKoPosition*2] = $teamString2;

                            $zumindestBegegnungGefunden = true;
                        }
                        
                    }
                    if($siegerGefunden == false){ //Falls keine Begegnung gefunden wurde, wird ein Platzhalter eingefuegt
                        $treeArray[$index][$zaehlerForKoPosition-1] = "...";
                    } 
                    if($zumindestBegegnungGefunden == false){
                        //SONDERFALL: ERSTES FINALLEVEL - hier koennen keine Gewinner abgelesen werden, sondern es muessen einfach das Heimteam und Auswaertsteam abgelesen werden
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
            $ko_finallevel = $start_ko_finallevel; //zuruecksetzen
            
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
                            $slotClass = 'bracket-slot';
                            if($zaehler === $maxKoLevel){
                                $slotClass .= ' bracket-winner';
                            }
                            echo "<td class='$slotClass'>$value</td>";
                        }else{
                            echo "<td class='bracket-slot placeholder'><i>...</i></td>";
                        }
                        
                    }else{
                        echo "<td class='bracket-line'></td>";
                    }
                    $zaehler++;
                }
                echo "</tr>";
            }
        echo"
            </tbody>
        </table>
                </div>
            </div>
        </section>
        ";  
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
        echo "<ul class='alt'>";
        $platzierungsZaehler = 1;
        $limit = 0;
        //zählen wie viele Teams es gibt
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY ID';
        $resultTeamZeile = $conn->query($sqlTeam);
        while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
            $limit++;
        }
        while($platzierungsZaehler <= $limit){
            $teamName = "<i>noch nicht bestimmt</i>";
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND endplatzierung = '. $platzierungsZaehler .' ORDER BY endplatzierung DESC'; //AND NOT endplatzierung = NULL
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

    function printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, $action, $test_turnier_id){
        if($gameEditMode == 1){
            echo "<h2 style='color:#00FF00'>Bearbeitungsmodus</h2>";
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
            else {  echo "<li style='color:#00FF00'><button style='background-color:green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&check;</button> Mit diesen Buttons tragt ihr ein, dass ein Spiel ergebnislos bleibt, also bspw. nicht stattfinden kann. </li>";}
            echo "<li style='color:#00FF00'><button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>&#9733;</button> Dieser Button zeigt an, dass ein Spiel als final markiert wurde. Solltet ihr nachträglich doch noch ein Spiel eintragen wollen, könnt ihr euch an einen Administrator wenden.</li>";
            //grey: #888888
            echo "</ul>";
            if(!$expertenmodus){
                echo "
                    <form style='color:#00FF00' method='post' action=?test_turnier_id=$test_turnier_id$action>      
                        <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>E</button>
                        <p>Expertenmodus</p>  
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
            if($gameEditMode == 1 && $status != '5' && $status != '7'){ //editMode & noch nicht final
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

        if($gameEditMode == 1 && ($status == '5' || $status == '7')){ //SCHON FINAL
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
                        <button style='text-align="center";background-color:#7700FF; padding: 0 0.3rem; height: 1.5rem; line-height: 1.3rem; font-size: 1.3rem;'>
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

    function printSpielplanGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id, $darfAlleSpieleBearbeiten = false, $bnEingeloggt = '', $pwEingeloggt = ''){
        try {
            //Button, mit dem man den Bearbeitungsmodus starten kann
            printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#gruppenphase", $test_turnier_id);

            // ================================================================================================
            // TESTMODUS: "Zufällige Spiele eintragen" (nur sichtbar/wirksam im Testturnier, dunkelblau)
            // ================================================================================================
            // Führt zur Auswahlseite backstage_zufaellige_spiele, wo man den Prozentsatz der noch offenen
            // Gruppenphasen-Begegnungen wählt, die auf einen Schlag zufällig befüllt+finalisiert werden.
            if ($test_turnier_id != 0) {
                echo "<p><a href='?test_turnier_id=$test_turnier_id&zufall_scope=gruppenphase#backstage_zufaellige_spiele' class='tbl-action-btn tbl-action-btn--testmodus'>Zufällige Spiele eintragen</a></p>";
            }

            // ================================================================================================
            // "ALLE GRUPPEN FINALISIEREN/UNFINALISIEREN" - ersetzt den früheren, kaputten Klick-auf-den-
            // Gruppennamen-Mechanismus (falscher Action-Name "final_group" statt "Gruppe_Finalisieren" UND
            // fehlende bn/pw-Felder - die Funktion konnte serverseitig nie erfolgreich sein). Rechte-Check:
            // gleiche Berechtigung wie normales Spiele-Finalisieren (rechte_alle_spiele-Flag).
            // ================================================================================================
            if ($darfAlleSpieleBearbeiten) {
                $bnAttrSp = htmlspecialchars($bnEingeloggt, ENT_QUOTES);
                $pwAttrSp = htmlspecialchars($pwEingeloggt, ENT_QUOTES);
                $sqlGesamtCheck = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 0 AND status <> 3 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ')';
                $anzahlGesamt = (int)$conn->query($sqlGesamtCheck)->fetch_assoc()['anzahl'];
                if ($anzahlGesamt > 0) {
                    $sqlOffenCheck = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 0 AND status NOT IN (3,5,6,7) AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ')';
                    $anzahlOffen = (int)$conn->query($sqlOffenCheck)->fetch_assoc()['anzahl'];
                    $alleFinalisiert = ($anzahlOffen === 0);
                    $alleGruppenAction = $alleFinalisiert ? 'Alle_Gruppen_Unfinalisieren' : 'Alle_Gruppen_Finalisieren';
                    $alleGruppenLabel = $alleFinalisiert ? 'Alle Gruppen unfinalisieren' : 'Alle Gruppen finalisieren';
                    echo "<form action='website_datachange/edit_games.php' method='POST' style='margin-bottom:0.8rem;'>
                        <input type='hidden' name='TurnierID' value='$TurnierID'>
                        <input type='hidden' name='bn' value='$bnAttrSp'>
                        <input type='hidden' name='pw' value='$pwAttrSp'>
                        <input type='hidden' name='action' value='$alleGruppenAction'>
                        <button type='submit' class='tbl-action-btn tbl-action-btn--admin'>$alleGruppenLabel</button>
                    </form>";
                }
            }

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
                // MÖGLICHKEIT, EINE EINZELNE GRUPPE ZU FINALISIEREN (eigener Button statt anklickbarer
                // Überschrift - die alte Variante nutzte einen falschen Action-Namen und hatte gar keine
                // bn/pw-Felder, konnte serverseitig also nie funktionieren).
                echo "<div class='matrix-group-heading'>";
                echo "<h2 style='display:inline-block; margin-right:0.6rem;'>Gruppe $gruppenname &#9733;</h2>";
                if ($darfAlleSpieleBearbeiten) {
                    echo "<form action='website_datachange/edit_games.php' method='POST' style='display:inline-block; margin:0;'>
                        <input type='hidden' name='TurnierID' value='$TurnierID'>
                        <input type='hidden' name='bn' value='$bnAttrSp'>
                        <input type='hidden' name='pw' value='$pwAttrSp'>
                        <input type='hidden' name='action' value='Gruppe_Finalisieren'>
                        <input type='hidden' name='groupId' value='" . $rowGruppe['id'] . "'>
                        <button type='submit' class='tbl-action-btn tbl-action-btn--admin'>Gruppe finalisieren</button>
                    </form>";
                }
                echo "</div>";
                echo "
                <div class='matrix-table-wrap'>
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
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    if ( empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'>";  // Tabellen-Feld eröffnen
                                        echo " - ";
                                    }
                                    //SONST BEGEGNUNGEN AUSGEBEN
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
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
                </table>
                </div>";
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
            $sqlTeamsLB = 'SELECT DISTINCT t.id FROM Turnier_Team t WHERE t.geloescht = 0 AND t.fk_turnier = ' . $TurnierID . ' AND (t.id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20) OR t.id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20)) ORDER BY t.id';
            $resTeamsLB = $conn->query($sqlTeamsLB);
            while ($resTeamsLB && ($rt = $resTeamsLB->fetch_assoc())) { $teams[] = (int)$rt['id']; }

            echo "<div class='matrix-group-heading'><h2>Gruppe Losing Bracket &#9733;</h2></div>";
            // Wenn noch keine Teams im LB sind, Hinweis anzeigen und abbrechen
            if (count($teams) === 0) {
                echo "<div class='note'>Noch keine Spiele im Losing‑Bracket vorhanden. Die Begegnungen werden automatisch erzeugt, sobald die ersten Teams ausgeschieden sind.</div>";
                return;
            }
            echo "<div class='matrix-table-wrap'>";
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
                    $sqlBeg = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND ((fk_heimteam = ' . $rowTid . ' AND fk_auswaertsteam = ' . $colTid . ') OR (fk_heimteam = ' . $colTid . ' AND fk_auswaertsteam = ' . $rowTid . ')) AND ko_finallevel = 20 ORDER BY ID';
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
            echo "</div>";
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen. Fehlermeldung: ***Fehler bei printSpielplan(): $msg*** ###</i>";
        }
    }

    function printPunktetabelleLosingBracket($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id){
        echo "<h2>Punktetabelle</h2>";
        echo "<table class='withBorderCollapse'><thead><tr><th>Team</th><th>Abk.</th><th>Sp.</th><th>Fl.</th><th>Pkt.</th></tr></thead><tbody>";
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND (id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20) OR id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20)) ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
        $resultTeamZeile = $conn->query($sqlTeam);
        $__lb_rows = 0;
        while ($resultTeamZeile && ($rowTeamZeile = $resultTeamZeile->fetch_assoc())) {
            $name=$rowTeamZeile["name"]; $teamId=$rowTeamZeile["id"];
            $gruppenphase_spiele=$rowTeamZeile["gruppenphase_spiele"];
            $gruppenphase_flaschen=$rowTeamZeile["gruppenphase_flaschen"];
            $gruppenphase_punkte=$rowTeamZeile["gruppenphase_punkte"];
            echo "<tr>";
            echo "<td style=\"text-align:left; padding: 0.1em 0.75em !important;\">$name</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">"; $return = printKuerzelWithLink($conn, $teamId); echo $return; echo "</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_spiele</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_flaschen</td>";
            echo "<td style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_punkte</td>";
            echo "</tr>";
            $__lb_rows++;
        }
        if ($__lb_rows === 0) {
            echo "<tr><td colspan='5' style='text-align:center; opacity:.8;'>Noch keine Teams im Losing‑Bracket erfasst.</td></tr>";
        }
        echo "</tbody></table>";
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
    }

    // RECHTE-AUDIT: 7. Parameter hieß vorher $istAdminOderCoAdmin - jetzt reines turnier_settings-Flag,
    // damit der Button/Toggle nur sichtbar ist, wenn edit_variables.php die Aktion auch wirklich annimmt.
    function printKO_PhaseTabellen($TurnierID, $conn, $istBackstageEingeloggt, $gameEditMode, $expertenmodus, $test_turnier_id, $darfTurnierSettingsAendern = false, $bnEingeloggt = '', $pwEingeloggt = ''){
        //Button, mit dem man den Bearbeitungsmodus starten kann
        printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#kophase", $test_turnier_id);

        if ($istBackstageEingeloggt) {
            echo "
            <style>
                .green-card-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; margin-left: 3px; vertical-align: middle; }
                .begegnung-gesperrt-row { opacity: 0.5; }
                .begegnung-gesperrt-label { font-size: 10px; color: #e74c3c; }
            </style>
            <a href='#backstage_begegnungen_bearbeiten' class='admin-menu-button'>Begegnung bearbeiten</a>
            <h5><br/></h5>
            ";
        }

        //$start_ko_finallevel herausfinden
        $sql = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID;
        $result_sql = $conn->query($sql);
        while ($row_sql = $result_sql->fetch_assoc()) {
            $start_ko_finallevel = $row_sql["start_ko_finallevel"];
            //echo '<script>console.log('.$start_ko_finallevel.')</script>';
        }
        // Anzeige-Reihenfolge: normal von der Start-Finalstufe abwärts, aber "Spiel um Platz 3"
        // (Finallevel 1) und "Finale" (Finallevel 2) werden bewusst ans Ende getauscht, damit das
        // Finale ganz unten steht (mit dem "Turnier abschließen"-Button direkt darunter) und das
        // Spiel um Platz 3 direkt darüber.
        $koLevelReihenfolge = [];
        for ($lvl = $start_ko_finallevel; $lvl >= 3; $lvl--) { $koLevelReihenfolge[] = $lvl; }
        $koLevelReihenfolge[] = 1; // Spiel um Platz 3
        $koLevelReihenfolge[] = 2; // Finale (ganz unten)
        foreach ($koLevelReihenfolge as $ko_finallevel) {
            //Überschrift aus Datenbank suchen
            $sqlFinallevel = 'SELECT * FROM `Turnier_KO_Finallevel` WHERE id = ' . $ko_finallevel . ' ORDER BY ID';
            $resultFinallevel = $conn->query($sqlFinallevel);
            while ($rowFinallevel = $resultFinallevel->fetch_assoc()) {
                $name = $rowFinallevel["name"];
                echo "<h3>$name</h3>";
            }
            // ============================================================================================
            // TESTMODUS: "Zufällige Spiele eintragen" für GENAU DIESE Finalstufe (nur im Testturnier, dunkelblau)
            // ============================================================================================
            if ($test_turnier_id != 0) {
                echo "<p><a href='?test_turnier_id=$test_turnier_id&zufall_scope=ko&zufall_ko_finallevel=$ko_finallevel#backstage_zufaellige_spiele' class='tbl-action-btn tbl-action-btn--testmodus'>Zufällige Spiele eintragen</a></p>";
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
                        // ================================================================================================
                        // GREEN-CARD-VISUALISIERUNG + GESPERRTE BEGEGNUNGEN AUSGRAUEN (nur für eingeloggte Backstage-Nutzer)
                        // ================================================================================================
                        // status: 1=normal, 3=veraltet (vom Auto-Scheduler ersetzt), 4=Green Card (manuell angelegt,
                        // vor Überschreiben geschützt), 5=finalisiert normal, 6=gesperrt (vor Auto-Scheduler geschützt,
                        // fuer die Oeffentlichkeit unsichtbar), 7=Green Card finalisiert. Der grüne Punkt neben der
                        // Begegnungs-ID markiert Status 4/7, gesperrte Begegnungen werden für Backstage-Nutzer
                        // zusätzlich (ausgegraut + Label) angezeigt, damit nachvollziehbar bleibt, was gesperrt wurde.
                        // Erst alle Begegnungen des aktuellen Turniers (Heim oder Auswärtsspiel) filtern und dann dazu die passenden Spiele suchen
                        // Öffentlich: gesperrte (6) und veraltete (3) Begegnungen werden nie angezeigt.
                        // Eingeloggt (Backstage-Rechte): gesperrte Begegnungen werden zusätzlich (ausgegraut) angezeigt, damit nachvollziehbar bleibt, was gesperrt wurde.
                        $statusFilterKoPhase = $istBackstageEingeloggt ? '`status` <> 3' : '`status` NOT IN (3, 6)';
                        $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE ' . $statusFilterKoPhase . ' AND ko_finallevel = ' . $ko_finallevel . ' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') ORDER BY ko_turnierbaumposition';
                        $resultBegegnung = $conn->query($sqlBegegnung);
                        while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Gegegnung gibt
                            //IDs der Teams speichern
                            $ko_turnierbaumposition=$rowBegegnung["ko_turnierbaumposition"];
                            $heimteamID=$rowBegegnung["fk_heimteam"];
                            $auswaertsteamID=$rowBegegnung["fk_auswaertsteam"];
                            $begegnungId=$rowBegegnung["id"];
                            $siegerteam=$rowBegegnung["fk_siegerteam"];
                            $status = $rowBegegnung['status'];
                            $istGesperrt = ($status == 6);
                            $zellenStyle = $istGesperrt ? "opacity: 0.5;" : "";
                            $greenCardDot = ($status == 4 || $status == 7) ? " <span class='green-card-dot' title='Green Card (manuell angelegt)'></span>" : '';
                            $gesperrtLabel = $istGesperrt ? " <span class='begegnung-gesperrt-label'>(gesperrt)</span>" : '';
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
                                echo "<td style='$zellenStyle'>$ko_turnierbaumposition. <p style='font-size: 10px'>#$begegnungId$greenCardDot$gesperrtLabel</p></td><td style='$zellenStyle background-color:green;word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td style='$zellenStyle'>"; //Heimteam kommt ganz links hin
                            }else{
                                echo "<td style='$zellenStyle'>$ko_turnierbaumposition. <p style='font-size: 10px'>#$begegnungId$greenCardDot$gesperrtLabel</p></td><td style='$zellenStyle word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td style='$zellenStyle'>"; //Heimteam kommt ganz links hin
                            }

                            //Spiele zu den Begegnungen finden
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
                                echo "</td><td style='$zellenStyle background-color:green;word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }else{
                                echo "</td><td style='$zellenStyle word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }

                            echo "</tr><tr>";
                        }
            echo"   </tr>
                </tbody>
            </table>";

            // Direkt unter der ersten Finalstufe: Umschalter für "Gruppenphase vorbei / KO-Einzug fertig".
            // Nur wer das turnier_settings-Flag hat, da das die automatische KO-Berechnung auslöst.
            // Nur relevant/sichtbar, wenn der Einzug in die K.-o.-Phase laut Turnier Settings überhaupt manuell angelegt wird -
            // im Automatik-Modus berechnet die Website das selbst, dieser Schalter hätte dort keine Wirkung.
            if ($ko_finallevel == $start_ko_finallevel && $darfTurnierSettingsAendern) {
                $sqlEinzugFertig = 'SELECT einzug_ko_manuell_anlegen, einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei FROM Turnier_Main WHERE id = ' . $TurnierID;
                $resultEinzugFertig = $conn->query($sqlEinzugFertig);
                $rowEinzugFertig = $resultEinzugFertig->fetch_assoc();
                $einzugKoManuellAnlegen = (int)$rowEinzugFertig['einzug_ko_manuell_anlegen'];
                $einzugFertig = (int)$rowEinzugFertig['einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei'];

                if ($einzugKoManuellAnlegen == 1) {
                    // Eindeutige Darstellung: das Häkchen "Status" zeigt/setzt den aktuellen Wert,
                    // ein separates "bestätigen"-Häkchen sendet die Änderung erst ab - man kann also
                    // in Ruhe umschalten (auch zurück), ohne dass ein Klick sofort etwas auslöst.
                    $checkedAttr = ($einzugFertig == 1) ? "checked" : "";
                    $statusText = ($einzugFertig == 1) ? "aktuell: aktiviert" : "aktuell: deaktiviert";
                    // In ein kleines violettes Admin-Kästchen gepackt (gleiche Akzentfarbe wie überall
                    // sonst im Backstage-Bereich), damit auf einen Blick klar ist, dass das hier eine
                    // Funktion mit Rechte-Voraussetzung ist (sichtbar nur mit turnier_settings-Flag).
                    echo "
                    <div style='text-align:center;margin:1rem 0;'>
                    <div style='display:inline-block; background: rgba(139, 92, 246, 0.15); border: 1px solid #8b5cf6; border-radius: 8px; padding: 0.6rem 1rem;'>
                    <form action='website_datachange/edit_variables.php' method='POST' style='margin:0;display:inline-flex;align-items:center;gap:0.6rem;flex-wrap:wrap;justify-content:center;'>
                        <input type='hidden' name='TurnierID' value='$TurnierID'/>
                        <input type='hidden' name='action' value='Einzug_KO_Fertig_Umschalten'/>
                        <input type='hidden' name='bn' value='$bnEingeloggt'/>
                        <input type='hidden' name='pw' value='$pwEingeloggt'/>
                        <span>Gruppenphase beendet / K.-o.-Einzug fertig angelegt (<i>$statusText</i>):</span>
                        <input type='checkbox' id='ko_einzug_fertig' name='einzug_ko_fertig' value='1' $checkedAttr>
                        <label for='ko_einzug_fertig'>aktiviert</label>
                        <label class='admin-toggle'>
                            <input type='checkbox' onchange='this.form.submit()'>
                            <span>bestätigen</span>
                        </label>
                    </form>
                    </div>
                    </div>
                    ";
                }
            }

            // ============================================================================================
            // TURNIER ABSCHLIESSEN - JETZT ALS TOGGLE (wie "Gruppenphase beendet"), nicht mehr Einbahnstraße
            // ============================================================================================
            // Direkt unter dem Finale (Finallevel 2), sobald ein Sieger feststeht: gleiches Muster wie der
            // "Gruppenphase beendet"-Umschalter oben - eigenes Status-Häkchen + separates "bestätigen"-
            // Häkchen, damit man in Ruhe umschalten (auch wieder zurück) kann, ohne dass ein Klick sofort
            // etwas auslöst. Nur wer das turnier_settings-Flag hat.
            if ($ko_finallevel == 2 && $darfTurnierSettingsAendern) {
                $sqlFinaleSieger = 'SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = 2 AND status NOT IN (3,6) AND fk_siegerteam IS NOT NULL AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') LIMIT 1';
                $resultFinaleSieger = $conn->query($sqlFinaleSieger);
                if ($resultFinaleSieger && $resultFinaleSieger->fetch_assoc()) {
                    $rowPhaseAktuellTa = $conn->query('SELECT fk_turnier_phase FROM Turnier_Main WHERE id = ' . $TurnierID)->fetch_assoc();
                    $turnierAbgeschlossen = ((int)$rowPhaseAktuellTa['fk_turnier_phase'] === 9);
                    $taCheckedAttr = $turnierAbgeschlossen ? "checked" : "";
                    $taStatusText = $turnierAbgeschlossen ? "aktuell: abgeschlossen" : "aktuell: nicht abgeschlossen";
                    echo "
                    <div style='text-align:center;margin:1rem 0;'>
                    <div style='display:inline-block; background: rgba(139, 92, 246, 0.15); border: 1px solid #8b5cf6; border-radius: 8px; padding: 0.6rem 1rem;'>
                    <form action='website_datachange/edit_variables.php' method='POST' style='margin:0;display:inline-flex;align-items:center;gap:0.6rem;flex-wrap:wrap;justify-content:center;'>
                        <input type='hidden' name='TurnierID' value='$TurnierID'/>
                        <input type='hidden' name='action' value='Turnier_Abschliessen_Umschalten'/>
                        <input type='hidden' name='bn' value='$bnEingeloggt'/>
                        <input type='hidden' name='pw' value='$pwEingeloggt'/>
                        <span>Turnier abschließen (<i>$taStatusText</i>):</span>
                        <input type='checkbox' id='ta_abgeschlossen' name='turnier_abgeschlossen' value='1' $taCheckedAttr>
                        <label for='ta_abgeschlossen'>abgeschlossen</label>
                        <label class='admin-toggle'>
                            <input type='checkbox' onchange='this.form.submit()'>
                            <span>bestätigen</span>
                        </label>
                    </form>
                    </div>
                    </div>
                    ";
                }
            }

        }
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

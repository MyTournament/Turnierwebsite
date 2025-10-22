<?php
// Increase execution time locally to avoid timeouts during heavy updates
if (php_sapi_name() !== 'cli') {
    $isLocal = false;
    if (isset($_SERVER['SERVER_NAME'])) {
        $host = strtolower((string)$_SERVER['SERVER_NAME']);
        $isLocal = ($host === 'localhost' || $host === '127.0.0.1');
    }
    if (!$isLocal && isset($_SERVER['HTTP_HOST'])) {
        $host = strtolower((string)$_SERVER['HTTP_HOST']);
        $isLocal = ($host === 'localhost' || $host === '127.0.0.1');
    }
    if ($isLocal) {
        @ini_set('max_execution_time', '120');
        @set_time_limit(120);
    }
}
//FUNCTIONS
    function begegnungErstellen($conn, $team1ID, $team2ID, $ko_finallevel, $ko_turnierbaumposition){
        //Falls beide Teams gefunden kann die Begegnung erstellt werden
        //checken dass auch wirklich beide Gewinnerteams gefunden wurden
        if($team1ID == "platzhalter" || $team2ID == "platzhalter"){
            //sonst nichts machen
        }else{ //Wird nur aufgerufen wenn beide Gewinnerteams (und damit auch die Verliererteams) gefunden wurden
            //Erst gucken ob es schon eine Begegnung gibt
            $sqlKOBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE fk_heimteam = ' . $team1ID . ' AND fk_auswaertsteam = ' . $team2ID . ' AND ko_finallevel = ' . $ko_finallevel . ' ORDER BY ID';
            $resultKOBegegnung = $conn->query($sqlKOBegegnung);
            //Wenn es keine gibt, dann einfügen
            if ( empty( $rowKOBegegnung = $resultKOBegegnung->fetch_assoc() ) ){ // nur wenn empty
                $stmt = $conn->prepare("INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (NULL, $team1ID, $team2ID, NULL, $ko_finallevel, $ko_turnierbaumposition, 1);"); //(?, ?, ?, ?, ?, ?, ?)
                //$stmt->bind_param("sssssss", NULL, $gewinnerTeam1ID, $gewinnerTeam2ID, NULL, $ko_finallevel, NULL, 0);
                if ( $stmt === false ){
                    throw new Exception('Begegnung der restlichen Finalstufen (außer der ersten) konnten nicht erstellt werden');
                }
                $stmt->execute();
            }else{ //Wenn Begegnung schon existiert, dann muss der Status geupdated werden
                //TODO: Auch Fall bedenken, dass es zwei gleiche Begegnungen gibt, dann würden hier beide als nicht unnötig markiert werden. -> Gibts da ne Lösung?
                $stmtStatuseBegegnung = $conn->prepare('UPDATE Turnier_Begegnung SET status = 1, ko_turnierbaumposition = '. $ko_turnierbaumposition .' WHERE status <> 4 AND status <> 5 AND fk_heimteam = '. $team1ID .' AND fk_auswaertsteam = '. $team2ID .' AND ko_finallevel = '. $ko_finallevel .'');
                if ( $stmtStatuseBegegnung === false ){
                    throw new Exception('Status konnte nicht geupdated werden');
                }
                $stmtStatuseBegegnung->execute();
            }
        }
    }
    function setTeamPlatziertLevel($conn, $TurnierID, $TeamId, $ko_finallevel){
        $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `platziert_level` = '$ko_finallevel' WHERE geloescht = 0 AND Turnier_Team.id = '$TeamId';"); //AND `Turnier`.`id` = '$TurnierID'
        if ( $stmt === false ){
            throw new Exception('platziert_level konnte nicht gesetzt werden.');
        }
        $stmt->execute();
    }
    function setSiegesQuote($conn, $TurnierID, $TeamId){
        //AUSRECHNEN
        $siege = 0; //für SIEGESQUOTE
        $niederlagen = 0;
        $siegesquote = 0;
        $sqlBeg = 'SELECT * FROM Turnier_Begegnung WHERE `status` <> 3 AND (fk_heimteam = ' . $TeamId . ' OR fk_auswaertsteam = ' . $TeamId . ') ORDER BY id';
        $resultBeg = $conn->query($sqlBeg);
        while (!empty($rowBeg = $resultBeg->fetch_assoc())) {
            $begegnungId = $rowBeg['id'];
            $heimteamID=$rowBeg["fk_heimteam"];
            $auswaertsteamID=$rowBeg["fk_auswaertsteam"];        
            //SIEGESQUOTE AUSRECHNEN
            $sqlSiegesquote = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
            $resultSiegesquote = $conn->query($sqlSiegesquote); 
            while ($rowSiegesquote = $resultSiegesquote->fetch_assoc()) {
                $biereheimteam = $rowSiegesquote['biereheimteam'];
                $biereauswaertsteam = $rowSiegesquote['biereauswaertsteam'];

                if($TeamId == $heimteamID){
                    if($biereheimteam > $biereauswaertsteam){
                        $siege++;
                    }else if($biereheimteam < $biereauswaertsteam){
                        $niederlagen++;
                    }
                }else if($TeamId == $auswaertsteamID){
                    if($biereheimteam > $biereauswaertsteam){
                        $niederlagen++;
                    }else if($biereheimteam < $biereauswaertsteam){
                        $siege++;
                    }
                }
            }
        }
        if($siege+$niederlagen != 0){ // nur wenn das Team schon gespielt hat 
            $siegesquote = ($siege/($siege+$niederlagen))*100;
            //IN DB SCHREIBEN
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `siegesquote` = '$siegesquote' WHERE geloescht = 0 AND Turnier_Team.id = '$TeamId';"); //AND `Turnier`.`id` = '$TurnierID'
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
        }
        

    }
    // Gesamtstatistik aus allen Turnierspielen (alle KO-Level)
    function computeOverallStats($conn, $TeamId){
        $spiele = 0; $flaschen = 0; $punkte = 0;
        // Heimspiele
        $sqlH = 'SELECT id FROM Turnier_Begegnung WHERE `status` <> 3 AND fk_heimteam = ' . (int)$TeamId . ' ORDER BY id';
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
        $sqlA = 'SELECT id FROM Turnier_Begegnung WHERE `status` <> 3 AND fk_auswaertsteam = ' . (int)$TeamId . ' ORDER BY id';
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
        return array('spiele'=>$spiele,'flaschen'=>$flaschen,'punkte'=>$punkte);
    }
    function setAllEndplatzierungen($conn, $TurnierID){
        //zählen wie viele Teams es gibt
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY ID';
        $resultTeamZeile = $conn->query($sqlTeam);
        $teamZaehler = 0;
        while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
            $teamZaehler++;
        }
        $platzierung = $teamZaehler;
        //GRUPPENPHASE
        //--------------------
        // Gruppenphase ausgeschiedene Teams (nur platziert_level = 0) anhand Gesamtstatistik sortieren
        $teamsGF = array();
        $sql = 'SELECT id FROM Turnier_Team WHERE geloescht = 0 AND platziert_level = 0 AND fk_turnier = '. (int)$TurnierID;
        $result = $conn->query($sql);
        while ($result && ($row = $result->fetch_assoc())) {
            $tid = (int)$row['id'];
            $st = computeOverallStats($conn, $tid);
            $teamsGF[] = array('id'=>$tid,'punkte'=>$st['punkte'],'flaschen'=>$st['flaschen'],'spiele'=>$st['spiele']);
        }
        // Für die Gruppenphase hier aufsteigend sortieren (schlechteste zuerst),
        // damit die schlechtesten Plätze die höchsten Endplatzierungen erhalten
        // und die besten ausgeschiedenen Teams niedrigere Endplatzierungen (z.B. 5) bekommen.
        usort($teamsGF, function($a,$b){
            if ($a['punkte'] !== $b['punkte']) return ($a['punkte'] < $b['punkte']) ? -1 : 1; // weniger Punkte zuerst
            if ($a['flaschen'] !== $b['flaschen']) return ($a['flaschen'] < $b['flaschen']) ? -1 : 1; // weniger Flaschen zuerst
            if ($a['spiele'] !== $b['spiele']) return ($a['spiele'] < $b['spiele']) ? -1 : 1; // weniger Spiele zuerst
            return ($a['id'] < $b['id']) ? -1 : 1;
        });
        foreach ($teamsGF as $row) {
            $TeamId = (int)$row['id'];
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = '$platzierung' WHERE geloescht = 0 AND Turnier_Team.id = '$TeamId';");
            if ( $stmt === false ){
                throw new Exception('endplatzierung konnte nicht gesetzt werden.');
            }
            $stmt->execute();
            $stmt->close();
            $platzierung--;
        }

        //KO-PHASE außer "Finale" und "Spiel um Platz 3" und "Halbfinale"
        //--------------------
        // KO-Phase (größer als Halbfinale): ursprüngliche Logik nach Level und Siegesquote
        $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND platziert_level > 3 AND fk_turnier = '. $TurnierID .' ORDER BY platziert_level DESC, siegesquote ASC, id DESC';
        $result = $conn->query($sql);
        while (!empty($row = $result->fetch_assoc())) {
            $TeamId = $row['id'];
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = '$platzierung' WHERE geloescht = 0 AND Turnier_Team.id = '$TeamId';");
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
            $platzierung--;
        }
        
        //Spiel um Platz 3
        //--------------------

        //Teams aus "Spiel um Platz 3" ermitteln
        $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND platziert_level = 1 AND fk_turnier = '. $TurnierID .' ORDER BY platziert_level DESC, gruppenphase_manuelle_platzierung ASC, gruppenphase_punkte DESC, gruppenphase_flaschen DESC, gruppenphase_spiele DESC, id DESC';
        $result = $conn->query($sql);
        $team_ids = array();
        while (!empty($row = $result->fetch_assoc())) {
            $team_ids[] = $row['id'];
        }
        
        if (!empty($team_ids)){            
            //Gewinner- und Verliererteam ermitteln
            $sql = 'SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = 1 AND (fk_heimteam = ' . $team_ids[0] . ' OR fk_heimteam = ' . $team_ids[1] . ')';
            $result = $conn->query($sql);
            if (!empty($row = $result->fetch_assoc())) {
                $winner_team_id = $row['fk_siegerteam'];
                if ($winner_team_id === $team_ids[0]){
                    $loser_team_id = $team_ids[1];
                } else {
                    $loser_team_id = $team_ids[0];
                }
            }

            // Verliererteam: Platzierung setzen 
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = 4 WHERE geloescht = 0 AND Turnier_Team.id = '$loser_team_id';"); //AND `Turnier`.`id` = '$TurnierID'
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
            
            // Gewinnerteam: Platzierung setzen 
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = 3 WHERE geloescht = 0 AND Turnier_Team.id = '$winner_team_id';"); //AND `Turnier`.`id` = '$TurnierID'
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
        }
        
        //Finale
        //--------------------

        //Teams aus "Finale" ermitteln
        $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND platziert_level = 2 AND fk_turnier = '. $TurnierID .' ORDER BY platziert_level DESC, gruppenphase_manuelle_platzierung ASC, gruppenphase_punkte DESC, gruppenphase_flaschen DESC, gruppenphase_spiele DESC';
        $result = $conn->query($sql);
        $team_ids = array();
        while (!empty($row = $result->fetch_assoc())) {
            $team_ids[] = $row['id'];
        }

        if (!empty($team_ids)){
            //Gewinner- und Verliererteam ermitteln
            $sql = 'SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = 2 AND (fk_heimteam = ' . $team_ids[0] . ' OR fk_heimteam = ' . $team_ids[1] . ')';
            $result = $conn->query($sql);
            if (!empty($row = $result->fetch_assoc())) {
                $winner_team_id = $row['fk_siegerteam'];
                if ($winner_team_id === $team_ids[0]){
                    $loser_team_id = $team_ids[1];
                } else {
                    $loser_team_id = $team_ids[0];
                }
            }

            // Verliererteam: Platzierung setzen 
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = 2 WHERE geloescht = 0 AND Turnier_Team.id = '$loser_team_id';"); //AND `Turnier`.`id` = '$TurnierID'
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
            //Zähler
            $platzierung--;
            
            // Gewinnerteam: Platzierung setzen 
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = 1 WHERE geloescht = 0 AND Turnier_Team.id = '$winner_team_id';"); //AND `Turnier`.`id` = '$TurnierID'
            if ( $stmt === false ){
                throw new Exception('siegesquote konnte nicht gesetzt werden.');
            }
            $stmt->execute();
        }
    }
    /*function setTeamEndplatzierung($conn, $TurnierID, $TeamId, $endplatzierung){
        //FALL: Zurücksetzen oder TOP 3
        if(is_numeric($endplatzierung)){ //is_numeric($endplatzierung) ->FÜR DEN FALL DASS 1, 2 oder 3 für die TOP 3 übergeben wird ODER dass 0 übergeben wird || $endplatzierung==0 || $endplatzierung=='NULL'
            //Platzierung updaten
            $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = '$endplatzierung' WHERE Turnier_Team.id = '$TeamId';");
            if ( $stmt === false ){
                throw new Exception('endplatzierung konnte nicht berechnet werden.');
            }
            $stmt->execute();
        }else{
            //zählen wie viele Teams es gibt
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY ID';
            $resultTeamZeile = $conn->query($sqlTeam);
            $teamZaehler = 0;
            while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                $teamZaehler++;
            }*/
            //zählen wie viele Teams schon eine Platzierung bekommen haben
            /*$sqlTeam = 'SELECT * FROM `Team` WHERE fk_turnier = ' . $TurnierID . ' AND endplatzierung > 0 ORDER BY endplatzierung';
            $resultTeamZeile = $conn->query($sqlTeam);
            $platzierungZaehler = 0;
            while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                $platzierungZaehler++;
            }
            $endplatzierung = $teamZaehler-$platzierungZaehler;
            if($endplatzierung<0){
                $endplatzierung=0;
            }*/
            /*$endplatzierung = $teamZaehler;
            while($endplatzierung>3){
                //gucken ob diese Platzierung schon vergeben ist
                $bool = 0;
                $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE fk_turnier = ' . $TurnierID . ' AND endplatzierung = '. $endplatzierung .' ORDER BY endplatzierung';
                $resultTeamZeile = $conn->query($sqlTeam);
                while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                    $bool = 1;
                }
                if($bool == 0){
                    //Platzierung updaten
                    $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `endplatzierung` = '$endplatzierung' WHERE (endplatzierung is NULL OR endplatzierung = 0) AND Turnier_Team.id = '$TeamId';"); //AND `Turnier`.`id` = '$TurnierID'
                    if ( $stmt === false ){
                        throw new Exception('endplatzierung konnte nicht berechnet werden.');
                    }
                    $stmt->execute();
                    break;
                }else{
                    $endplatzierung--;
                }
            }
            
        }
    }*/
    function calculateTheWinnerAndWriteInDatabase($conn, $TurnierID, $rowBegegnung){ //Gewinnerteam berechnen
        $FlaschenZaehlerTeam1 = 0;
        $PunkteZaehlerTeam1 = 0;
        $FlaschenZaehlerTeam2 = 0;
        $PunkteZaehlerTeam2 = 0;
        $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $rowBegegnung['id'] . ' ORDER BY ID';
        $resultSpiel = $conn->query($sqlSpiel);	
        if ($resultSpiel === false) {
            throw new Exception('Abfrage Turnier_Spiel fehlgeschlagen.');
        }
        while ($rowSpiel = $resultSpiel->fetch_assoc()) {
            $a=$rowSpiel["biereheimteam"];
            $b=$rowSpiel["biereauswaertsteam"];
            $FlaschenZaehlerTeam1+=$a;
            $FlaschenZaehlerTeam2+=$b;
            if($a>$b){
                $PunkteZaehlerTeam1++;
            }else if($b>$a){
                $PunkteZaehlerTeam2++;
            }
        }			
        //GewinnerteamID in $gewinnerTeam1ID schreiben
        if($PunkteZaehlerTeam1 > $PunkteZaehlerTeam2){
            $gewinnerTeam1ID = $rowBegegnung['fk_heimteam'];
            $verliererTeam1ID = $rowBegegnung['fk_auswaertsteam']; //Für Spiel um Platz 3 wichtig
        }else if($PunkteZaehlerTeam2 > $PunkteZaehlerTeam1){
            $gewinnerTeam1ID = $rowBegegnung['fk_auswaertsteam'];
            $verliererTeam1ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
        }else{ //Fall dass Punkte gleich sind: Es wird nach Flaschen entschieden
            if($FlaschenZaehlerTeam1 > $FlaschenZaehlerTeam2){
                $gewinnerTeam1ID = $rowBegegnung['fk_heimteam'];
                $verliererTeam1ID = $rowBegegnung['fk_auswaertsteam'];  //Für Spiel um Platz 3 wichtig
            }else if($FlaschenZaehlerTeam2 > $FlaschenZaehlerTeam1){
                $gewinnerTeam1ID = $rowBegegnung['fk_auswaertsteam'];
                $verliererTeam1ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
            }else{
                //Nur einen Gewinner bestimmen wenn schon ein Spiel gemacht wurde
                $gewinnerTeam1ID = "platzhalter"; //Hier weise ich "platzhalter" zu damit bei nächster Iteration nicht wieder diese if-Bedingung aufgerufen wird. Da wird die ID dann eh zurückgesetz
                $verliererTeam1ID = "platzhalter";  //Für Spiel um Platz 3 wichtig
            }
        }
        if($rowBegegnung['fk_siegerteam_manuell'] != NULL){
            $gewinnerTeam1ID = $rowBegegnung['fk_siegerteam_manuell'];
            $stmt = $conn->prepare('UPDATE Turnier_Begegnung SET `fk_siegerteam` = '. $gewinnerTeam1ID .' WHERE id = ' . $rowBegegnung['id'] . ' ORDER BY id');
            if ( $stmt === false ){
                throw new Exception('Siegerteam konnte nicht berechnet werden.');
            }
            $stmt->execute();
        }else{
            //echo "<script>console.log('gewinnerTeam1ID: $gewinnerTeam1ID')</script>";
            if($gewinnerTeam1ID != "platzhalter" && $verliererTeam1ID != "platzhalter" && $rowBegegnung['status'] == 5){ //NUR WENN FINAL
                $stmt = $conn->prepare('UPDATE Turnier_Begegnung SET `fk_siegerteam` = '. $gewinnerTeam1ID .' WHERE id = ' . $rowBegegnung['id'] . ' ORDER BY id');
                if ( $stmt === false ){
                    throw new Exception('Siegerteam konnte nicht berechnet werden.');
                }
                $stmt->execute();
            }
        }
        
        
        return $verliererTeam1ID;
    }


// Losing Bracket: einfache Hülle (Platzhalter für Logik)
    function update_losing_bracket($conn, $TurnierID){
        // Flags für KO-Einzug laden
        $sqlFlags = 'SELECT einzug_ko_manuell_anlegen, einzug_ko_fertig_manuell_angelegt FROM Turnier_Main WHERE id = ' . (int)$TurnierID . ' LIMIT 1';
        $resultFlags = $conn->query($sqlFlags);
        $einzug_ko_manuell_anlegen = 0; $einzug_ko_fertig_manuell_angelegt = 0;
        if ($resultFlags && ($row = $resultFlags->fetch_assoc())) {
            $einzug_ko_manuell_anlegen = (int)$row['einzug_ko_manuell_anlegen'];
            $einzug_ko_fertig_manuell_angelegt = (int)$row['einzug_ko_fertig_manuell_angelegt'];
        }

        // Prüfen, ob Gruppenphase komplett ist (alle Gruppenspiele final)
        $alleGruppenFinal = 1; // TRUE
        $sqlGrp = 'SELECT g.id FROM Turnier_Gruppe g WHERE g.id IN (SELECT fk_gruppe FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . (int)$TurnierID . ') ORDER BY g.id';
        $resGrp = $conn->query($sqlGrp);
        while ($resGrp && ($rg = $resGrp->fetch_assoc())) {
            $gid = (int)$rg['id'];
            $sqlChk = 'SELECT b.status FROM Turnier_Begegnung b, Turnier_Team a, Turnier_Team c '
                    . 'WHERE a.geloescht = 0 AND c.geloescht = 0 AND b.status <> 3 AND b.ko_finallevel = 0 '
                    . 'AND b.fk_heimteam = a.id AND b.fk_auswaertsteam = c.id '
                    . 'AND a.fk_gruppe = ' . $gid . ' AND c.fk_gruppe = ' . $gid;
            $resChk = $conn->query($sqlChk);
            while ($resChk && ($rb = $resChk->fetch_assoc())) {
                $st = $rb['status'];
                if ($st != '5' && $st != '4') { $alleGruppenFinal = 0; }
            }
        }

        // Startbedingungen wie beim KO-Einzug: automatisch erst nach vollständiger Gruppenphase; bei manuell erst, wenn fertig angelegt
        if ($einzug_ko_manuell_anlegen == 0) {
            if ($alleGruppenFinal != 1) {
                echo "<script>console.log('losing_bracket: Gruppenphase noch nicht final.')</script>";
                return;
            }
        } else {
            if ($einzug_ko_fertig_manuell_angelegt != 1) {
                echo "<script>console.log('losing_bracket: manuelle Einzüge noch nicht fertig.')</script>";
                return;
            }
        }

        // Teilnehmer fürs Losing Bracket: alle Teams dieses Turniers mit platziert_level = 0 (in Gruppenphase ausgeschieden)
        $teilnehmer = [];
        // Sicherstellen: Alle Teams, die nicht in KO-Begegnungen (ko_finallevel > 1) stehen, erhalten platziert_level = 0 - somit auch Teams die sich vlt. jetzt erst anmelden
        try {
            $tid = (int)$TurnierID;
            $sqlSetPL0 = "UPDATE Turnier_Team t
                           LEFT JOIN (
                               SELECT DISTINCT x.id AS team_id
                               FROM Turnier_Team x
                               WHERE x.geloescht = 0 AND x.fk_turnier = $tid
                                 AND x.id IN (
                                     SELECT b.fk_heimteam FROM Turnier_Begegnung b WHERE b.ko_finallevel > 1 AND b.status <> 3
                                     UNION
                                     SELECT b.fk_auswaertsteam FROM Turnier_Begegnung b WHERE b.ko_finallevel > 1 AND b.status <> 3
                                 )
                           ) ko ON ko.team_id = t.id
                           SET t.platziert_level = 0
                           WHERE t.geloescht = 0 AND t.fk_turnier = $tid AND ko.team_id IS NULL";
            $conn->query($sqlSetPL0);
        } catch (Throwable $e) {
            echo "<script>console.warn('platziert_level=0-Setzung warn: " . addslashes($e->getMessage()) . "')</script>";
        }

        $sqlTeilnehmer = 'SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . (int)$TurnierID . ' AND (platziert_level = 0) ORDER BY id';
        $resTeilnehmer = $conn->query($sqlTeilnehmer);
        while ($resTeilnehmer && ($rt = $resTeilnehmer->fetch_assoc())) { $teilnehmer[] = (int)$rt['id']; }

        // Ergänzung: Alle Teams, die bereits in LB-Begegnungen (ko_finallevel = 20) auftauchen, ebenfalls in die Teilnehmerliste aufnehmen
        $sqlTeilnehmerLB = 'SELECT DISTINCT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . (int)$TurnierID . ' AND (id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20) OR id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20)) ORDER BY id';
        $resTeilnehmerLB = $conn->query($sqlTeilnehmerLB);
        while ($resTeilnehmerLB && ($rt = $resTeilnehmerLB->fetch_assoc())) {
            $tidLB = (int)$rt['id'];
            if (!in_array($tidLB, $teilnehmer, true)) { $teilnehmer[] = $tidLB; }
        }
        sort($teilnehmer, SORT_NUMERIC);

        // Round-Robin Begegnungen für LB anlegen (ko_finallevel = 20) und als nicht veraltet markieren (status=1)
        for ($i = 0; $i < count($teilnehmer); $i++) {
            for ($j = $i+1; $j < count($teilnehmer); $j++) {
                $a = (int)$teilnehmer[$i];
                $b = (int)$teilnehmer[$j];
                // Prüfen, ob es die Begegnung schon gibt (in einer Richtung)
                $sqlChk = 'SELECT id FROM Turnier_Begegnung WHERE status <> 3 AND ((fk_heimteam = ' . $a . ' AND fk_auswaertsteam = ' . $b . ') OR (fk_heimteam = ' . $b . ' AND fk_auswaertsteam = ' . $a . ')) AND ko_finallevel = 20 LIMIT 1';
                $resChk = $conn->query($sqlChk);
                if (!$resChk || !$resChk->fetch_assoc()) {
                    $stmt = $conn->prepare("INSERT INTO Turnier_Begegnung (fk_heimteam, fk_auswaertsteam, fk_siegerteam, ko_finallevel, ko_turnierbaumposition, status) VALUES (?, ?, NULL, 20, NULL, 1)");
                    $stmt->bind_param("ii", $a, $b);
                    $stmt->execute();
                } else {
                    // Falls vorhanden, als aktiv markieren
                    $stmt = $conn->prepare("UPDATE Turnier_Begegnung SET status = 1 WHERE ko_finallevel = 20 AND ((fk_heimteam = ? AND fk_auswaertsteam = ?) OR (fk_heimteam = ? AND fk_auswaertsteam = ?)) AND status <> 4 AND status <> 5");
                    $stmt->bind_param("iiii", $a, $b, $b, $a);
                    $stmt->execute();
                }
            }
        }

        // Deduplizierung: genau eine Begegnung je Team-Paar im LB behalten
        // - Behalte pro (min(team1,team2), max(team1,team2)) die kleinste ID -> status=1
        // - Markiere alle weiteren IDs als veraltet -> status=3
        try {
            $tid = (int)$TurnierID;
            $sqlKeep = "UPDATE Turnier_Begegnung t
                        JOIN Turnier_Team th ON th.id = t.fk_heimteam
                        JOIN Turnier_Team ta ON ta.id = t.fk_auswaertsteam
                        JOIN (
                            SELECT MIN(t2.id) AS keep_id,
                                   LEAST(t2.fk_heimteam, t2.fk_auswaertsteam) AS a,
                                   GREATEST(t2.fk_heimteam, t2.fk_auswaertsteam) AS b
                            FROM Turnier_Begegnung t2
                            JOIN Turnier_Team th2 ON th2.id = t2.fk_heimteam
                            JOIN Turnier_Team ta2 ON ta2.id = t2.fk_auswaertsteam
                            WHERE t2.ko_finallevel = 20 AND th2.fk_turnier = $tid AND ta2.fk_turnier = $tid
                            GROUP BY a, b
                        ) k
                          ON LEAST(t.fk_heimteam, t.fk_auswaertsteam) = k.a
                         AND GREATEST(t.fk_heimteam, t.fk_auswaertsteam) = k.b
                        SET t.status = CASE WHEN t.id = k.keep_id THEN 1 ELSE 3 END
                        WHERE t.ko_finallevel = 20 AND t.status <> 4 AND t.status <> 5 AND th.fk_turnier = $tid AND ta.fk_turnier = $tid";
            $conn->query($sqlKeep);
        } catch (Throwable $e) {
            echo "<script>console.warn('LB-Dedupe warn: " . addslashes($e->getMessage()) . "')</script>";
        }

        // Sicherheitspass: Für jedes Teilnehmer-Paar genau 1 aktive Begegnung (status <> 3) anlegen
        // Falls keine vorhanden ist: neu erstellen; falls mehrere: kleinste ID aktiv lassen, Rest veralten.
        for ($i = 0; $i < count($teilnehmer); $i++) {
            for ($j = $i+1; $j < count($teilnehmer); $j++) {
                $a = (int)$teilnehmer[$i];
                $b = (int)$teilnehmer[$j];
                $ids = [];
                $sqlPairs = 'SELECT id FROM Turnier_Begegnung WHERE ko_finallevel = 20 AND status <> 3 AND ((fk_heimteam = ' . $a . ' AND fk_auswaertsteam = ' . $b . ') OR (fk_heimteam = ' . $b . ' AND fk_auswaertsteam = ' . $a . ')) ORDER BY id';
                $resPairs = $conn->query($sqlPairs);
                while ($resPairs && ($rp = $resPairs->fetch_assoc())) { $ids[] = (int)$rp['id']; }
                if (count($ids) === 0) {
                    $stmt = $conn->prepare("INSERT INTO Turnier_Begegnung (fk_heimteam, fk_auswaertsteam, fk_siegerteam, ko_finallevel, ko_turnierbaumposition, status) VALUES (?, ?, NULL, 20, NULL, 1)");
                    $stmt->bind_param("ii", $a, $b);
                    $stmt->execute();
                } elseif (count($ids) > 1) {
                    sort($ids, SORT_NUMERIC);
                    $keep = $ids[0];
                    // aktiv setzen
                    $conn->query('UPDATE Turnier_Begegnung SET status = 1 WHERE id = ' . $keep);
                    // rest veralten
                    for ($k = 1; $k < count($ids); $k++) {
                        $conn->query('UPDATE Turnier_Begegnung SET status = 3 WHERE id = ' . (int)$ids[$k] . ' AND status <> 4 AND status <> 5');
                    }
                }
            }
        }

        // gruppenphase_* für LB neu berechnen (nur LB-Gruppe; aus LB-Spielen ko_finallevel=20)
        if (!empty($teilnehmer)) {
            // Reset nur für LB-Teams (by IDs)
            $ids = implode(',', array_map('intval', $teilnehmer));
            if ($ids === '') { $ids = '0'; }
            // LB schreibt keine gruppenphase_* mehr in die DB
            // $stmtR = $conn->prepare("UPDATE Turnier_Team SET gruppenphase_spiele = 0, gruppenphase_flaschen = 0, gruppenphase_punkte = 0 WHERE id IN ($ids) AND geloescht = 0");
            // $stmtR->execute();

            foreach ($teilnehmer as $tid) {
                $spiele = 0; $flaschen = 0; $punkte = 0;
                // Heimspiele
                $sqlH = 'SELECT id FROM Turnier_Begegnung WHERE status <> 3 AND fk_heimteam = ' . (int)$tid . ' AND ko_finallevel = 20 ORDER BY id';
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
                $sqlA = 'SELECT id FROM Turnier_Begegnung WHERE status <> 3 AND fk_auswaertsteam = ' . (int)$tid . ' AND ko_finallevel = 20 ORDER BY id';
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

                // LB schreibt keine gruppenphase_* mehr in die DB
                // $stmtU = $conn->prepare("UPDATE Turnier_Team SET gruppenphase_spiele = ?, gruppenphase_flaschen = ?, gruppenphase_punkte = ? WHERE id = ? AND geloescht = 0");
                // $stmtU->bind_param("iiii", $spiele, $flaschen, $punkte, $tid);
                // $stmtU->execute();
            }
        }

        echo "<script>console.log('losing_bracket: erstellt/aktualisiert (ko_finallevel=20).')</script>";
    }

//main
    function db_update($conn, $TurnierID){
        echo "<script>console.log('TurnierID2: " . $TurnierID . "');</script>";
        // **ERROR HANDLING **
        //try {
        //Aktuelle Turnierphase herausfinden - erstmal ID
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
            }
        if($turnier_phase_ID == 1){ //Noch keine Anmeldung möglich
            //do nothing
        }if($turnier_phase_ID == 3  || $turnier_phase_ID == 11){ //Anmeldezeitraum ODER Debug-Modus
            //max_anzahl_teams herausfinden
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $max_anzahl_teams = $rowTurnier['max_anzahl_teams'];
            }
            //Anzahl Teams zählen
            $counter = 0;
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .' ORDER BY ID';
            $resultTeam = $conn->query($sqlTeam);
            while ($rowTeam = $resultTeam->fetch_assoc()) {
                $counter++;
            }
            //Falls genug Teams angemeldet sind -> Turnierphase auf Warteliste setzen
            if($counter >= $max_anzahl_teams){
                $stmtWarteliste = $conn->prepare("UPDATE Turnier_Main SET fk_turnier_phase = 12 WHERE id = '$TurnierID' ORDER BY ID");
                if ( $stmtWarteliste === false ){
                    throw new Exception('Warteliste konnte nicht aktiviert werden');
                }
                $stmtWarteliste->execute();
            }
        }if($turnier_phase_ID == 12){ //Warteliste
            //do nothing
        }if($turnier_phase_ID == 4  || $turnier_phase_ID == 11){ //Gruppengröße neu bestimmen & ERSTELLEN/LÖSCHEN ODER Debug-Modus
            //ANZAHL DER GRUPPEN BESTIMMEN
                //anzahl_gruppen berechnen & //festlegen wie viele Finalstufen es gibt
                //-> kleinstes: 4
                //-> wenn es min. 3*8=24 Teams gibt, dann 8, dann hätte jede Gruppe 3 Teams
                //anzahl_gruppen in Datenbank schreiben
                /*$sqlTeamZaehlen = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY ID'; //Teams filtern die keine Gruppe haben
                $resultTeamZaehlen = $conn->query($sqlTeamZaehlen);
                $teamZaehler = 0;
                while (!empty($rowTeamZaehlen = $resultTeamZaehlen->fetch_assoc())) {
                    $teamZaehler++;
                }
                if($teamZaehler>=0 && $teamZaehler<12){ //24
                    $anzahl_gruppen = 2;
                    $start_ko_finallevel = 3;
                }else if($teamZaehler>=12 && $teamZaehler<24){ //24
                    $anzahl_gruppen = 4;
                    $start_ko_finallevel = 4;
                }else if($teamZaehler>=24 && $teamZaehler<=48){ //24 //16*3=48
                    $anzahl_gruppen = 8;
                    $start_ko_finallevel = 5;
                }else{
                    $anzahl_gruppen = 16;
                    $start_ko_finallevel = 6;
                }

                //wenn neue Gruppenanzahl erreicht wird, müssen alle Teams ihre Gruppe verlieren und neu zugeteilt werden
                //-> $anzahl_gruppen mit anzahl_gruppen aus Turnier vergleichen, und wenns abweicht, dann allen Teams die Gruppe wegnehmen
                $sqlanzahl_gruppen = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID . ' ORDER BY id'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                $resultanzahl_gruppen = $conn->query($sqlanzahl_gruppen);
                while ($rowanzahl_gruppen = $resultanzahl_gruppen->fetch_assoc()) {
                    $anzahl_gruppen_database = $rowanzahl_gruppen['anzahl_gruppen'];
                }
                if($anzahl_gruppen_database!=$anzahl_gruppen){ //falls ungleich, alle Gruppen löschen
                    $stmtTeams = $conn->prepare("UPDATE `Turnier_Team` SET `fk_gruppe` = NULL WHERE geloescht = 0 AND fk_turnier = '$TurnierID';");
                    if ( $stmtTeams === false ){
                        throw new Exception('Es wurde versucht, alle Gruppen zu löschen. Das ist fehlgeschlagen.');
                    }
                    $stmtTeams->execute();  
                }else{} //do nothing

                //anzahl_gruppen in Datenbank schreiben
                $stmt = $conn->prepare("UPDATE `Turnier_Main` SET `anzahl_gruppen` = '$anzahl_gruppen' WHERE `Turnier_Main`.`id` = '$TurnierID';");
                if ( $stmt === false ){
                    throw new Exception('anzahl_gruppen konnte nicht in die Datenbank geschrieben werden.');
                }
                $stmt->execute();

                //start_ko_finallevel in Datenbank schreiben
                $stmt2 = $conn->prepare("UPDATE `Turnier_Main` SET `start_ko_finallevel` = '$start_ko_finallevel' WHERE `Turnier_Main`.`id` = '$TurnierID';");
                if ( $stmt2 === false ){
                    throw new Exception('start_ko_finallevel konnte nicht in die Datenbank geschrieben werden.');
                }
                $stmt2->execute();*/

                $sqlanzahl_gruppen = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID . ' ORDER BY id'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                $resultanzahl_gruppen = $conn->query($sqlanzahl_gruppen);
                while ($rowanzahl_gruppen = $resultanzahl_gruppen->fetch_assoc()) {
                    $anzahl_gruppen_database = $rowanzahl_gruppen['anzahl_gruppen'];
                }

                //checken ob es schon genug/zu viel Gruppen gibt
                $sqlGruppenZaehlen = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                $resultGruppenZaehlen = $conn->query($sqlGruppenZaehlen);
                $gruppenZaehler = 0;
                while ($rowGruppenZaehlen = $resultGruppenZaehlen->fetch_assoc()) {
                    $gruppenZaehler++;
                }
                if($gruppenZaehler<$anzahl_gruppen_database){ //zu wenig Gruppen -> erstellen
                    $fehlendeGruppen = $anzahl_gruppen_database-$gruppenZaehler; //Differenz -> so viele Gruppen fehlen noch
                    $gruppennameId = 1;
                    while($fehlendeGruppen>0){
                        //passenden Gruppennamen aus der DB abfragen
                        $sqlGruppenName = 'SELECT * FROM Turnier_Gruppe_Namen WHERE id = ' . $gruppennameId . ' ORDER BY id';
                        $gruppennameId++;
                        $resultGruppenName = $conn->query($sqlGruppenName);
                        $gruppenName = ""; //wird gleich überschrieben
                        while ($rowGruppenName = $resultGruppenName->fetch_assoc()) {
                            $gruppenName = $rowGruppenName['name'];
                        }
                        //Gruppen erstellen
                        //fk_turnier dabei beschreiben, damit Gruppen dem Turnier zuordbar sind
                        $stmt2 = $conn->prepare("INSERT INTO `Turnier_Gruppe` (`name`, `fk_turnier`) VALUES (?, ?);");
                        $stmt2->bind_param("ss", $gruppenName, $TurnierID);
                        if ( $stmt2 === false ){
                            throw new Exception('Es gibt zu wenige Gruppen und leider konnten keine neuen erstellt werden.');
                        }
                        $stmt2->execute();
                        //Zähler dekrementieren
                        $fehlendeGruppen--;
                    }

                }else if($anzahl_gruppen_database<$gruppenZaehler){ //zu viel Gruppen -> löschen
                    $zuVieleGruppen = $gruppenZaehler-$anzahl_gruppen_database; //Differenz -> so viele Gruppen sind zu viel da
                    while($zuVieleGruppen>0){
                        //Gruppen löschen
                        //echo "<script>console.log('###############gruppe löschen############');</script>";
                        $stmt3 = $conn->prepare("DELETE FROM `Turnier_Gruppe` WHERE `Turnier_Gruppe`.`fk_turnier` = '$TurnierID' ORDER BY `id` DESC LIMIT 1;"); //eigentlich hätte ich die letzte Gruppe gelöscht aber es ist eigentlich egal weil die Teams eh neuverteilt werden //AND `id` = (SELECT MAX(id) FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ')
                        if ( $stmt3 === false ){
                            throw new Exception('Es gibt mehr Gruppen als benötigt und es konnten keine gelöscht werden.');
                        }
                        $stmt3->execute();
                        //Zähler dekrementieren
                        $zuVieleGruppen--;
                    }
                }else {} //richtige Anzahl an Gruppen -> nichts tun
                


        }if($turnier_phase_ID == 5  || $turnier_phase_ID == 11){ //Gruppeneinteilung ODER Debug-Modus
                //Teams den Gruppen zuordnen - nicht die erste komplett füllen und danach die zweite sondern gleichmäßig
                //Idee: Array speichert, wie viele Teams in welcher Gruppe sind und funtion wählt minimum aus -> index x
                //mit diesem minimum wird dann eine while der Gruppen durchlaufen und bei dem x-ten eintrag wird das Team dann hinzugefügt
                //Gruppen durchgehen und Array mit Voll-heit anlegen
                $sqlGruppenGroessen = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id'; //Nur Gruppen die zum aktuellen Turnier gehören
                $resultGruppenGroessen = $conn->query($sqlGruppenGroessen);
                $gruppenGroessen = [];
                //$index = 0;
                while ($rowGruppenGroessen = $resultGruppenGroessen->fetch_assoc()) {
                    //Teams der Gruppe zählen
                    $gruppe = $rowGruppenGroessen['id'];
                    $sqlTeamZaehlen = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $gruppe . ' ORDER BY CHAR_LENGTH(name)'; //Teams filtern die keine Gruppe haben
                    $resultTeamZaehlen = $conn->query($sqlTeamZaehlen);
                    $teamZaehler = 0;
                    while (!empty($rowTeamZaehlen = $resultTeamZaehlen->fetch_assoc())) {
                        $teamZaehler++;
                    }
                    //Gruppengröße in Array eintragen
                    $gruppenGroessen[$gruppe] = $teamZaehler; //Gruppengröße

                    //$index++;
                }
                //$index = 123;
                //$array = [
                //    "foo" => "bar",
                //    "$index" => "ichbineinhaus",
                //];
                //$test = $array[123];
                //echo "<script>console.log('Array der Gruppen " . json_encode($gruppenGroessen) . " ');</script>";
                //echo "<script>console.log('Minimum: " . min($gruppenGroessen) . " ');</script>";
                $key = array_search(min($gruppenGroessen), $gruppenGroessen);
                //echo "<script>console.log('Key vom Minimum: " . $key . " ');</script>";

                //TEAMS DEN GRUPPEN ZUORDNEN
                $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe is NULL ORDER BY ID'; //Teams filtern die keine Gruppe haben
                $resultTeamZeile = $conn->query($sqlTeam);
                while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                    $teamID = $rowTeamZeile["id"]; //ID des gefundenen Teams speichern
                    $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `fk_gruppe` = '$key' WHERE geloescht = 0 AND `Turnier_Team`.`id` = '$teamID';");
                    if ( $stmt === false ){
                        throw new Exception('Teams konnten nicht den Gruppen zugeordnet werden.');
                    }
                    $stmt->execute();
                    //Array aktualisieren
                    $gruppenGroessen[$key] = $gruppenGroessen[$key]+1;
                    $key = array_search(min($gruppenGroessen), $gruppenGroessen); //Key des neuen Minimums finden
                    //echo "<script>console.log('#nochmal Array der Gruppen " . json_encode($gruppenGroessen) . " ');</script>";
                }
            
                

                

        }if($turnier_phase_ID == 7 || $turnier_phase_ID == 13 || $turnier_phase_ID == 11){ //Turnier läuft ODER Debug-Modus  
            
            if($turnier_phase_ID == 13){ //Nachmeldungen erlaubt
                $didAssignNewTeam = 0; // Flag für Laststeuerung
                //Gruppengrößen ermitteln (nur Gruppen dieses Turniers) – performant via Aggregation
                $gruppenGroessen = [];
                // Alle Gruppen des Turniers initial auf 0 setzen
                $sqlGruppen = 'SELECT id FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
                $resultGruppen = $conn->query($sqlGruppen);
                while ($rowG = $resultGruppen->fetch_assoc()) {
                    $gruppenGroessen[$rowG['id']] = 0;
                }
                // Pro Gruppe Anzahl Teams holen
                $sqlCounts = 'SELECT fk_gruppe AS gruppe, COUNT(*) AS anzahl FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe IS NOT NULL GROUP BY fk_gruppe';
                $resultCounts = $conn->query($sqlCounts);
                while ($rowC = $resultCounts->fetch_assoc()) {
                    $gId = $rowC['gruppe'];
                    if(isset($gruppenGroessen[$gId])){
                        $gruppenGroessen[$gId] = (int)$rowC['anzahl'];
                    }
                }

                if(!empty($gruppenGroessen)){
                    // Genau EIN neues, unzugewiesenes Team (falls vorhanden) holen
                    $sqlUngueteam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe is NULL ORDER BY id LIMIT 1';
                    $resultUngueteam = $conn->query($sqlUngueteam);
                    if ($rowTeam = $resultUngueteam->fetch_assoc()) {
                        $teamID = $rowTeam['id'];
                        $keyMin = array_search(min($gruppenGroessen), $gruppenGroessen);
                        $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `fk_gruppe` = '$keyMin' WHERE geloescht = 0 AND `Turnier_Team`.`id` = '$teamID';");
                        if ( $stmt === false ){
                            throw new Exception('Neues Team konnte keiner Gruppe zugewiesen werden.');
                        }
                        $stmt->execute();
                        $didAssignNewTeam = 1; // mindestens ein Team zugewiesen
                    }
                }
            }


            
            //STATUS SETZEN
                //Hier wird allen Begegnungen der Status auf 2 (als veraltet vormarkiert) gesetzt, immer wenn eine Begegung im folgenden dann bei einer Berechnung auftaucht, wird der Status auf 1 (nicht veraltet) gesetzt
                //Am Ende werden dann alle Begegnung mit Status veraltet gelöscht
                $stmtVeralteteBegegnung = $conn->prepare("UPDATE Turnier_Begegnung SET `status` = 2 WHERE `status` = 1 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID')");
                if ( $stmtVeralteteBegegnung === false ){
                    throw new Exception('veraltet-Status aller Begegnungen konnte nicht auf TRUE gesetzt werden.');
                }
                $stmtVeralteteBegegnung->execute();
                // LOSING BRACKET (Hook) direkt nach Vormarkieren aufrufen,
                // damit benötigte LB-Begegnungen frühzeitig wieder auf status=1 gesetzt werden können
                update_losing_bracket($conn, $TurnierID);
            
            //GRUPPENPHASE
                //BEGEGNUNGEN FÜR GRUPPENPHASE ANLEGEN // id IN (SELECT fk_gruppe FROM Team WHERE fk_turnier = ' . $TurnierID . ')';
                $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE fk_turnier = ' . $TurnierID . '';
                $resultGruppe = $conn->query($sqlGruppe);
                while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                    $array = array(); //WIRD FÜR SCHALTER UNTEN BENÖTIGT
                    $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY ID';
                    $resultTeamZeile = $conn->query($sqlTeam);
                    while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                        $resultTeamSpalte = $conn->query($sqlTeam);
                        while ($rowTeamSpalte = $resultTeamSpalte->fetch_assoc()) {
                            // Erst alle Begegnungen filtern und dann dazu die passenden Spiele suchen
                            $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID'; //NICHT status <> 3 !!!!
                            $resultBegegnung = $conn->query($sqlBegegnung);
                            $TeamZeileID = $rowTeamZeile['id'];
                            $TeamSpalteID =$rowTeamSpalte['id'];
                            //SCHALTER -> soll in Gruppenphasentabelle nur obere Hälfte gefüllt werden -> 1
                            $sqlSchalter = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID . '';
                            $resultSchalter = $conn->query($sqlSchalter);
                            while ($rowSchalter = $resultSchalter->fetch_assoc()) {
                                $schalterDreieck = $rowSchalter['nurOberesDreieckInGruppenphase'];
                            }
                            $anlegen = 0;
                            if($schalterDreieck == 1){
                                $schonVorhanden = 0;
                                foreach ($array as $value) {
                                    if(($value[1] == $TeamZeileID && $value[2] == $TeamSpalteID) || ($value[1] == $TeamSpalteID && $value[2] == $TeamZeileID)){
                                        $schonVorhanden = 1;
                                    }
                                }
                                if($schonVorhanden == 1){
                                    //do nothing
                                }else{
                                    $anlegen = 1;
                                    array_push($array, array(
                                        "1" => $TeamZeileID,
                                        "2" => $TeamSpalteID,)
                                            );
                                }
                            }else{
                                $anlegen = 1; //Wenn Schalter so steht dass alles befüllt werden soll wird $anlegen einfach auf 1 gesetzt
                            }
                            if($anlegen == 1){
                                if($TeamZeileID == $TeamSpalteID){
                                    //do nothing
                                }else{
                                    if ( empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // nur wenn empty
                                        //$stmtBegegnungGruppenphase = $conn->prepare("INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?);");
                                        //Grund für den ganzen ausgeklammerten Code: Ich wollte SQL-Injection verhindern, leider funzt der Code aus irgendeinem Grund hier nicht, deswegen bin ich zurück zur alten Version gegangen. Hier kann nämlich eh keine SQL Injection passieren weil ID auto-generiert wird und der einzige Parameter ist
                                        $stmtBegegnungGruppenphase = $conn->prepare("INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (NULL, $TeamZeileID, $TeamSpalteID, NULL, 0, NULL, 1);");
                                        //INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (NULL, 60, 69, NULL, 0, NULL, 0)
                                        /*$stmtBegegnungGruppenphase->bind_param("ssssssss", NULL, $TeamZeileID, $TeamSpalteID, NULL, 0, NULL, 0, 0);*/
                                        if ( $stmtBegegnungGruppenphase === false ){
                                            throw new Exception('Begegnungen der Gruppenphase konnten nicht erstellt werden.');
                                        }
                                        $stmtBegegnungGruppenphase->execute();
                                    }else{ //Wenn Begegnung schon existiert, dann muss der Status geupdated werden
                                        $stmtNichtVeralteteBegegnung = $conn->prepare("UPDATE Turnier_Begegnung SET status = 1 WHERE status <> 4 AND status <> 5 AND fk_heimteam = '$TeamZeileID' AND fk_auswaertsteam = '$TeamSpalteID' AND ko_finallevel = 0 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID')");
                                        if ( $stmtNichtVeralteteBegegnung === false ){
                                            throw new Exception('veraltet-Status der Gruppenphase konnte nicht geupdated werden.');
                                        }
                                        $stmtNichtVeralteteBegegnung->execute();
                                    }
                                }
                            }
                        }
                    }
                } //TODO: zB Spiel tes gegen tes nicht erstellen bzw beim Eintragen zulassen

            
                //PUNKTE FÜR PUNKTETABELLE DER GRUPPENPHASE BERECHNEN
                // TODO Priorität der Sortierung: Punkte -> direkter Vergleich -> Bierdifferenz -> meiste eigenen getrunkenen Biere  
                $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE id IN (SELECT fk_gruppe FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ')'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                $resultGruppe = $conn->query($sqlGruppe);
                while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                    $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $rowGruppe["id"] . ' ORDER BY ID';
                    $resultTeamZeile = $conn->query($sqlTeam);
                    while ($rowTeamZeile = $resultTeamZeile->fetch_assoc()) {
                    $teamID=$rowTeamZeile["id"];					  
                    // Erst alle Begegnungen (Heim oder Auswärtsspiel) filtern und dann dazu die passenden Spiele suchen
                    // Erst Heimspiele zählen
                    $sqlHeimBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE status <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                    $resultHeimBegegnung = $conn->query($sqlHeimBegegnung);
                    $SpieleZaehler = 0;
                    $FlaschenZaehler = 0;
                    $PunkteZaehler = 0;
                    while ( !empty( $rowHeimBegegnung = $resultHeimBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Gegegnung gibt
                        $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $rowHeimBegegnung["id"] . ' ORDER BY ID';
                        $resultSpiel = $conn->query($sqlSpiel);	
                        while ($rowSpiel = $resultSpiel->fetch_assoc()) {
                            $SpieleZaehler++;
                            $a=$rowSpiel["biereheimteam"];
                            $b=$rowSpiel["biereauswaertsteam"];
                            $FlaschenZaehler+=$a;
                            if($a>$b){
                                $PunkteZaehler++;
                            }
                        }			
                    }
                    // Jetzt Auswärtsspiele zählen
                    $sqlAuswaertsBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE status <> 3 AND fk_auswaertsteam = ' . $rowTeamZeile["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                    $resultAuswaertsBegegnung = $conn->query($sqlAuswaertsBegegnung);
                    while ( !empty( $rowAuswaertsBegegnung = $resultAuswaertsBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Gegegnung gibt
                        $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $rowAuswaertsBegegnung["id"] . ' ORDER BY ID';
                        $resultSpiel = $conn->query($sqlSpiel);	
                        while ($rowSpiel = $resultSpiel->fetch_assoc()) {
                            $SpieleZaehler++;
                            $a=$rowSpiel["biereheimteam"];
                            $b=$rowSpiel["biereauswaertsteam"];
                            $FlaschenZaehler+=$b;
                            if($b>$a){
                                $PunkteZaehler++;
                            }
                        }			
                    }
                    $stmt = $conn->prepare("UPDATE `Turnier_Team` SET `gruppenphase_spiele` = '$SpieleZaehler', `gruppenphase_flaschen` = '$FlaschenZaehler', `gruppenphase_punkte` = '$PunkteZaehler' WHERE geloescht = 0 AND `Turnier_Team`.`id` = '$teamID';");
                    if ( $stmt === false ){
                        throw new Exception('Punkte, Flaschen bzw Spiele konnten nicht geupdated werden (Gruppenphase).');
                    }
                    $stmt->execute();
                    }               
                }
                
            //KO-PHASE
                //Start-Finalstufe rausfinden
                    $sql = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID;
                    $result_sql = $conn->query($sql);
                    while ($row_sql = $result_sql->fetch_assoc()) {
                        $start_ko_finallevel = $row_sql["start_ko_finallevel"];
                        $anzahl_gruppen = $row_sql["anzahl_gruppen"];
                        //echo '<script>console.log('.$start_ko_finallevel.')</script>';
                    }
                    $ko_finallevel = $start_ko_finallevel; //Zähler

                
                //HIER ERSTMAL NUR ERSTE FINALSTUFE
                    
                    //Checken ob die erste Finalstufe manuell angelegt wird oder berechnet werden soll
                    //+ herausfinden ob schon fertig angelegt wurde
                    $sql_einzug_ko_manuell_anlegen = 'SELECT * FROM Turnier_Main WHERE id = '. $TurnierID .'';
                    $result_einzug_ko_manuell_anlegen = $conn->query($sql_einzug_ko_manuell_anlegen);
                    while ($row_einzug_ko_manuell_anlegen = $result_einzug_ko_manuell_anlegen->fetch_assoc()) {
                        $einzug_ko_manuell_anlegen = $row_einzug_ko_manuell_anlegen["einzug_ko_manuell_anlegen"];
                        $einzug_ko_fertig_manuell_angelegt = $row_einzug_ko_manuell_anlegen["einzug_ko_fertig_manuell_angelegt"];
                    }
                    echo "<script>console.log('TurnierID: " . $TurnierID . "' );</script>";
                    echo "<script>console.log('einzug_ko_manuell_anlegen: " . $einzug_ko_manuell_anlegen . "' );</script>";
                    
                    if($einzug_ko_manuell_anlegen==0){ //FALL: AUTOMATISCH DIE STARTPOSITIONEN GENERIEREN

                        $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE id IN (SELECT fk_gruppe FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY turnierposition_for_ko asc, id asc'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                        $resultGruppe = $conn->query($sqlGruppe);
                        
                        //TURNIERBAUMPOSITION
                            //Die Begegnungen werden immer abwechselnd in der ersten und in der zweiten Hälfte des Turnierbaums erstellt, damit Teams, die in gleichen Gruppen waren, erst im Finale wieder matchen können
                            $zaehlerForKoPosition = 1;
                            $zaehlerUngerade = 1;
                            $zaehlerGerade = ($anzahl_gruppen/2)+1; //also bei 8 Gruppen -> 5
                        
                        while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                            if($zaehlerForKoPosition % 2 == 1){ //immer nur bei den ungeraden Gruppenzahlen diesen Prozess ausführen
                                //TURNIERBAUMPOSITION
                                    //jetzt richtigen Wert berechnen
                                        /*$ko_position = 0;
                                        if($zaehlerForKoPosition % 2 == 1){ //wenn ungerade
                                            $ko_position = $zaehlerUngerade;
                                            $zaehlerUngerade++;
                                        }else{
                                            $ko_position = $zaehlerGerade;
                                            $zaehlerGerade++;
                                        }*/
                                //13 24 57 68
                                //1; 2->3; 3->2; 4; 5; 6->7; 7->6; 8
                                
                                $ko_position_team1 = 0;
                                $ko_position_team2 = 0;
                                if ($anzahl_gruppen==2){ //SONDERFALL: Nur zwei Gruppen in Gruppenphase - dann soll natürlich nicht versetzt verteilt werden
                                    $ko_position_team1 = 1;
                                    $ko_position_team2 = 2;
                                }else{ //FALL: Mehr als zwei Gruppen in Gruppenphase - dann werden Teams versetzt verteilt auf KO-Plätze
                                    switch ($zaehlerForKoPosition) {
                                        case 1:
                                            $ko_position_team1 = 1;
                                            $ko_position_team2 = 3;
                                            break;
                                        case 3:
                                            $ko_position_team1 = 2;
                                            $ko_position_team2 = 4;
                                            break;
                                        case 5:
                                            $ko_position_team1 = 5;
                                            $ko_position_team2 = 7;
                                            break;
                                        case 7:
                                            $ko_position_team1 = 6;
                                            $ko_position_team2 = 8;
                                            break;                         
                                    }
                                }

                                $gruppeID = $rowGruppe["id"];
                                $turnierposition_for_ko = $rowGruppe["turnierposition_for_ko"];
                                
                                //Nächste Gruppe finden - damit erster aus aktueller Gruppe mit zweitem aus nächster Gruppe eine Begegnung bekommt
                                $resultNextGruppe = $conn->query($sqlGruppe);
                                while ($rowNextGruppe = $resultNextGruppe->fetch_assoc()) {
                                    //Gruppe mit ID 1 höher als der ID der aktuellen Gruppe finden
                                    $gruppeNextID = 0;
                                    if($rowGruppe["turnierposition_for_ko"] != NULL){ //turnierposition_for_ko sollte nur Kriterium sein, wenn sie auch vergeben wurde 
                                        if($rowNextGruppe["turnierposition_for_ko"] > $turnierposition_for_ko){
                                            $gruppeNextID = $rowNextGruppe["id"];
                                            break;
                                        }
                                    }else{ //Fall dass die turnierposition_for_ko noch nicht manuell vergeben wurde
                                        if($rowNextGruppe["id"] > $gruppeID){
                                            $gruppeNextID = $rowNextGruppe["id"];
                                            break;
                                        }
                                    }
                                    
                                }
                                //Alt: falls es keine nächste Gruppe gibt, dann kleinste ID
                                /*if ($gruppeNextID == 0){
                                    $sqlFirstGruppe = 'SELECT * FROM Turnier_Gruppe WHERE id IN (SELECT fk_gruppe FROM Turnier_Team WHERE fk_turnier = ' . $TurnierID . ') ORDER BY turnierposition_for_ko asc, id asc LIMIT 1';
                                    $resultFirstGruppe = $conn->query($sqlFirstGruppe);
                                    while ($rowFirstGruppe = $resultFirstGruppe->fetch_assoc()) {
                                        $gruppeNextID = $rowFirstGruppe["id"];
                                        break;
                                    }
                                }else{}*/

                                //Erstes Team der aktuellen Gruppe finden
                                $sql = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $gruppeID . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc LIMIT 1';
                                $result = $conn->query($sql);
                                //Erstes Team auswählen
                                $team1Gruppe1ID = 0;
                                while (!empty($row = $result->fetch_assoc())) {
                                    $team1Gruppe1ID = $row["id"];
                                    break;
                                }
                                //Zweites Team der aktuellen Gruppe finden
                                $sql = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $gruppeID . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
                                $result = $conn->query($sql);
                                //Zweites Team auswählen
                                $zaehler = 0;
                                $team2Gruppe1ID = 0;
                                while (!empty($row = $result->fetch_assoc())) {
                                    if($zaehler == 1){
                                        $team2Gruppe1ID = $row["id"];
                                        break;
                                    }else{
                                        $zaehler++;
                                    }
                                }

                                //Erstes Team der nächsten Gruppe finden
                                $sql = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $gruppeNextID . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc LIMIT 1';
                                $result = $conn->query($sql);
                                //Erstes Team auswählen
                                $team1Gruppe2ID = 0;
                                while (!empty($row = $result->fetch_assoc())) {
                                    $team1Gruppe2ID = $row["id"];
                                    break;
                                }
                                //Zweites Team der nächsten Gruppe finden
                                $sql = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . $gruppeNextID . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
                                $result = $conn->query($sql);
                                //Zweites Team auswählen
                                $zaehler = 0;
                                $team2Gruppe2ID = 0;
                                while (!empty($row = $result->fetch_assoc())) {
                                    if($zaehler == 1){
                                        $team2Gruppe2ID = $row["id"];
                                        break;
                                    }else{
                                        $zaehler++;
                                    }
                                }

                                //Herausfinden ob in beiden Gruppen ALLE Spiele gespielt wurden
                                //-> nur Spiele erstellen wenn alle Spiele in beiden Gruppen gemacht wurden
                                $allebegegnungenInGruppeFinal = 1; //TRUE
                                //Aktuelle Gruppe
                                $sqlFirstBegegnungen = 'SELECT * FROM Turnier_Begegnung, Turnier_Team a, Turnier_Team b WHERE a.geloescht = 0 AND b.geloescht = 0 AND Turnier_Begegnung.status <> 3 AND Turnier_Begegnung.ko_finallevel = 0 AND Turnier_Begegnung.fk_heimteam = a.id AND Turnier_Begegnung.fk_auswaertsteam = b.id AND a.fk_gruppe = '. $gruppeID .' AND b.fk_gruppe = '. $gruppeID .'';
                                $resultFirstBegegnungen = $conn->query($sqlFirstBegegnungen);
                                while (!empty($rowFirstBegegnungen = $resultFirstBegegnungen->fetch_assoc())) {
                                    $begegnungsStatus = $rowFirstBegegnungen['status'];
                                    if($begegnungsStatus!='5' && $begegnungsStatus!='4'){
                                        $allebegegnungenInGruppeFinal=0; //FALSE
                                    }
                                }
                                //Nächste Gruppe
                                $sqlSecondBegegnungen = 'SELECT * FROM Turnier_Begegnung, Turnier_Team a, Turnier_Team b WHERE a.geloescht = 0 AND b.geloescht = 0 AND Turnier_Begegnung.status <> 3 AND Turnier_Begegnung.ko_finallevel = 0 AND Turnier_Begegnung.fk_heimteam = a.id AND Turnier_Begegnung.fk_auswaertsteam = b.id AND a.fk_gruppe = '. $gruppeNextID .' AND b.fk_gruppe = '. $gruppeNextID .'';
                                $resultSecondBegegnungen = $conn->query($sqlSecondBegegnungen);
                                while (!empty($rowSecondBegegnungen = $resultSecondBegegnungen->fetch_assoc())) {
                                    $begegnungsStatus = $rowSecondBegegnungen['status'];
                                    if($begegnungsStatus!='5' && $begegnungsStatus!='4'){
                                        $allebegegnungenInGruppeFinal=0; //FALSE
                                    }
                                }

                                //Nur erstellen wenn gerade eben berechnet wurde dass alle Begegnungen in beiden Gruppen final sind
                                if($allebegegnungenInGruppeFinal == 1){
                                    //zählen wie viele Teams in Gruppe
                                    $sqlRausgeflogen = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = '. $gruppeID .' AND fk_turnier = '. $TurnierID .' ORDER BY gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele asc';
                                    $resultRausgeflogen = $conn->query($sqlRausgeflogen);
                                    $counter = 0;
                                    while (!empty($rowRausgeflogen = $resultRausgeflogen->fetch_assoc())) {
                                        $counter++;
                                    }
                                    //LIMIT IST counter-2 weil 2 Teams weiterkommen
                                    $counter = max(0, $counter - 2);
                                    //allen Teams, die rausgefolgen sind eine Platzierung zuweisen
                                    if ($counter > 0) {
                                        $sqlRausgeflogen = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = '. $gruppeID .' ORDER BY gruppenphase_punkte asc, gruppenphase_flaschen asc, gruppenphase_spiele desc LIMIT '. $counter .''; //NOT (id = '. $team1ID .' OR id = '. $team2ID .') AND
                                        $resultRausgeflogen = $conn->query($sqlRausgeflogen);
                                        while (!empty($rowRausgeflogen = $resultRausgeflogen->fetch_assoc())) {
                                            $verliererTeam1ID = $rowRausgeflogen['id'];
                                            setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, 0);
                                        }
                                    }
                                    
                                    //Nächste Gruppe
                                    //zählen wie viele Teams in Gruppe
                                    $sqlRausgeflogen = 'SELECT * FROM Turnier_Team WHERE fk_gruppe = '. $gruppeNextID .' AND fk_turnier = '. $TurnierID .' ORDER BY gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele asc';
                                    $resultRausgeflogen = $conn->query($sqlRausgeflogen);
                                    $counter = 0;
                                    while (!empty($rowRausgeflogen = $resultRausgeflogen->fetch_assoc())) {
                                        $counter++;
                                    }
                                    //LIMIT IST counter-2 weil 2 Teams weiterkommen
                                    $counter = max(0, $counter - 2);
                                    //allen Teams, die rausgefolgen sind eine Platzierung zuweisen
                                    if ($counter > 0) {
                                        $sqlRausgeflogen = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = '. $gruppeNextID .' ORDER BY gruppenphase_punkte asc, gruppenphase_flaschen asc, gruppenphase_spiele desc LIMIT '. $counter .''; //NOT (id = '. $team1ID .' OR id = '. $team2ID .') AND
                                        $resultRausgeflogen = $conn->query($sqlRausgeflogen);
                                        while (!empty($rowRausgeflogen = $resultRausgeflogen->fetch_assoc())) {
                                            $verliererTeam1ID = $rowRausgeflogen['id'];
                                            setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, 0);
                                        }
                                    }

                                    //Erste Finalstufe erstellen (könnte zB Viertel- oder Achtelfinale sein)

                                    //Erste Konstellation
                                    //Erst gucken ob es schon eine Begegnung gibt
                                    $sqlKOBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE fk_heimteam = ' . $team1Gruppe1ID . ' AND fk_auswaertsteam = ' . $team2Gruppe2ID . ' AND ko_finallevel = ' . $ko_finallevel . ' ORDER BY ID'; //NICHT status <> 3 !!!
                                    $resultKOBegegnung = $conn->query($sqlKOBegegnung);
                                    //Wenn es keine gibt, dann einfügen
                                    if ( (int)$team1Gruppe1ID > 0 && (int)$team2Gruppe2ID > 0 && empty( $rowKOBegegnung = $resultKOBegegnung->fetch_assoc() ) ){ // nur wenn empty und gültige Team-IDs
                                        $stmt = $conn->prepare("INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (NULL, $team1Gruppe1ID, $team2Gruppe2ID, NULL, $ko_finallevel, $ko_position_team1, 1);"); //(?, ?, ?, ?, ?, ?, ?)
                                        //$stmt->bind_param("ssssssss", NULL, $team1ID, $team2ID, NULL, $ko_finallevel, NULL, 0, 0);
                                        if ( $stmt === false ){
                                            throw new Exception('Eine Begegnung der ersten Finalstufe konnte nicht erstellt werden.');
                                        }
                                        $stmt->execute();
                                    }else{ //Wenn Begegnung schon existiert, dann muss der veraltet-Status geupdated werden
                                        //TODO: Auch Fall bedenken, dass es zwei gleiche Begegnungen gibt, dann würden hier beide als nicht unnötig markiert werden. -> Gibts da ne Lösung? - Theoretisch werden ja eigentlich nie zwei gleiche Begegnungen erstellt?
                                        $stmtNichtVeralteteBegegnung = $conn->prepare('UPDATE Turnier_Begegnung SET status = 1, ko_turnierbaumposition = '. $ko_position_team1 .' WHERE status <> 4 AND status <> 5 AND fk_heimteam = '. $team1Gruppe1ID .' AND fk_auswaertsteam = '. $team2Gruppe2ID .' AND ko_finallevel = '. $ko_finallevel .' ORDER BY ID');// AND fk_heimteam IN (SELECT id FROM Team WHERE fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Team WHERE fk_turnier = '. $TurnierID .')');
                                        if ( $stmtNichtVeralteteBegegnung === false ){
                                            throw new Exception('Veraltet-Status der ersten Finalstufe konnte nicht geupdated werden.');
                                        }
                                        $stmtNichtVeralteteBegegnung->execute();
                                    }

                                    //Zweite Konstellation
                                    //Erst gucken ob es schon eine Begegnung gibt
                                    $sqlKOBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE fk_heimteam = ' . $team2Gruppe1ID . ' AND fk_auswaertsteam = ' . $team1Gruppe2ID . ' AND ko_finallevel = ' . $ko_finallevel . ' ORDER BY ID'; //NICHT status <> 3 !!!
                                    $resultKOBegegnung = $conn->query($sqlKOBegegnung);
                                    //Wenn es keine gibt, dann einfügen
                                    if ( (int)$team2Gruppe1ID > 0 && (int)$team1Gruppe2ID > 0 && empty( $rowKOBegegnung = $resultKOBegegnung->fetch_assoc() ) ){ // nur wenn empty und gültige Team-IDs
                                        $stmt = $conn->prepare("INSERT INTO `Turnier_Begegnung` (`id`, `fk_heimteam`, `fk_auswaertsteam`, `fk_siegerteam`, `ko_finallevel`, `ko_turnierbaumposition`, `status`) VALUES (NULL, $team2Gruppe1ID, $team1Gruppe2ID, NULL, $ko_finallevel, $ko_position_team2, 1);"); //(?, ?, ?, ?, ?, ?, ?)
                                        //$stmt->bind_param("ssssssss", NULL, $team1ID, $team2ID, NULL, $ko_finallevel, NULL, 0, 0);
                                        if ( $stmt === false ){
                                            throw new Exception('Eine Begegnung der ersten Finalstufe konnte nicht erstellt werden.');
                                        }
                                        $stmt->execute();
                                    }else{ //Wenn Begegnung schon existiert, dann muss der veraltet-Status geupdated werden
                                        //TODO: Auch Fall bedenken, dass es zwei gleiche Begegnungen gibt, dann würden hier beide als nicht unnötig markiert werden. -> Gibts da ne Lösung? - Theoretisch werden ja eigentlich nie zwei gleiche Begegnungen erstellt?
                                        $stmtNichtVeralteteBegegnung = $conn->prepare('UPDATE Turnier_Begegnung SET status = 1, ko_turnierbaumposition = '. $ko_position_team2 .' WHERE status <> 4 AND status <> 5 AND fk_heimteam = '. $team2Gruppe1ID .' AND fk_auswaertsteam = '. $team1Gruppe2ID .' AND ko_finallevel = '. $ko_finallevel .' ORDER BY ID');// AND fk_heimteam IN (SELECT id FROM Team WHERE fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Team WHERE fk_turnier = '. $TurnierID .')');
                                        if ( $stmtNichtVeralteteBegegnung === false ){
                                            throw new Exception('Veraltet-Status der ersten Finalstufe konnte nicht geupdated werden.');
                                        }
                                        $stmtNichtVeralteteBegegnung->execute();
                                    }
                                }else{
                                    //do nothing
                                }
                            }
                            $zaehlerForKoPosition++;
                        }    

                    }else{ //FALL: SCHALTER GELEGT AUF MANUELLE PLATZIERUNG IN ERSTEM KO-LEVEL
                        //Erst Endplatzierungen für rausgeflogene Teams vergeben, sobald alle Startpositionen der KO-Phase vergeben sind
                        //Erklärung: Hierdrunter gibt es theoretisch das veraltete Kriterium, welches die Platzierung vergibt sobald die Gruppe final ist
                        //Das Problem hierbei ist, dass wenn der Schalter auf manuellen Startpositionen liegt, dass dann ja erst finalisiert wird und danach die Startpositionen manuell vergeben werden
                        //Dadurch wbekommen dann schon alle Teams eine Platzierung
                        //Dadurch ist innerhalb dieser Bedingung hier die zweite Bedingung theoretisch redundant, aber stört auch nicht
                        //Deswegen: Nur wenn der manuelle zweite Schalter aussagt, dass auch fertig platziert wurde:
                        if($einzug_ko_fertig_manuell_angelegt == 1){
                            echo "<script>console.log('db_update - rangliste: Startpositionenen der KO-Phase werden manuell angelegt' );</script>";
                            //Alle Teams die keinen manuellen KO-Platz bekommen, sollen Platzierung bekommen
                            //Deswegen wird erst geschaut, welche Teams (EGAL AUS WELCHER GRUPPE) einen KO-Platz haben
                            //Alle Begegnungen des Start-KO-Finallevels aus dem aktuellen Turnier
                            $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = ' . $start_ko_finallevel . ' AND `status` <> 3 
                                AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') 
                                AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') 
                                ORDER BY ko_turnierbaumposition, id'; //AND NOT fk_siegerteam = NULL 
                            $teamsDieWeiterSindArray = array();
                            echo "<script>console.log('db_update - rangliste: teamsDieWeiterSindArray: " . json_encode($teamsDieWeiterSindArray) . "');</script>";

                            //Gruppen durchiterieren, um Gruppen die nicht Teil des Arrays sind eine Endplatzierung zuzuweisen
                            $sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE id IN (SELECT fk_gruppe FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY turnierposition_for_ko asc, id asc'; //Nur Gruppen von Teams die zum aktuellen Turnier gehören
                            $resultGruppe = $conn->query($sqlGruppe);                        
                            while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                                $gruppeID = $rowGruppe["id"];
                                echo "<script>console.log('db_update - rangliste: Checken ob Endplatzierungen erstellt werden sollen für Gruppe mit der ID: " . $gruppeID . "' );</script>";
                                
                                //Endplatzierung für rausgeflogene Teams nur in den Gruppen wo alle Spiele gemacht wurden
                                $allebegegnungenInGruppeFinal = 1; //TRUE
                                $sqlFirstBegegnungen = 'SELECT * FROM Turnier_Begegnung, Turnier_Team a, Turnier_Team b WHERE a.geloescht = 0 AND b.geloescht = 0 AND Turnier_Begegnung.status <> 3 AND Turnier_Begegnung.ko_finallevel = 0 AND Turnier_Begegnung.fk_heimteam = a.id AND Turnier_Begegnung.fk_auswaertsteam = b.id AND a.fk_gruppe = '. $gruppeID .' AND b.fk_gruppe = '. $gruppeID .'';
                                $resultFirstBegegnungen = $conn->query($sqlFirstBegegnungen);
                                while (!empty($rowFirstBegegnungen = $resultFirstBegegnungen->fetch_assoc())) {
                                    $begegnungsStatus = $rowFirstBegegnungen['status'];
                                    if($begegnungsStatus!='5' && $begegnungsStatus!='4'){
                                        $allebegegnungenInGruppeFinal=0; //FALSE
                                    }
                                }
                                echo "<script>console.log('db_update - rangliste: allebegegnungenInGruppeFinal: " . $allebegegnungenInGruppeFinal . "' );</script>";
                                
                                //Nur Platzierung für alle Teams der Gruppe vergeben wenn gerade eben berechnet wurde dass alle Begegnungen in beiden Gruppen final sind
                                if($allebegegnungenInGruppeFinal == 1){
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    while (!empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                                        $fk_heimteam = $rowBegegnung["fk_heimteam"];
                                        $fk_auswaertsteam = $rowBegegnung["fk_auswaertsteam"];

                                        $teamsDieWeiterSindArray[] = $fk_heimteam;
                                        $teamsDieWeiterSindArray[] = $fk_auswaertsteam;
                                    }
                                    
                                    $teamsDieWeiterSindArrayJson = json_encode($teamsDieWeiterSindArray);
                                    echo "<script>console.log('teamsDieWeiterSindArray: " . $teamsDieWeiterSindArrayJson . "' );</script>";

                                    //Jetzt allen Teams (AUS DER GRUPPE) die kein Teil des Arrays sind eine Platzierung geben
                                    $sqlTeam = 'SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = '. $gruppeID .'';
                                    $resultTeam = $conn->query($sqlTeam);
                                    while (!empty( $rowTeam = $resultTeam->fetch_assoc() ) ){
                                        $team_id = $rowTeam["id"];
                                        if (!in_array($team_id, $teamsDieWeiterSindArray)) {
                                            setTeamPlatziertLevel($conn, $TurnierID, $team_id, 0);
                                            echo "<script>console.log('db_update - rangliste: Team mit der ID " . $team_id . " hat keinen manuellen KO-Platz und bekommt eine Platzierung' );</script>";
                                        }
                                    }
                                }
                            }
                        }else{
                            //Hier einen SQL Befehl ausführen, der alle endplatzierungen und platziert_level auf NULL setzt
                            try {
                                $stmtResetPlatzierungen = $conn->prepare('UPDATE `Turnier_Team` SET `endplatzierung` = NULL, `platziert_level` = NULL WHERE geloescht = 0 AND fk_turnier = ?');
                                if ($stmtResetPlatzierungen === false) {
                                    throw new Exception('Platzierungen konnten nicht zurückgesetzt werden.');
                                }
                                $stmtResetPlatzierungen->bind_param('i', $TurnierID);
                                $stmtResetPlatzierungen->execute();
                                $stmtResetPlatzierungen->close();
                                echo "<script>console.log('db_update - rangliste: Alle Endplatzierungen und platziert_level auf NULL zurückgesetzt.');</script>";
                            } catch (Exception $e) {
                                echo "<script>console.error('" . $e->getMessage() . "');</script>";
                            }
                        }

                    }

                    

                
                //RESTLICHE FINALSTUFEN - Für Alle Finalstufen außer der ersten Finalstufe die Begegnungen erstellen
                    //zuerst für alle (übrigen) Begegnungen den Gewinner berechnen und in DB schreiben
                    $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE `status` <> 3 AND ko_finallevel <= '. $ko_finallevel .' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ';//ORDER BY ko_turnierbaumposition, id
                    $resultBegegnung = $conn->query($sqlBegegnung);
                    while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                        calculateTheWinnerAndWriteInDatabase($conn, $TurnierID, $rowBegegnung);
                    }
                    
                    while ($ko_finallevel > 0){
                        $ko_finallevel_next = $ko_finallevel-1;
                        $gewinnerTeam1ID = 0;
                        $gewinnerTeam2ID = 0;
                        $verliererTeam1ID = 0;
                        $verliererTeam2ID = 0;
                        $zaehlerForKoPosition = 1;
                        while($zaehlerForKoPosition < pow(2,($ko_finallevel-2))+1){ //Zähler bis zu 2^x (x=Finalstufe, zB Stufe 4 hat 2^(4-1)=8 ) ||| -2 weil ja 2 und nicht 1 das Finale ist
                            //Begegnung (eine) finden, die zum Zähler passt + restliche Bed. (zB der vorherigen  Stufe & des aktuellen Turniers)
                            //NUR FINALE BEGEGNUNGEN
                            if($zaehlerForKoPosition % 2 == 1){ //Das zurücksetzen der IDs muss außerhalb der SQL-while laufen für den Fall dass keine Begegnung gefunden wurde. Dann muss das ja trotzdem zurückgesetzt werden
                                $gewinnerTeam1ID = 0;
                                $gewinnerTeam2ID = 0;
                                $verliererTeam1ID = 0;
                                $verliererTeam2ID = 0;
                            }
                            $sqlBegegnung = 'SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = ' . $ko_finallevel . ' AND ko_turnierbaumposition = '. $zaehlerForKoPosition .' AND status = 5 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition, id'; //AND NOT fk_siegerteam = NULL 
                            $resultBegegnung = $conn->query($sqlBegegnung);
                            while (!empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){
                                //Checken ob ich das erste oder das zweite Team gefunden habe
                                if($zaehlerForKoPosition % 2 == 1){ //In den Fällen ungerade wird das erste Team der neuen Begegnung gesucht
                                    //IDs zurücksetzen schon hier falls im vorherigen Durchlauf das zweite Team noch nicht gefunden wurde, deswegen wäre am Ende der zweiten if-Bedingung kein guter Ort
                                    if($rowBegegnung['fk_siegerteam'] == $rowBegegnung['fk_heimteam']){
                                        $gewinnerTeam1ID = $rowBegegnung['fk_heimteam'];
                                        $verliererTeam1ID = $rowBegegnung['fk_auswaertsteam'];
                                    }else if($rowBegegnung['fk_siegerteam'] == $rowBegegnung['fk_auswaertsteam']){
                                        $gewinnerTeam1ID = $rowBegegnung['fk_auswaertsteam'];
                                        $verliererTeam1ID = $rowBegegnung['fk_heimteam'];
                                    }

                                    if($gewinnerTeam1ID != 0){
                                        $fk_heimteam = $rowBegegnung['fk_heimteam'];
                                        $fk_auswaertsteam = $rowBegegnung['fk_auswaertsteam'];
                    
                                        //SONDERFALL: FINALE GEWINNERTEAMS MARKIEREN
                                        if($ko_finallevel==2){ 
                                            //echo "<script>console.log('ko finallevel ist 2')</script>";
                                            if($gewinnerTeam1ID == 0 || $verliererTeam1ID == 0){ //NUr die Endplatzierung vergeben wenn die neuen Nachfolgerteams schon gefunden sind
                                                setTeamPlatziertLevel($conn, $TurnierID, $fk_heimteam, $ko_finallevel);
                                                setTeamPlatziertLevel($conn, $TurnierID, $fk_auswaertsteam, $ko_finallevel);
                                            }else{
                                                setTeamPlatziertLevel($conn, $TurnierID, $gewinnerTeam1ID, $ko_finallevel);
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, $ko_finallevel);
                                            }
                                            continue;
                                        }
                                        //SONDERFALL: SPIEL UM PLATZ 3 GEWINNERTEAM MARKIEREN
                                        if($ko_finallevel==1){ 
                                            //echo "<script>console.log('ko finallevel ist 1')</script>";
                                            if($gewinnerTeam1ID == 0 || $verliererTeam1ID == 0){ //NUr die Endplatzierung vergeben wenn die neuen Nachfolgerteams schon gefunden sind
                                                setTeamPlatziertLevel($conn, $TurnierID, $fk_heimteam, $ko_finallevel);
                                                setTeamPlatziertLevel($conn, $TurnierID, $fk_auswaertsteam, $ko_finallevel);
                                            }else{
                                                setTeamPlatziertLevel($conn, $TurnierID, $gewinnerTeam1ID, $ko_finallevel);
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, $ko_finallevel);
                                            }
                                            continue;
                                        }
                                    }
                                }else if($zaehlerForKoPosition % 2 == 0){ //in den Fällen gerade findet man das zweite Team
                                    //Neue Turnierbaumposition berechnen
                                    $ko_turnierbaumposition_alt = $rowBegegnung['ko_turnierbaumposition'];
                                    $ko_turnierbaumposition = ($ko_turnierbaumposition_alt / 2); //Neue Turnierbaumposition ist die alte der zweiten Begegnung durch 2
                                    if($ko_finallevel_next == 4){ // FALLS GRAD FÜR VIERTELFINALE ERSTELLT WIRD
                                        switch ($ko_turnierbaumposition) {
                                            case 1:
                                                $ko_turnierbaumposition = 1;
                                                break;
                                            case 2:
                                                $ko_turnierbaumposition = 3;
                                                break;
                                            case 3:
                                                $ko_turnierbaumposition = 2;
                                                break;
                                            case 4:
                                                $ko_turnierbaumposition = 4;
                                                break;                         
                                        }
                                    }
                                    //Gewinnerteam herausfinden
                                    if($rowBegegnung['fk_siegerteam'] == $rowBegegnung['fk_heimteam']){
                                        $gewinnerTeam2ID = $rowBegegnung['fk_heimteam'];
                                        $verliererTeam2ID = $rowBegegnung['fk_auswaertsteam'];
                                    }else if($rowBegegnung['fk_siegerteam'] == $rowBegegnung['fk_auswaertsteam']){
                                        $gewinnerTeam2ID = $rowBegegnung['fk_auswaertsteam'];
                                        $verliererTeam2ID = $rowBegegnung['fk_heimteam'];
                                    }
                                   
                                    //nur Begegnung erstellen wenn auch bei vorherigem Durchlauf das erste Team gefunden wurde
                                    if($gewinnerTeam1ID != 0 && $gewinnerTeam2ID != 0){
                                        begegnungErstellen($conn, $gewinnerTeam1ID, $gewinnerTeam2ID, $ko_finallevel_next, $ko_turnierbaumposition);
            
                                        //SONDERFALL: SPIEL UM PLATZ 3
                                        if($ko_finallevel==3){ //3 ist das Halbfinale -> bedeutet dass wir gerade fürs Finale erstellen, und da müssen wir direkt für Spiel um Platz 3 mit erstellen
                                            begegnungErstellen($conn, $verliererTeam1ID, $verliererTeam2ID, $ko_finallevel_next-1, $ko_turnierbaumposition); //Spiel um Platz 3 bekommt die Verlierer des Halbfinales
                                        }
                                        else if($ko_finallevel>3){ //endplatzierung nur vergeben wenn gerade nicht schon fürs FInale und SPiel um Platz 3 erstellt wird, weil alle die im Halbfinale waren eh nochmal spielen werden
                                            if($verliererTeam1ID == 0){ //Nur die Endplatzierung vergeben wenn die neuen Nachfolgerteams schon gefunden sind
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, $ko_finallevel);
                                            }else{
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, $ko_finallevel);
                                            }
                                            
                                            if($verliererTeam2ID == 0){
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam1ID, $ko_finallevel);
                                            } else{
                                                setTeamPlatziertLevel($conn, $TurnierID, $verliererTeam2ID, $ko_finallevel);
                                            }                                    
                                        }
                                    }
                                }
                            }
                            $zaehlerForKoPosition++;
                        }
                        $ko_finallevel--; //Neues KO_Finallevel
                    }
               /*
                                $begegnungsID = $rowBegegnung['id'];
                                $begegnungsStatus = $rowBegegnung['status'];
                                if($rowBegegnung['status'] != '5' && $rowBegegnung['status'] != '4'){ //NOCH NICHT FINAL
                                    $gewinnerTeam1ID = "platzhalter"; //Hier weise ich "platzhalter" zu damit bei nächster Iteration nicht wieder diese if-Bedingung aufgerufen wird. Da wird die ID dann eh zurückgesetz
                                    $verliererTeam1ID = "platzhalter";  //Für Spiel um Platz 3 wichtig
                                    $gewinnerTeam2ID = "platzhalter"; //Hier weise ich "platzhalter" zu damit bei nächster Iteration nicht wieder diese if-Bedingung aufgerufen wird. Da wird die ID dann eh zurückgesetz
                                    $verliererTeam2ID = "platzhalter";  //Für Spiel um Platz 3 wichtig
                                }else{ //BEGEGNUNG FINAL
                                    if($gewinnerTeam1ID == 0 && $gewinnerTeam2ID == 0){
                                        
                                        //ALT: Gewinnerteam & Verliererteam herausfinden
                                            //dazu: Spiele finden
                                            
                                            $FlaschenZaehlerTeam1 = 0;
                                            $PunkteZaehlerTeam1 = 0;
                                            $FlaschenZaehlerTeam2 = 0;
                                            $PunkteZaehlerTeam2 = 0;
                                            $sqlSpiel = 'SELECT * FROM `Spiel` WHERE fk_begegnung = ' . $rowBegegnung["id"] . ' ORDER BY ID';
                                            $resultSpiel = $conn->query($sqlSpiel);	
                                            while ($rowSpiel = $resultSpiel->fetch_assoc()) {
                                                $a=$rowSpiel["biereheimteam"];
                                                $b=$rowSpiel["biereauswaertsteam"];
                                                $FlaschenZaehlerTeam1+=$a;
                                                $FlaschenZaehlerTeam2+=$b;
                                                if($a>$b){
                                                    $PunkteZaehlerTeam1++;
                                                }else if($b>$a){
                                                    $PunkteZaehlerTeam2++;
                                                }else{}
                                            }			
                                            //GewinnerteamID in $gewinnerTeam1ID schreiben
                                            if($PunkteZaehlerTeam1 > $PunkteZaehlerTeam2){
                                                $gewinnerTeam1ID = $rowBegegnung['fk_heimteam'];
                                                $verliererTeam1ID = $rowBegegnung['fk_auswaertsteam']; //Für Spiel um Platz 3 wichtig
                                            }else if($PunkteZaehlerTeam2 > $PunkteZaehlerTeam1){
                                                $gewinnerTeam1ID = $rowBegegnung['fk_auswaertsteam'];
                                                $verliererTeam1ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
                                            }else{ //Fall dass Punkte gleich sind: Es wird nach Flaschen entschieden
                                                if($FlaschenZaehlerTeam1 > $FlaschenZaehlerTeam2){
                                                    $gewinnerTeam1ID = $rowBegegnung['fk_heimteam'];
                                                    $verliererTeam1ID = $rowBegegnung['fk_auswaertsteam'];  //Für Spiel um Platz 3 wichtig
                                                }else if($FlaschenZaehlerTeam2 > $FlaschenZaehlerTeam1){
                                                    $gewinnerTeam1ID = $rowBegegnung['fk_auswaertsteam'];
                                                    $verliererTeam1ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
                                                }else{
                                                    //Nur einen Gewinner bestimmen wenn schon ein Spiel gemacht wurde
                                                    $gewinnerTeam1ID = "platzhalter"; //Hier weise ich "platzhalter" zu damit bei nächster Iteration nicht wieder diese if-Bedingung aufgerufen wird. Da wird die ID dann eh zurückgesetz
                                                    $verliererTeam1ID = "platzhalter";  //Für Spiel um Platz 3 wichtig
                                                }
                                            }
                                            //echo "<script>console.log('gewinnerTeam1ID: $gewinnerTeam1ID')</script>";
                                            
                                    }else if($gewinnerTeam1ID != 0 && $gewinnerTeam2ID == 0){
                                        
                                        //ALT: Gewinnerteam herausfinden
                                            //dazu: Spiele finden
                                        
                                            $FlaschenZaehlerTeam1 = 0;
                                            $PunkteZaehlerTeam1 = 0;
                                            $FlaschenZaehlerTeam2 = 0;
                                            $PunkteZaehlerTeam2 = 0;
                                            $sqlSpiel = 'SELECT * FROM `Spiel` WHERE fk_begegnung = ' . $rowBegegnung["id"] . ' ORDER BY ID';
                                            $resultSpiel = $conn->query($sqlSpiel);	
                                            while ($rowSpiel = $resultSpiel->fetch_assoc()) {
                                                $a=$rowSpiel["biereheimteam"];
                                                $b=$rowSpiel["biereauswaertsteam"];
                                                $FlaschenZaehlerTeam1+=$a;
                                                $FlaschenZaehlerTeam2+=$b;
                                                if($a>$b){
                                                    $PunkteZaehlerTeam1++;
                                                }else if($b>$a){
                                                    $PunkteZaehlerTeam2++;
                                                }else{}
                                            }
                                            //GewinnerteamID in $gewinnerTeam2ID schreiben
                                            if($PunkteZaehlerTeam1 > $PunkteZaehlerTeam2){
                                                $gewinnerTeam2ID = $rowBegegnung['fk_heimteam'];
                                                $verliererTeam2ID = $rowBegegnung['fk_auswaertsteam'];  //Für Spiel um Platz 3 wichtig
                                            }else if($PunkteZaehlerTeam2 > $PunkteZaehlerTeam1){
                                                $gewinnerTeam2ID = $rowBegegnung['fk_auswaertsteam'];
                                                $verliererTeam2ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
                                            }else{ //Fall dass Punkte gleich sind: Es wird nach Flaschen entschieden
                                                if($FlaschenZaehlerTeam1 > $FlaschenZaehlerTeam2){
                                                    $gewinnerTeam2ID = $rowBegegnung['fk_heimteam'];
                                                    $verliererTeam2ID = $rowBegegnung['fk_auswaertsteam'];  //Für Spiel um Platz 3 wichtig
                                                }else if($FlaschenZaehlerTeam2 > $FlaschenZaehlerTeam1){
                                                    $gewinnerTeam2ID = $rowBegegnung['fk_auswaertsteam'];
                                                    $verliererTeam2ID = $rowBegegnung['fk_heimteam'];  //Für Spiel um Platz 3 wichtig
                                                }else{
                                                    //Nur einen Gewinner bestimmen wenn schon ein Spiel gemacht wurde
                                                    $gewinnerTeam2ID = "platzhalter"; //Hier weise ich "platzhalter" zu damit bei nächster Iteration nicht wieder diese if-Bedingung aufgerufen wird. Da wird die ID dann eh zurückgesetz
                                                    $verliererTeam2ID = "platzhalter";  //Für Spiel um Platz 3 wichtig
                                                }
                                            }
                                            //echo "<script>console.log('gewinnerTeam2ID: $gewinnerTeam2ID')</script>";
                                            
                                        
                                        
            
                                        
                                    }
                                }*/ 
                

                
            //UNNÖTGE BEGEGNUNGEN werden als final veraltet markiert - gerade bei Finalspielen wichtig
                //TODO: Darauf achten dass nur unnötige Begegnungen des AKTUELLEN Turniers gelöscht werden
                //als veraltet vormatierte Begegnungen jetzt als final-veraltet markieren
                $stmtFinalVeralteteBegegnung = $conn->prepare('UPDATE Turnier_Begegnung SET `status` = 3 WHERE `status` = 2 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .')');
                if ( $stmtFinalVeralteteBegegnung === false ){
                    throw new Exception('Begegnungen konnten nicht als Final-Veraltet markiert werden.');
                }
                $stmtFinalVeralteteBegegnung->execute();
                
                
                //Siegesquoten ausrechnen und eintragen
                $sqlTeam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID;
                $resultTeam = $conn->query($sqlTeam);
                while (!empty( $rowTeam = $resultTeam->fetch_assoc() ) ){
                    $TeamId = $rowTeam['id'];
                    setSiegesQuote($conn, $TurnierID, $TeamId);
                }
                // LOSING BRACKET (Hook) wurde bereits vor dem finalen Veraltet-Schritt aufgerufen

                //PLATZIERUNGEN
                setAllEndplatzierungen($conn, $TurnierID);

                //TODO:s für php-script was im Hintergrund läuft: Darauf achten dass es keine Kürzel doppelt gibt - ist fürs eintragen wichtig

        }if($turnier_phase_ID == 9){ //Turnier vorbei
            //do nothing
        }else{
            //throw new Exception('Es wurde eine ungültige Turnierphase gewählt.');
            //do nothing
        }
 

    //**ERROR HANDLING ** (try-Block beginnt ganz oben)
        //throw new Exception();

        //TEST_ERROR DER DURCH 1. CATCH-STATEMENT ABGEFANGEN WIRD
        /*$stmtErrorTest = $conn->prepare("UPDATE `ErrorTest` SET `fk_gruppe` = '$abcerror' WHERE `Team`.`id` = '$abcerror';");
        if ( $stmtErrorTest === false ){
            throw new Exception('ErrorTest');
        }
        $stmtErrorTest->execute();*/

        //TEST_ERROR DER DURCH 2. CATCH-STATEMENT ABGEFANGEN WIRD
        //$stmtErrorTest = $conn->prepare("UPDATE `ErrorTest` SET `fk_gruppe` = '$abcerror' WHERE `Team`.`id` = '$abcerror';");
        //$stmtErrorTest->execute();   


        /*} catch (Exception $e) {
            $message = $e->getMessage();
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***$message*** ###</i>";
        }catch (Throwable $e) { //ALLES WAS NICHT SCHON VORHER ABGEFANGEN WIRD
            print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***unbekannter Fehler*** ###</i>";
        }*/
    }
?>

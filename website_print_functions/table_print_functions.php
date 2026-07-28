<?php
    // getRollenFlags() wird hier u.a. in printSchiedsrichterInnen() gebraucht - explizit inkludiert,
    // damit diese Datei nicht von der Include-Reihenfolge in index.php abhängt.
    include_once __DIR__ . '/../database/rollen_definitionen.php';

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

    // $eigenesTeamId: falls gerade ein Team eingeloggt ist, dessen ID - $darfAllePasswoerterSehen:
    // Admin/Co-Admin/Turniermaster/Backstage-Zugang (backstage-Flag, siehe rollen_definitionen.php) -
    // steuert zusammen mit $eigenesTeamId weiter unten, ob der "Passwort anzeigen"-Button auf dieser
    // Teamseite überhaupt gerendert wird (nur fürs eigene Team bzw. mit Backstage-Rechten, siehe Chat).
    function printTeamInfo($TurnierID, $conn, $teamId, $eigenesTeamId = null, $darfAllePasswoerterSehen = false){ //NICHT IM CMS
        // SICHERHEIT: (int)-Cast schliesst SQL-Injection (defensiv - diese Seite ist komplett
        // oeffentlich ohne Login erreichbar, der Aufrufer in index.php castet zwar bereits, aber
        // doppelt gesichert ist besser).
        $teamId = $teamId !== null ? (int)$teamId : null;
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
                $platziertLevel = $row['platziert_level'];
                $gruppenphaseSpiele = $row['gruppenphase_spiele'];
                $gruppenphaseFlaschen = $row['gruppenphase_flaschen'];
                $gruppenphasePunkte = $row['gruppenphase_punkte'];
                $siegesquote = $row['siegesquote'];
                $teamPasswort = $row['password'];
            }
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Teamname/-kuerzel (beides
            // vom Team selbst bei der Anmeldung frei waehlbar).
            $teamNameSafe = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
            $teamKuerzelSafe = htmlspecialchars($teamKuerzel, ENT_QUOTES, 'UTF-8');
            $pokalBadge = ($endplatzierung !== null && (int)$endplatzierung === 1) ? " &#127942;" : "";
            echo "<div class='team-info-header'><h1>$teamNameSafe <span class='team-info-kuerzel'>($teamKuerzelSafe)</span>$pokalBadge</h1></div>";

            // ========================================================================================
            // OFFENE SPIELE: je nachdem ob das Team gerade in der K.-o.-Phase oder noch in der
            // Gruppenphase steckt, wird direkt die passende Tabelle mit angezeigt (eigene Zeile
            // hervorgehoben) - man muss dafür nicht extra zur Gesamtübersicht wechseln. Keine
            // Status-Box hier mehr (die gibt's schon auf der Startseite, wäre hier redundant).
            // ========================================================================================
            // BUGFIX: vorher auch bereits finalisierte Begegnungen (status 5/7) mitgezählt - ein Team,
            // das z.B. das Finale bereits gewonnen hat (keine offenen Spiele mehr), sah hier trotzdem
            // "Eure offenen Spiele: Finale" samt dem längst abgeschlossenen Spiel. Jetzt nur noch
            // wirklich offene/unfertige Begegnungen (status NOT IN 3,5,6,7).
            $resultAktivKoTeaminfo = $conn->query('SELECT * FROM Turnier_Begegnung WHERE status NOT IN (3,5,6,7) AND ko_finallevel > 0 AND ko_finallevel < 20 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY ko_finallevel ASC LIMIT 1');
            $aktivKoRowTeaminfo = $resultAktivKoTeaminfo ? $resultAktivKoTeaminfo->fetch_assoc() : null;
            if ($aktivKoRowTeaminfo) {
                $aktuellesLevelTeaminfo = (int)$aktivKoRowTeaminfo['ko_finallevel'];
                $levelNameTeaminfo = 'Aktuelle Runde';
                $resultLevelNameTeaminfo = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . $aktuellesLevelTeaminfo);
                if ($resultLevelNameTeaminfo && ($rl = $resultLevelNameTeaminfo->fetch_assoc())) { $levelNameTeaminfo = $rl['name']; }
                echo "<h2>Eure offenen Spiele: " . htmlspecialchars($levelNameTeaminfo, ENT_QUOTES, 'UTF-8') . "</h2>";
                echo "<table class='withBorderCollapse'><thead><tr><th>Team A</th><th>Spiele</th><th>Team B</th></tr></thead><tbody><tr>";
                $sqlLevelBegegnungen = 'SELECT * FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = ' . $aktuellesLevelTeaminfo . ' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition';
                $resultLevelBegegnungen = $conn->query($sqlLevelBegegnungen);
                while ($rowLvl = $resultLevelBegegnungen->fetch_assoc()) {
                    $istEigeneZeile = ((int)$rowLvl['fk_heimteam'] === $teamId || (int)$rowLvl['fk_auswaertsteam'] === $teamId);
                    $rowClassTeaminfo = $istEigeneZeile ? " class='own-team-row'" : '';
                    $hKuerzel = $conn->query('SELECT kuerzel FROM Turnier_Team WHERE id = ' . (int)$rowLvl['fk_heimteam'])->fetch_assoc()['kuerzel'] ?? '?';
                    $aKuerzel = $conn->query('SELECT kuerzel FROM Turnier_Team WHERE id = ' . (int)$rowLvl['fk_auswaertsteam'])->fetch_assoc()['kuerzel'] ?? '?';
                    echo "<td$rowClassTeaminfo>" . htmlspecialchars($hKuerzel, ENT_QUOTES, 'UTF-8') . "</td><td$rowClassTeaminfo>";
                    printGames($TurnierID, $conn, (int)$rowLvl['id'], 0, $rowLvl['status']);
                    echo "</td><td$rowClassTeaminfo>" . htmlspecialchars($aKuerzel, ENT_QUOTES, 'UTF-8') . "</td></tr><tr>";
                }
                echo "</tr></tbody></table>";
                echo "<p><a href='#kophase' class='button primary'>Zur K.-o.-Phase</a></p><br/>";
            } elseif ($gruppeId !== null && $platziertLevel === null) {
                $resultGruppenNameTeaminfo = $conn->query('SELECT name FROM Turnier_Gruppe WHERE id = ' . (int)$gruppeId);
                $gruppenNameTeaminfo = ($resultGruppenNameTeaminfo && ($g = $resultGruppenNameTeaminfo->fetch_assoc())) ? $g['name'] : '';
                echo "<h2>Eure offenen Spiele: Gruppe " . htmlspecialchars($gruppenNameTeaminfo, ENT_QUOTES, 'UTF-8') . "</h2>";
                echo "<table class='withBorderCollapse'><thead><tr><th>Team</th><th>Abk.</th><th>Sp.</th><th>Fl.</th><th>Pkt.</th></tr></thead><tbody>";
                $sqlGruppenTeams = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND fk_gruppe = ' . (int)$gruppeId . ' ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
                $resultGruppenTeams = $conn->query($sqlGruppenTeams);
                while ($rowGt = $resultGruppenTeams->fetch_assoc()) {
                    $rowClassTeaminfo = ((int)$rowGt['id'] === $teamId) ? " class='own-team-row'" : '';
                    echo "<tr$rowClassTeaminfo><td>" . htmlspecialchars($rowGt['name'], ENT_QUOTES, 'UTF-8') . "</td><td>";
                    $r = printKuerzelWithLink($conn, $rowGt['id']);
                    echo "$r</td><td>" . (int)$rowGt['gruppenphase_spiele'] . "</td><td>" . (int)$rowGt['gruppenphase_flaschen'] . "</td><td>" . (int)$rowGt['gruppenphase_punkte'] . "</td></tr>";
                }
                echo "</tbody></table>";
                echo "<p><a href='#gruppenphase' class='button primary'>Zur Gruppenphase</a></p><br/>";
            }

            // ========================================================================================
            // FACTS-LEISTE: kompakte Kennzahlen auf einen Blick statt einzelner h2/p-Blöcke.
            // ========================================================================================
            $gruppenNameFacts = 'noch keiner zugeteilt';
            if ($gruppeId != NULL) {
                $resultGruppeFacts = $conn->query('SELECT name FROM Turnier_Gruppe WHERE id = ' . (int)$gruppeId);
                if ($resultGruppeFacts && ($gf = $resultGruppeFacts->fetch_assoc())) { $gruppenNameFacts = $gf['name']; }
            }
            $siegesquoteFacts = ($siegesquote !== NULL) ? round($siegesquote) . ' %' : '-';
            $endplatzierungFacts = ($endplatzierung !== NULL && $endplatzierung !== 0) ? $endplatzierung : '-';
            echo "
            <div class='team-info-stats'>
                <div class='team-info-stat'><span class='team-info-stat-label'>Gruppe</span><span class='team-info-stat-value'>" . htmlspecialchars($gruppenNameFacts, ENT_QUOTES, 'UTF-8') . "</span></div>
                <div class='team-info-stat'><span class='team-info-stat-label'>Gruppenspiele</span><span class='team-info-stat-value'>" . (int)$gruppenphaseSpiele . "</span></div>
                <div class='team-info-stat'><span class='team-info-stat-label'>Flaschen</span><span class='team-info-stat-value'>" . (int)$gruppenphaseFlaschen . "</span></div>
                <div class='team-info-stat'><span class='team-info-stat-label'>Punkte</span><span class='team-info-stat-value'>" . (int)$gruppenphasePunkte . "</span></div>
                <div class='team-info-stat'><span class='team-info-stat-label'>Siegesquote</span><span class='team-info-stat-value'>$siegesquoteFacts</span></div>
                <div class='team-info-stat'><span class='team-info-stat-label'>Endplatzierung</span><span class='team-info-stat-value'>$endplatzierungFacts</span></div>
            </div>
            ";

            // Zertifikat prominent als eigene CTA-Box, weiter oben auf der Seite statt ganz unten.
            echo "
            <div class='cta-banner' style='border-color: rgba(251,146,60,0.35); background: linear-gradient(180deg, rgba(251,146,60,0.14), rgba(251,146,60,0.03));'>
                <div class='title'>&#127942; Euer Teamzertifikat</div>
                <div class='desc'>Zum Ausdrucken und Aufhängen - mit eurem Namen, Kürzel und eurem Abschneiden im Turnier.</div>
                <a href='/website_functionalities/generate_team_certificate/generate_team_certificate.php?teamId=$teamId&turnierId=$TurnierID' class='button primary'>Teamzertifikat zum Drucken</a>
            </div>
            ";

            // ========================================================================================
            // TEAM-PASSWORT ANZEIGEN - auf ausdrücklichen Wunsch NUR hier auf der eigenen Teamseite
            // (fürs eingeloggte Team selbst) bzw. für Admin/Co-Admin/Turniermaster/Backstage-Zugang auf
            // JEDER Teamseite. Alle anderen (andere eingeloggte Teams, nicht eingeloggt) sehen den
            // Button gar nicht erst - kein Hinweis, dass es ihn überhaupt gibt. Passwort selbst bleibt
            // wie im Team-Passwörter-Backstage-Menü standardmäßig maskiert, erst per Klick sichtbar.
            // ========================================================================================
            $darfDiesesPasswortSehen = $darfAllePasswoerterSehen || ($eigenesTeamId !== null && (int)$eigenesTeamId === $teamId);
            if ($darfDiesesPasswortSehen) {
                $teamPasswortSafe = htmlspecialchars($teamPasswort, ENT_QUOTES, 'UTF-8');
                echo "
                <div class='cta-banner'>
                    <div class='title'>Team-Passwort</div>
                    <div class='desc'>Passwort: <span class='pw-mask' id='pw-mask-team-$teamId'>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span><span class='pw-value' id='pw-value-team-$teamId' hidden>$teamPasswortSafe</span></div>
                    <button type='button' class='button small' onclick=\"var m=document.getElementById('pw-mask-team-$teamId'),v=document.getElementById('pw-value-team-$teamId'),zeigen=v.hidden;v.hidden=!zeigen;m.hidden=zeigen;this.textContent=zeigen?'verbergen':'anzeigen';\">anzeigen</button>
                </div>
                ";
            }

            //TEAMMITGLIEDER RAUSFINDEN
            echo "<h2>Team</h2>";
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

            echo "<h2>Alle Spiele</h2>";
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
                
                //Ausgeben - SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Teamnamen
                echo "<td>" . htmlspecialchars($heimteam, ENT_QUOTES, 'UTF-8') . " ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td>"; //Heimteam kommt ganz links hin

                //Spiele zu den Begegnungen finden
                $status = $row['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                printGames($TurnierID, $conn, $begegnungId, 0, $status);

                echo "</td><td>" . htmlspecialchars($auswaertsteam, ENT_QUOTES, 'UTF-8') . " ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td></tr><tr>"; //Auswärtsteam kommt ganz rechts hin
                $zaehler++;
            }

            echo"   </tr>
                </tbody>
            </table>";
            echo "<br/>";
        }

    }


    // ================================================================================================
    // GESAMTTURNIER-STATUS: welche der drei Phasen (Gruppenphase/K.-o.-Phase/Losing Bracket) läuft
    // gerade? Auf ausdrücklichen Wunsch für die Spielplan-Übersicht, damit auf einen Blick klar wird,
    // wo das Turnier insgesamt gerade steht - unabhängig vom eingeloggten Team. Jede Phase bekommt einen
    // von drei Zuständen: 'nicht_gestartet' (noch keine Begegnungen), 'aktiv' (mindestens eine offene
    // Begegnung) oder 'abgeschlossen' (alle Begegnungen finalisiert). Bewusst rein lesend, exakt
    // dieselben Status-Codes wie überall sonst (status <> 3 = nicht veraltet, status IN (5,7) = final).
    // Mehrere Phasen können gleichzeitig 'aktiv' sein (z.B. Gruppenphase läuft noch aus, K.-o.-Phase hat
    // schon begonnen).
    //
    // BUGFIX: wenn die Turnierphase (Backstage > Turnierphase-Schalter) auf 9 ("Turnier ist
    // abgeschlossen") gestellt ist, zeigten trotzdem einzelne Karten (v.a. Losing-Bracket, das oft gar
    // nicht für jedes Team offen ist) noch "Läuft gerade", weil ihr Status rein aus den Begegnungsdaten
    // abgeleitet wurde. Der explizite "abgeschlossen"-Schalter ist die Aussage der Turnierleitung und
    // muss daher Vorrang vor der reinen Datenlage haben - siehe Chat.
    // ================================================================================================
    function ermittlePhasenStatus($conn, $TurnierID, $turnier_phase_ID = null) {
        if ((int)$turnier_phase_ID === 9) {
            return ['gruppenphase' => 'abgeschlossen', 'kophase' => 'abgeschlossen', 'losingbracket' => 'abgeschlossen'];
        }
        $TurnierID = (int)$TurnierID;
        $teamsFilter = 'fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ')';
        $ermittleEinzelstatus = function($levelBedingung) use ($conn, $teamsFilter) {
            $gesamt = (int)$conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ' . $levelBedingung . ' AND status <> 3 AND ' . $teamsFilter)->fetch_assoc()['anzahl'];
            if ($gesamt === 0) { return 'nicht_gestartet'; }
            $offen = (int)$conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ' . $levelBedingung . ' AND status NOT IN (3,5,6,7) AND ' . $teamsFilter)->fetch_assoc()['anzahl'];
            return ($offen > 0) ? 'aktiv' : 'abgeschlossen';
        };
        return [
            'gruppenphase' => $ermittleEinzelstatus('ko_finallevel = 0'),
            'kophase' => $ermittleEinzelstatus('ko_finallevel > 0 AND ko_finallevel < 20'),
            'losingbracket' => $ermittleEinzelstatus('ko_finallevel = 20'),
        ];
    }

    // ================================================================================================
    // TEAM-STATUS: Wo steht ein Team gerade im Turnier? (Gruppenphase/K.-o.-Phase/Losing Bracket/Sieg)
    // ================================================================================================
    // Reine SELECTs auf denselben Feldern, die db_update.php für die automatische Turnierberechnung
    // pflegt (platziert_level, endplatzierung, ko_finallevel, losingbracket_open_for_ko_losers) -
    // hier wird nichts geschrieben. Gibt ein Array mit 'titel'/'text'/'cta_label'/'cta_href'/'stil'
    // zurück ('stil' steuert die Farbe der Box in printTeamStatusBox(): 'default'/'win'/'eliminated').
    function getTeamStatusInfo($conn, $TurnierID, $teamId, $turnier_phase_ID) {
        $teamId = (int)$teamId;
        $TurnierID = (int)$TurnierID;

        if (!in_array((int)$turnier_phase_ID, [7, 11, 13], true)) {
            return [
                'titel' => 'Noch nicht losgegangen',
                'text' => 'Das Turnier läuft noch nicht. Sobald es startet, seht ihr hier eure Spiele und euren aktuellen Stand.',
                'cta_label' => null, 'cta_href' => null, 'stil' => 'default',
            ];
        }

        $resultTeam = $conn->query('SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $teamId);
        $rowTeam = $resultTeam ? $resultTeam->fetch_assoc() : null;
        if (!$rowTeam) {
            return ['titel' => '', 'text' => '', 'cta_label' => null, 'cta_href' => null, 'stil' => 'default'];
        }
        $platziertLevel = $rowTeam['platziert_level'];
        $endplatzierung = $rowTeam['endplatzierung'];
        $fkGruppe = $rowTeam['fk_gruppe'];

        // Turniersieg hat Vorrang vor allem anderen
        if ($endplatzierung !== null && (int)$endplatzierung === 1) {
            return [
                'titel' => '🏆 Herzlichen Glückwunsch!',
                'text' => 'Euer Team hat das Turnier gewonnen!',
                'cta_label' => 'Zum Turnierbaum', 'cta_href' => '#turnierbaum', 'stil' => 'win',
            ];
        }

        // BUGFIX: Sieger*in des "Spiel um Platz 3" bekommt in db_update.php genau wie der/die
        // Verlierer*in `platziert_level = 1` gesetzt (beide sind mit der K.-o.-Phase fertig, egal wie
        // das Spiel ausgeht) - ohne diesen Extra-Check würde weiter unten fälschlich "ausgeschieden"
        // stehen, obwohl das Team dieses Spiel gewonnen und den 3. Platz belegt hat. Unterscheidung
        // erfolgt über `endplatzierung` (3 = gewonnen, 4 = verloren), nicht über platziert_level allein.
        if ($endplatzierung !== null && (int)$endplatzierung === 3) {
            return [
                'titel' => '🥉 Spiel um Platz 3 gewonnen!',
                'text' => 'Herzlichen Glückwunsch, ihr habt den 3. Platz belegt!',
                'cta_label' => 'Zur Rangliste', 'cta_href' => '#rangliste', 'stil' => 'win',
            ];
        }

        // Weitesten erreichte Begegnung in der regulären K.-o.-Phase (ko_finallevel 1-19)? Niedrigstes
        // Level = am weitesten fortgeschrittene Runde (höhere Zahl = frühere Runde, siehe
        // Turnier_KO_Finallevel) - status NOT IN (3,6) schließt nur veraltete/gesperrte Begegnungen
        // aus, damit sowohl offene ALS AUCH bereits gewonnene (finalisierte) Begegnungen zählen.
        // BUGFIX: vorher wurde nach höchstem Level sortiert und dabei auch längst finalisierte
        // Begegnungen früherer (bereits gewonnener) Runden mitgezählt - eine gerade erst gewonnene
        // Halbfinal-Begegnung wurde so von einer alten, finalisierten Achtelfinal-Begegnung
        // "überdeckt" und fälschlich als aktuelle Runde angezeigt.
        $resultAktivKo = $conn->query('SELECT * FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel > 0 AND ko_finallevel < 20 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY ko_finallevel ASC LIMIT 1');
        $rowAktivKo = $resultAktivKo ? $resultAktivKo->fetch_assoc() : null;

        if ($platziertLevel === null) {
            if ($rowAktivKo) {
                $aktuellesLevel = (int)$rowAktivKo['ko_finallevel'];
                $aktuellerStatus = (string)$rowAktivKo['status'];
                $levelName = 'der aktuellen Runde';
                $resultLevelName = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . $aktuellesLevel);
                if ($resultLevelName && ($rl = $resultLevelName->fetch_assoc())) { $levelName = $rl['name']; }
                // Schon finalisiert (5/7) und trotzdem die am weitesten fortgeschrittene Begegnung ->
                // die Runde ist gewonnen, die nächste Begegnung wurde nur noch nicht erzeugt (wartet
                // z.B. auf das Ergebnis der Parallel-Begegnung, aus der der/die nächste Gegner*in kommt).
                if ($aktuellerStatus === '5' || $aktuellerStatus === '7') {
                    return [
                        'titel' => "Runde \"$levelName\" gewonnen!",
                        'text' => 'Weiter geht es, sobald die nächste Begegnung feststeht - schaut bald wieder vorbei.',
                        'cta_label' => 'Zur K.-o.-Phase', 'cta_href' => '#kophase', 'stil' => 'default',
                    ];
                }
                $begegnungId = (int)$rowAktivKo['id'];
                $anzahlSpiele = (int)$conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Spiel WHERE fk_begegnung = ' . $begegnungId)->fetch_assoc()['anzahl'];
                return [
                    'titel' => "Ihr seid in Runde \"$levelName\"",
                    'text' => "Bisher habt ihr in dieser Begegnung $anzahlSpiele Spiel(e) gespielt.",
                    'cta_label' => 'Zur K.-o.-Phase', 'cta_href' => '#kophase', 'stil' => 'default',
                ];
            }
            if ($fkGruppe !== null) {
                $gesamt = (int)$conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 0 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ')')->fetch_assoc()['anzahl'];
                $fertig = (int)$conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE status IN (5,7) AND ko_finallevel = 0 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ')')->fetch_assoc()['anzahl'];
                $offen = max(0, $gesamt - $fertig);
                return [
                    'titel' => 'Ihr seid in der Gruppenphase',
                    'text' => "Ihr habt $fertig von $gesamt Spielen gespielt" . ($offen > 0 ? ", noch $offen offen." : " - alle gespielt!"),
                    'cta_label' => 'Zur Gruppenphase', 'cta_href' => '#gruppenphase', 'stil' => 'default',
                ];
            }
            return [
                'titel' => 'Noch keiner Gruppe zugeteilt',
                'text' => 'Sobald die Gruppenphase beginnt, seht ihr hier eure Spiele.',
                'cta_label' => null, 'cta_href' => null, 'stil' => 'default',
            ];
        }

        // Ab hier: platziert_level gesetzt (0 = Gruppenphase nicht geschafft, >0 = in dieser
        // K.-o.-Runde verloren) -> Team ist ausgeschieden, evtl. aber im Losing Bracket weiter dabei.
        if ((int)$platziertLevel > 0) {
            $levelName = 'einer K.-o.-Runde';
            $resultLevelName = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . (int)$platziertLevel);
            if ($resultLevelName && ($rl = $resultLevelName->fetch_assoc())) { $levelName = '"' . $rl['name'] . '"'; }
            $ausgeschiedenText = "Schade, ihr seid in der Runde $levelName ausgeschieden.";
        } else {
            $ausgeschiedenText = "Schade, eure Gruppenphase ist beendet - es hat leider nicht für die K.-o.-Phase gereicht.";
        }

        $resultAktivLb = $conn->query('SELECT id FROM Turnier_Begegnung WHERE status NOT IN (3,6) AND ko_finallevel = 20 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') LIMIT 1');
        $hatAktivesLb = $resultAktivLb && $resultAktivLb->fetch_assoc();
        if ($hatAktivesLb) {
            return [
                'titel' => 'Ausgeschieden - aber weiter im Losing Bracket!',
                'text' => $ausgeschiedenText . ' Ihr spielt aber im Losing Bracket weiter - viel Erfolg!',
                'cta_label' => 'Zum Losing Bracket', 'cta_href' => '#losingbracket', 'stil' => 'default',
            ];
        }

        $lbOffenRow = $conn->query('SELECT losingbracket_open_for_ko_losers FROM Turnier_Main WHERE id = ' . $TurnierID)->fetch_assoc();
        $lbOffen = $lbOffenRow ? (int)$lbOffenRow['losingbracket_open_for_ko_losers'] : 0;
        if ($lbOffen === 1 && (int)$platziertLevel === 0) {
            // Gruppenphase-Ausscheiden, Losing Bracket grundsätzlich offen, aber (noch) keine eigene
            // Begegnung für dieses Team dort (z.B. weil es gerade erst erzeugt wird).
            return [
                'titel' => 'Ausgeschieden',
                'text' => $ausgeschiedenText . ' Das Losing Bracket startet in Kürze - schaut bald wieder vorbei.',
                'cta_label' => null, 'cta_href' => null, 'stil' => 'eliminated',
            ];
        }

        $platzText = ($endplatzierung !== null) ? " Eure Endplatzierung: $endplatzierung." : '';
        return [
            'titel' => 'Ausgeschieden',
            'text' => $ausgeschiedenText . $platzText,
            'cta_label' => 'Zur Rangliste', 'cta_href' => '#rangliste', 'stil' => 'eliminated',
        ];
    }

    function printTeamStatusBox($conn, $TurnierID, $teamId, $turnier_phase_ID) {
        $info = getTeamStatusInfo($conn, $TurnierID, $teamId, $turnier_phase_ID);
        if ($info['titel'] === '') { return; }
        $stilKlasse = '';
        if ($info['stil'] === 'win') { $stilKlasse = ' team-status-box--win'; }
        else if ($info['stil'] === 'eliminated') { $stilKlasse = ' team-status-box--eliminated'; }
        echo "<div class='team-status-box$stilKlasse'>";
        echo "<h4>" . htmlspecialchars($info['titel'], ENT_QUOTES, 'UTF-8') . "</h4>";
        echo "<p>" . htmlspecialchars($info['text'], ENT_QUOTES, 'UTF-8') . "</p>";
        if ($info['cta_label'] !== null) {
            echo "<a href='" . htmlspecialchars($info['cta_href'], ENT_QUOTES, 'UTF-8') . "' class='button primary'>" . htmlspecialchars($info['cta_label'], ENT_QUOTES, 'UTF-8') . "</a>";
        }
        echo "</div>";
    }

    // ================================================================================================
    // SPIELPLAN-ÜBERSICHT: die drei Phase-Karten (Gruppenphase/K.-o.-Phase/Losing-Bracket) inkl.
    // Gesamtturnier-Status-Badge (siehe ermittlePhasenStatus()) und, falls ein Team eingeloggt ist,
    // einem "Ihr seid hier"-Pfeil auf genau der Karte, die zu dessen aktuellem Status passt (abgeleitet
    // aus demselben cta_href, das auch die Team-Status-Box auf der Startseite verlinkt - beide Stellen
    // bleiben dadurch automatisch konsistent). Auf ausdrücklichen Wunsch, siehe Chat.
    // ================================================================================================
    function printSpielplanPhaseKarten($conn, $TurnierID, $eigenesTeamId, $turnier_phase_ID) {
        $phasenStatus = ermittlePhasenStatus($conn, $TurnierID, $turnier_phase_ID);
        $statusLabel = ['nicht_gestartet' => 'Startet noch', 'aktiv' => 'Läuft gerade', 'abgeschlossen' => 'Abgeschlossen'];

        $eigenePhaseKey = null;
        if ($eigenesTeamId) {
            $eigenerStatus = getTeamStatusInfo($conn, $TurnierID, (int)$eigenesTeamId, $turnier_phase_ID);
            $hrefZuKey = ['#gruppenphase' => 'gruppenphase', '#kophase' => 'kophase', '#losingbracket' => 'losingbracket'];
            $eigenePhaseKey = $hrefZuKey[$eigenerStatus['cta_href']] ?? null;
        }

        $karten = [
            ['key' => 'gruppenphase', 'klasse' => 'phase-card--gruppen', 'icon' => 'images/icon/sterni1.png',
             'titel' => 'Gruppenphase', 'text' => 'Alle Teams werden in Gruppen eingeteilt und spielen dort im Modus Jede*r gegen Jede*n. Die besten Teams jeder Gruppe ziehen in die KO-Phase ein.',
             'href' => '#gruppenphase', 'label' => 'Zur Gruppenphase'],
            ['key' => 'kophase', 'klasse' => 'phase-card--ko', 'icon' => 'images/icon/sterni2.png',
             'titel' => 'KO-Phase', 'text' => 'In der KO-Phase entscheidet jedes Spiel: Sieg bedeutet Weiterkommen - eine Niederlage das Ausscheiden. Verfolge den Weg durch den Turnierbaum.',
             'href' => '#kophase', 'label' => 'Zur KO-Phase'],
            ['key' => 'losingbracket', 'klasse' => 'phase-card--losing', 'icon' => 'images/icon/logo_export_icon/transparent/favicon-96x96.png',
             'titel' => 'Losing-Bracket', 'text' => 'Im Losing-Bracket geht es für ausgeschiedene Teams weiter - mit Chancen auf eine bessere Endplatzierung und zusätzliche Matches.',
             'href' => '#losingbracket', 'label' => 'Zum Losing-Bracket'],
        ];

        echo "<div class='phase-cards'>";
        foreach ($karten as $karte) {
            $status = $phasenStatus[$karte['key']];
            $istEigenePhase = ($karte['key'] === $eigenePhaseKey);
            $kartenKlasse = 'phase-card ' . $karte['klasse'] . ($istEigenePhase ? ' phase-card--eigene-phase' : '');
            $badge = "<span class='phase-status-badge phase-status-badge--$status'>" . htmlspecialchars($statusLabel[$status], ENT_QUOTES, 'UTF-8') . "</span>";
            $pfeil = $istEigenePhase ? "<div class='phase-card-pfeil'>&#128071; Ihr seid hier</div>" : '';
            echo "<div class='$kartenKlasse'>
                $pfeil
                <h3><img class='icon' src='{$karte['icon']}' alt='Icon'> {$karte['titel']}</h3>
                $badge
                <p class='muted'>{$karte['text']}</p>
                <a href='{$karte['href']}' class='button primary'>{$karte['label']}</a>
            </div>";
        }
        echo "</div>";
    }

    // Wandelt eine (vom Team frei eingegebene) Telefonnummer in einen WhatsApp-Click-to-Chat-Link um -
    // WhatsApp braucht dafür die Nummer in E.164 ohne "+"/Leerzeichen/Bindestriche. Nimmt bei einer
    // führenden 0 an, dass es eine deutsche Nummer ist (0170... -> 49170...), da das Turnier in Berlin
    // stattfindet - internationale Nummern, die schon mit Landesvorwahl eingegeben wurden, bleiben
    // unangetastet. Gibt null zurück, wenn nach dem Bereinigen nichts Sinnvolles übrig bleibt.
    function telefonnummerZuWhatsappLink($telefonnummer) {
        $ziffern = preg_replace('/[^0-9]/', '', (string)$telefonnummer);
        if ($ziffern === '') { return null; }
        if (substr($ziffern, 0, 1) === '0') {
            $ziffern = '49' . substr($ziffern, 1);
        }
        return 'https://wa.me/' . $ziffern;
    }

    // RECHTE-AUDIT (siehe Chat): vorher musste man sich HIER noch einmal separat einloggen, obwohl man
    // z.B. als Admin/Co-Admin/Turniermaster/Backstage-Zugang schon ganz normal eingeloggt war - dieses
    // doppelte Login-Formular ist jetzt komplett weg. $bn/$pw kommen von außen (index.php löst das
    // session-basiert auf, gleiches Muster wie überall sonst auf der Website) statt aus einem eigenen
    // $_POST-Formular hier. Ohne ausreichende Rechte (backstage-Flag) gibt es jetzt eine klare
    // Fehlermeldung statt eines erneuten Login-Versuchs.
    function printSpielerInfo($TurnierID, $conn, $spielerId, $bn = '', $pw = ''){ //NICHT IM CMS
        include_once __DIR__ . '/../website_datachange/login_interface.php';
        $rollenInfoSpielerinfo = getUserRollenInfo($conn, $bn, $pw);
        $darfSpielerinfoSehen = ($rollenInfoSpielerinfo !== null && $rollenInfoSpielerinfo['flags']['backstage']);

        if ($spielerId !== null && $darfSpielerinfoSehen) {
            $sql = 'SELECT * FROM Turnier_Spieler_in WHERE id = ' . $spielerId . ' ORDER BY id';
            $result = $conn->query($sql);
            $spielerName = " ";
            while (!empty($row = $result->fetch_assoc())) {
                $spielerName = $row['name'];
                $tel = $row['telefonnummer'];
                $fk_team = $row['fk_team'];
            }
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS - Spielername/Teamname/Telefon-
            // nummer sind alles bei der Team-Anmeldung frei waehlbare Felder.
            echo "<h1>" . htmlspecialchars($spielerName, ENT_QUOTES, 'UTF-8') . "</h1>";
            //TEAM RAUSFINDEN
            $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $fk_team . ' ORDER BY id';
            $result = $conn->query($sql);
            $teamName = " ";
            while (!empty($row = $result->fetch_assoc())) {
                $teamName = $row['name'];
                $teamId = $row['id'];
            }
            $teamKuerzel = printKuerzelWithLink($conn, $teamId);
            echo "Team: <b>" . htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') . " ($teamKuerzel)</b>";
            echo "<br/>";

            if($tel != NULL && $tel != "" && $tel != " "){
                $telSicher = htmlspecialchars($tel, ENT_QUOTES, 'UTF-8');
                $whatsappLink = telefonnummerZuWhatsappLink($tel);
                echo "Telefonnummer: <b>$telSicher</b>";
                if ($whatsappLink !== null) {
                    echo " <a href='" . htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8') . "' target='_blank' rel='noopener' class='whatsapp-link' title='WhatsApp-Chat mit dieser Nummer öffnen'>&#128172; WhatsApp öffnen</a>";
                }
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
            echo "<h1>Keine ausreichende Berechtigung</h1>";
            echo "<h2>Um Infos zu einzelnen Spieler*innen (z.B. Telefonnummer) zu sehen, musst du als Admin, Co-Admin, Turniermaster oder Backstage-Zugang eingeloggt sein.</h2>";
            echo "<a href='#login' class='button primary'>Oben einloggen</a>";
        }
    }

    function printTeams($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY ID'; //WHERE Freischaltung = 1
        $resultTeam = $conn->query($sqlTeam);
        $zeahler = 1;
        
        while ($rowTeam = $resultTeam->fetch_assoc()) {
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS - $a ist der (bei der
            // Anmeldung frei waehlbare) Teamname und wird auf der oeffentlichen Teams-Uebersicht
            // fuer jeden Besuch ausgegeben.
            $a=htmlspecialchars($rowTeam["name"], ENT_QUOTES, 'UTF-8');
            $name_link=$rowTeam["name_link"];
            if($name_link != NULL){
                $a=htmlspecialchars($name_link, ENT_QUOTES, 'UTF-8');
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
        // Admin/Co-Admin haben dieses Flag ohnehin gesetzt und tauchen daher automatisch mit auf,
        // ohne dass hier nach Rollen-IDs gefragt werden muss. Das Flag kommt jetzt aus
        // getRollenFlags() (rollen_definitionen.php) statt aus einer DB-Spalte, deshalb wird hier in
        // PHP statt per SQL-WHERE gefiltert.
        try {
            $sql = "SELECT DISTINCT sb.Benutzername, rel.fk_rolle FROM System_Benutzer_in sb
                    JOIN System_Benutzer_in_Relation_Rolle rel ON rel.fk_benutzer_in = sb.id
                    ORDER BY sb.Benutzername ASC";
            $result = $conn->query($sql);
            $bereitsAusgegeben = [];
            while ($row = $result->fetch_assoc()) {
                $Benutzername = $row['Benutzername'];
                if (isset($bereitsAusgegeben[$Benutzername])) { continue; }
                $flags = getRollenFlags((int)$row['fk_rolle']);
                if (!empty($flags['rechte_alle_spiele'])) {
                    echo "<li>" . htmlspecialchars($Benutzername) . "</li>";
                    $bereitsAusgegeben[$Benutzername] = true;
                }
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

    // Kompletter Neubau (vorher: reine Lese-Ansicht ohne jede Bearbeitungsmöglichkeit) - klassischer
    // Turnierbaum wie bei NFL/ESPN/Challonge: Runden als gleich breite Spalten nebeneinander, echte
    // Klammerlinien zwischen den Spielen, horizontal UND vertikal scrollbar. Volle Funktionsparität zur
    // K.-o.-Tabelle (Login, +/✓/★-Buttons, Sperren-Button, Begegnungs-ID, Green-Card-Punkt, Legende) -
    // dafür werden dieselben geteilten Bausteine genutzt wie printKO_PhaseTabellen (printEditModeStuff,
    // printBegegnungSperrenUI, ermittleBegegnungsDaten, printGames), damit beide Ansichten nie
    // auseinanderlaufen können. Siehe Chat ("alle Funktionalitäten die's auch in der KO Tabelle gibt").
    function printTurnierbaum($TurnierID, $conn, $istBackstageEingeloggt, $gameEditMode, $expertenmodus, $test_turnier_id, $bnEingeloggt = '', $pwEingeloggt = '', $darfZufaelligeSpieleEintragen = false, $darfAlleSpieleBearbeiten = false, $eigenesTeamId = null, $darfTeamsBearbeiten = false, $darfTurnierSettingsAendern = false){
        $rowMain = $conn->query('SELECT start_ko_finallevel FROM Turnier_Main WHERE id = ' . (int)$TurnierID)->fetch_assoc();
        $start_ko_finallevel = (int)($rowMain['start_ko_finallevel'] ?? 0);
        if ($start_ko_finallevel < 2) {
            echo "<div class='note'>Der Turnierbaum ist noch nicht konfiguriert.</div>";
            return;
        }

        //Button, mit dem man den Bearbeitungsmodus starten kann (identisch zur K.-o.-Tabelle, eigener
        //Rücksprung-Anker "#turnierbaum" statt "#kophase")
        printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#turnierbaum", $test_turnier_id, $darfAlleSpieleBearbeiten, $darfTeamsBearbeiten);
        printBegegnungSperrenUI($darfTeamsBearbeiten, $bnEingeloggt, $pwEingeloggt);

        if ($test_turnier_id != 0 && $darfZufaelligeSpieleEintragen) {
            echo "<p><a href='?test_turnier_id=$test_turnier_id&zufall_scope=ko#backstage_zufaellige_spiele' class='tbl-action-btn tbl-action-btn--testmodus'>Zufällige Spiele eintragen</a></p>";
        }

        echo "
        <style>
            :root {
                --bracket-bg: #0d1626;
                --bracket-card: linear-gradient(135deg, #0f1e34, #142740);
                --bracket-line: #3a6ea8;
                --bracket-accent: #00c2ff;
            }
            .bracket-shell {
                background: radial-gradient(circle at 15% 20%, rgba(0, 194, 255, 0.12), transparent 32%), radial-gradient(circle at 85% 0%, rgba(255, 117, 88, 0.08), transparent 26%), var(--bracket-bg);
                border: 1px solid rgba(255,255,255,0.06);
                border-radius: 16px;
                padding: clamp(0.8rem, 2vw, 1.5rem);
                margin: 0.5rem 0 2rem;
                box-shadow: 0 24px 80px rgba(5, 12, 26, 0.45);
            }
            /* cursor:grab/grabbing signalisiert, dass sich der Baum per Maus ziehen laesst (siehe
               Drag-to-Scroll-Skript weiter unten) - rein optisch, faellt bei Touch-Bedienung einfach
               weg, dort scrollt man ohnehin ganz normal per Fingergeste. */
            .bracket-scroll { overflow: auto; padding-bottom: 0.5rem; cursor: grab; }
            .bracket-scroll.bracket-scroll--grabbing { cursor: grabbing; user-select: none; }
            .bracket-tree { position: relative; display: inline-flex; align-items: flex-start; gap: clamp(1.6rem, 4vw, 3rem); padding: 0 1.2rem 0.5rem 0.3rem; min-width: max-content; }
            .bracket-round { display: flex; flex-direction: column; width: clamp(170px, 20vw, 220px); flex: 0 0 auto; }
            /* Auffällig UND beim vertikalen Scrollen fixiert (position:sticky relativ zu .bracket-scroll),
               damit bei einer langen Runde (z.B. Achtelfinale mit 8 Spielen) immer klar bleibt, welche
               Runde man gerade sieht - siehe Chat (sollen deutlich mehr ins Auge rutschen und dableiben). */
            .bracket-round-title { position: sticky; top: 0; z-index: 8; text-align: center; text-transform: uppercase; letter-spacing: 0.06em; font-size: 1rem; font-weight: 800; color: #ffffff; background: linear-gradient(180deg, #101c32 72%, rgba(16,28,50,0)); padding: 0.55rem 0.3rem 1rem; margin: 0 0 0.2rem; border-bottom: 2px solid var(--bracket-accent); white-space: nowrap; }
            .bracket-round-matches { position: relative; }
            .bracket-match { position: relative; background: var(--bracket-card); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 0.5rem 0.6rem 0.4rem; margin: 0 0 0.9rem; box-shadow: 0 8px 24px rgba(10, 18, 36, 0.35); color: #eaf1ff; }
            .bracket-match.placeholder { opacity: 0.55; font-style: italic; }
            .bracket-match--anomalie { border-color: rgba(245, 158, 11, 0.6); box-shadow: 0 8px 24px rgba(10, 18, 36, 0.35), 0 0 0 1px rgba(245, 158, 11, 0.5); }
            .bracket-anomalie-badge { color: #f59e0b; cursor: help; }
            .bracket-match-meta { font-size: 0.62rem; opacity: 0.8; margin-bottom: 0.3rem; min-height: 1.1em; display: flex; align-items: center; flex-wrap: wrap; gap: 0.15rem; }
            .bracket-team { display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; padding: 0.22rem 0.4rem; border-radius: 6px; font-weight: 600; font-size: 0.86rem; }
            .bracket-team span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .bracket-team + .bracket-team { margin-top: 0.2rem; }
            .bracket-team--winner { background: rgba(22, 163, 74, 0.22); color: #e9fff2; }
            .bracket-team--own { box-shadow: inset 0 0 0 2px var(--team-highlight, #fb923c); }
            .bracket-games { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; justify-content: center; margin-top: 0.35rem; padding-top: 0.35rem; border-top: 1px dashed rgba(255,255,255,0.1); }
            .bracket-champion { display: flex; flex-direction: column; align-items: center; justify-content: center; align-self: center; width: clamp(170px, 20vw, 220px); flex: 0 0 auto; text-align: center; }
            .bracket-champion-card { background: linear-gradient(135deg, #12436d, #0e8aa8); border: 1px solid rgba(0, 194, 255, 0.4); border-radius: 12px; padding: 1rem 0.8rem; color: #e9fbff; font-weight: 700; box-shadow: 0 10px 30px rgba(0, 194, 255, 0.2); }
            /* Spiel um Platz 3: liegt als Extra-Karte in der Finale-Spalte, wird per JS mit Abstand unter
               die Finale-Karte gesetzt (siehe layoutBracket()) - gestrichelter oberer Rahmen statt
               Klammerlinie, weil sie NICHT Teil der eigentlichen Sieger-Kette ist. */
            .bracket-platz3-card { border-top: 2px dashed var(--bracket-line); padding-top: 0.9rem; }
            .bracket-platz3-label { text-align: center; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; margin-bottom: 0.4rem; }
            .bracket-lines { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; overflow: visible; }
            .bracket-lines path { fill: none; stroke: var(--bracket-line); stroke-width: 2; }
            .bracket-lines path.bracket-line-anomalie { stroke: #f59e0b; stroke-width: 2.5; stroke-dasharray: 5 3; }
            @media (max-width: 640px) {
                .bracket-shell { margin: 0.5rem -0.35rem 1.5rem; border-radius: 12px; }
                .bracket-round { width: 155px; }
                .bracket-team { font-size: 0.8rem; }
            }
        </style>
        <div class='bracket-shell'>
            <div class='bracket-scroll'>
                <div class='bracket-tree' id='bracketTree'>
        ";

        $statusFilterBaum = $istBackstageEingeloggt ? '`status` <> 3' : '`status` NOT IN (3, 6)';

        // Spiel um Platz 3 vorab laden - wird weiter unten NICHT als eigene Spalte, sondern INNERHALB
        // der Finale-Spalte (mit Abstand darunter) eingehängt, siehe Chat ("kann gerne einmal unterm
        // Finale sein... nicht direkt drunter, sondern mit 'n bisschen Abstand").
        $bdP3 = null;
        $rundenNameP3 = 'Spiel um Platz 3';
        $rowPlatz3 = $conn->query('SELECT * FROM `Turnier_Begegnung` WHERE ' . $statusFilterBaum . ' AND ko_finallevel = 1 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY id DESC LIMIT 1')->fetch_assoc();
        if ($rowPlatz3) {
            $rowRundeP3 = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = 1')->fetch_assoc();
            $rundenNameP3 = htmlspecialchars($rowRundeP3['name'] ?? 'Spiel um Platz 3', ENT_QUOTES, 'UTF-8');
            $bdP3 = ermittleBegegnungsDaten($conn, $rowPlatz3, $rowRundeP3['name'] ?? 'Spiel um Platz 3', $darfTeamsBearbeiten, $eigenesTeamId);
        }

        for ($ko_finallevel = $start_ko_finallevel; $ko_finallevel >= 2; $ko_finallevel--) {
            $rowRunde = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . $ko_finallevel)->fetch_assoc();
            $rundenName = htmlspecialchars($rowRunde['name'] ?? "Level $ko_finallevel", ENT_QUOTES, 'UTF-8');
            $anzahlSpiele = (int)pow(2, $ko_finallevel - 2);

            echo "<div class='bracket-round' data-round='$ko_finallevel'>
                <div class='bracket-round-title'>$rundenName</div>
                <div class='bracket-round-matches'>";

            for ($pos = 1; $pos <= $anzahlSpiele; $pos++) {
                $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE ' . $statusFilterBaum . ' AND ko_finallevel = ' . $ko_finallevel . ' AND ko_turnierbaumposition = ' . $pos . ' AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY id DESC LIMIT 1';
                $rowBegegnung = $conn->query($sqlBegegnung)->fetch_assoc();

                if (!$rowBegegnung) {
                    echo "<div class='bracket-match placeholder' data-round='$ko_finallevel' data-pos='$pos'>
                        <div class='bracket-team'><span>TBD</span></div>
                        <div class='bracket-team'><span>TBD</span></div>
                    </div>";
                    continue;
                }

                $bd = ermittleBegegnungsDaten($conn, $rowBegegnung, $rowRunde['name'] ?? '', $darfTeamsBearbeiten, $eigenesTeamId);

                // ========================================================================================
                // ANOMALIE-ERKENNUNG: weicht diese Begegnung vom automatisch berechneten Baum ab?
                // ========================================================================================
                // Fall 1: Green Card (status 4/7) - per Definition eine bewusste manuelle Abweichung.
                // Fall 2: beide Zubringer-Spiele der Vorrunde (Position pos*2-1 und pos*2) sind bereits
                // entschieden, aber die hier stehenden zwei Teams sind NICHT genau deren beide Sieger -
                // kommt z.B. zustande, wenn nachträglich eine Begegnung gesperrt und die Nachfolge-Runde
                // per Green Card manuell neu besetzt wurde. Nur relevant ab der zweiten Runde (die erste
                // hat keine Zubringer-Spiele, die Teams stehen dort von Anfang an fest). Siehe Chat.
                $istAnomalie = ($bd['status'] == 4 || $bd['status'] == 7);
                if (!$istAnomalie && $ko_finallevel < $start_ko_finallevel) {
                    $feederLevel = $ko_finallevel + 1;
                    $feederPos1 = $pos * 2 - 1; $feederPos2 = $pos * 2;
                    $sqlFeeder = 'SELECT fk_siegerteam FROM Turnier_Begegnung WHERE status IN (5,7) AND ko_finallevel = ' . $feederLevel . ' AND ko_turnierbaumposition IN (' . $feederPos1 . ',' . $feederPos2 . ') AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ')';
                    $feederSieger = [];
                    $resFeeder = $conn->query($sqlFeeder);
                    while ($rf = $resFeeder->fetch_assoc()) { if ($rf['fk_siegerteam']) { $feederSieger[] = (int)$rf['fk_siegerteam']; } }
                    if (count($feederSieger) == 2) {
                        sort($feederSieger);
                        $tatsaechlich = [(int)$bd['heimteamID'], (int)$bd['auswaertsteamID']];
                        sort($tatsaechlich);
                        if ($feederSieger !== $tatsaechlich) { $istAnomalie = true; }
                    }
                }
                $anomalieBadge = $istAnomalie
                    ? " <span class='bracket-anomalie-badge' title='Weicht vom automatisch berechneten Turnierbaum ab (z.B. durch Sperren/Green Card) - mindestens eines der Teams kommt nicht direkt aus den beiden verbundenen Spielen der Vorrunde.'>&#9888;</span>"
                    : '';

                $team1Klassen = ($bd['siegerteam'] == $bd['heimteamID']) ? ' bracket-team--winner' : '';
                $team2Klassen = ($bd['siegerteam'] == $bd['auswaertsteamID']) ? ' bracket-team--winner' : '';
                if ($eigenesTeamId && $bd['heimteamID'] == $eigenesTeamId) { $team1Klassen .= ' bracket-team--own'; }
                if ($eigenesTeamId && $bd['auswaertsteamID'] == $eigenesTeamId) { $team2Klassen .= ' bracket-team--own'; }
                $kuerzel1 = printKuerzelWithLink($conn, $bd['teamId1']);
                $kuerzel2 = printKuerzelWithLink($conn, $bd['teamId2']);

                echo "<div class='bracket-match" . ($bd['istGesperrt'] ? ' begegnung-gesperrt-row' : '') . ($istAnomalie ? ' bracket-match--anomalie' : '') . "' data-round='$ko_finallevel' data-pos='$pos' data-anomalie='" . ($istAnomalie ? '1' : '0') . "'>
                    <div class='bracket-match-meta'>{$bd['begegnungIdAnzeige']}{$bd['greenCardDot']}{$bd['gesperrtLabel']}$anomalieBadge</div>
                    <div class='bracket-team$team1Klassen'><span>{$bd['heimteam']} ($kuerzel1)</span></div>
                    <div class='bracket-team$team2Klassen'><span>{$bd['auswaertsteam']} ($kuerzel2)</span></div>
                    <div class='bracket-games'>";
                printGames($TurnierID, $conn, $bd['begegnungId'], $gameEditMode, $bd['status'], $darfAlleSpieleBearbeiten, $eigenesTeamId);
                echo "</div>
                </div>";
            }

            // Spiel um Platz 3 gehört optisch zur Finale-Spalte (unterhalb, mit Abstand) statt einer
            // eigenen Spalte daneben - liegt als zusätzliches Kind in .bracket-round-matches, wird aber
            // per "bracket-platz3-card"-Klasse von der Klammerlinien-/Paarungs-Logik in JS ausgenommen
            // und stattdessen separat unterhalb der Finale-Karte positioniert.
            if ($ko_finallevel == 2 && $bdP3) {
                $team1KlassenP3 = ($bdP3['siegerteam'] == $bdP3['heimteamID']) ? ' bracket-team--winner' : '';
                $team2KlassenP3 = ($bdP3['siegerteam'] == $bdP3['auswaertsteamID']) ? ' bracket-team--winner' : '';
                if ($eigenesTeamId && $bdP3['heimteamID'] == $eigenesTeamId) { $team1KlassenP3 .= ' bracket-team--own'; }
                if ($eigenesTeamId && $bdP3['auswaertsteamID'] == $eigenesTeamId) { $team2KlassenP3 .= ' bracket-team--own'; }
                echo "<div class='bracket-match bracket-platz3-card" . ($bdP3['istGesperrt'] ? ' begegnung-gesperrt-row' : '') . "'>
                    <div class='bracket-platz3-label'>&#129352; $rundenNameP3</div>
                    <div class='bracket-match-meta'>{$bdP3['begegnungIdAnzeige']}{$bdP3['greenCardDot']}{$bdP3['gesperrtLabel']}</div>
                    <div class='bracket-team$team1KlassenP3'><span>{$bdP3['heimteam']} (" . printKuerzelWithLink($conn, $bdP3['teamId1']) . ")</span></div>
                    <div class='bracket-team$team2KlassenP3'><span>{$bdP3['auswaertsteam']} (" . printKuerzelWithLink($conn, $bdP3['teamId2']) . ")</span></div>
                    <div class='bracket-games'>";
                printGames($TurnierID, $conn, $bdP3['begegnungId'], $gameEditMode, $bdP3['status'], $darfAlleSpieleBearbeiten, $eigenesTeamId);
                echo "</div>
                </div>";
            }

            echo "</div></div>"; // .bracket-round-matches, .bracket-round
        }

        // Turniersieger: eigene, letzte "Spalte" - zeigt das Siegerteam des Finales, sobald feststeht.
        $rowFinale = $conn->query('SELECT * FROM Turnier_Begegnung WHERE ' . $statusFilterBaum . ' AND ko_finallevel = 2 AND ko_turnierbaumposition = 1 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY id DESC LIMIT 1')->fetch_assoc();
        echo "<div class='bracket-champion'><div class='bracket-round-title'>&#127942; Turniersieger</div><div class='bracket-champion-card'>";
        if ($rowFinale && $rowFinale['fk_siegerteam']) {
            $rowSieger = $conn->query('SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . (int)$rowFinale['fk_siegerteam'])->fetch_assoc();
            if ($rowSieger) {
                $siegerName = htmlspecialchars($rowSieger['name'], ENT_QUOTES, 'UTF-8');
                echo "$siegerName (" . printKuerzelWithLink($conn, $rowSieger['id']) . ")";
            }
        } else {
            echo "<i>steht noch nicht fest</i>";
        }
        echo "</div></div>";

        echo "
                <svg class='bracket-lines' id='bracketLines'></svg>
                </div><!-- .bracket-tree -->
            </div><!-- .bracket-scroll -->
        </div><!-- .bracket-shell -->
        ";

        // ============================================================================================
        // KLASSENBAUM-LAYOUT + KLAMMERLINIEN PER JS: erste Runde bleibt im normalen Flow (liefert die
        // Basis-Positionen), jede weitere Runde wird per JS mittig zwischen ihre zwei Zubringer-Spiele
        // positioniert (absolute Positionierung anhand echt gemessener Höhen) - robust gegenüber
        // unterschiedlich hohen Karten (Admin sieht mehr Buttons/Zeilen als die Öffentlichkeit), da rein
        // pixelbasiert statt auf eine starre CSS-Formel angewiesen. Läuft bei jedem Laden/Resize neu.
        // ============================================================================================
        echo '
        <script>
        (function() {
            // "Spiel um Platz 3" liegt als zusätzliches Kind in der Finale-Spalte (.bracket-platz3-card),
            // darf aber NICHT wie ein normales Bracket-Spiel mitgezählt/gepaart werden (keine Nachfolge-
            // Begegnung) - überall, wo echte Spiele einer Runde gebraucht werden, wird sie rausgefiltert.
            function echteMatches(matchesWrap) {
                return Array.prototype.slice.call(matchesWrap.children).filter(function(el) {
                    return !el.classList.contains("bracket-platz3-card");
                });
            }

            function layoutBracket() {
                var tree = document.getElementById("bracketTree");
                if (!tree) { return; }
                var rounds = Array.prototype.slice.call(tree.querySelectorAll(".bracket-round"));
                if (rounds.length < 1) { return; }

                // Runden zurücksetzen (falls layoutBracket() erneut läuft, z.B. nach Resize)
                rounds.forEach(function(round, idx) {
                    var matchesWrap = round.querySelector(".bracket-round-matches");
                    if (idx === 0) {
                        matchesWrap.style.height = "";
                        Array.prototype.forEach.call(matchesWrap.children, function(m) {
                            m.style.position = ""; m.style.top = ""; m.style.left = ""; m.style.right = "";
                        });
                    }
                });

                var baseHeight = rounds[0].querySelector(".bracket-round-matches").offsetHeight;
                var centersProRunde = [];

                rounds.forEach(function(round, idx) {
                    var matchesWrap = round.querySelector(".bracket-round-matches");
                    var matches = echteMatches(matchesWrap);
                    var centers = [];
                    if (idx === 0) {
                        matches.forEach(function(m) {
                            centers.push(m.offsetTop + m.offsetHeight / 2);
                        });
                    } else {
                        var vorherigeCenters = centersProRunde[idx - 1];
                        matchesWrap.style.height = baseHeight + "px";
                        matches.forEach(function(m, i) {
                            var c1 = vorherigeCenters[i * 2];
                            var c2 = (vorherigeCenters[i * 2 + 1] !== undefined) ? vorherigeCenters[i * 2 + 1] : c1;
                            var mitte = (c1 + c2) / 2;
                            m.style.position = "absolute";
                            m.style.left = "0"; m.style.right = "0";
                            m.style.top = (mitte - m.offsetHeight / 2) + "px";
                            centers.push(mitte);
                        });
                    }
                    centersProRunde.push(centers);
                });

                // "Spiel um Platz 3": mit etwas Abstand UNTER die (jetzt final positionierte) Finale-Karte
                // setzen - eigene absolute Positionierung statt normalem Fluss, weil die umgebende
                // .bracket-round-matches auf die volle Baumhöhe gestreckt ist (siehe oben).
                var letzteRunde = rounds[rounds.length - 1];
                var platz3 = letzteRunde.querySelector(".bracket-platz3-card");
                if (platz3) {
                    var finaleMatches = echteMatches(letzteRunde.querySelector(".bracket-round-matches"));
                    var finale = finaleMatches[0];
                    if (finale) {
                        platz3.style.position = "absolute";
                        platz3.style.left = "0"; platz3.style.right = "0";
                        platz3.style.top = (finale.offsetTop + finale.offsetHeight + 32) + "px";
                    }
                }

                // Klammerlinien als SVG-Pfade zeichnen (Koordinaten relativ zu #bracketTree)
                var svg = document.getElementById("bracketLines");
                if (!svg) { return; }
                svg.innerHTML = "";
                var treeRect = tree.getBoundingClientRect();
                var ns = "http://www.w3.org/2000/svg";

                for (var r = 1; r < rounds.length; r++) {
                    var vorMatches = echteMatches(rounds[r - 1].querySelector(".bracket-round-matches"));
                    var dieseMatches = echteMatches(rounds[r].querySelector(".bracket-round-matches"));

                    dieseMatches.forEach(function(ziel, i) {
                        var zielRect = ziel.getBoundingClientRect();
                        var zielY = zielRect.top - treeRect.top + zielRect.height / 2;
                        var zielX = zielRect.left - treeRect.left;
                        var istAnomalie = ziel.getAttribute("data-anomalie") === "1";

                        [vorMatches[i * 2], vorMatches[i * 2 + 1]].forEach(function(quelle) {
                            if (!quelle) { return; }
                            var qRect = quelle.getBoundingClientRect();
                            var qY = qRect.top - treeRect.top + qRect.height / 2;
                            var qX = qRect.right - treeRect.left;
                            var midX = (qX + zielX) / 2;
                            var path = document.createElementNS(ns, "path");
                            path.setAttribute("d", "M" + qX + "," + qY + " H" + midX + " V" + zielY + " H" + zielX);
                            // Orange/gestrichelt, wenn das Zielspiel vom automatisch berechneten Baum
                            // abweicht (siehe $istAnomalie in PHP) - macht auf einen Blick sichtbar, wo
                            // Sperren/Green-Cards die "saubere" Turnierbaum-Logik durchbrochen haben.
                            if (istAnomalie) { path.setAttribute("class", "bracket-line-anomalie"); }
                            svg.appendChild(path);
                        });
                    });
                }
            }

            var timer;
            function layoutBracketVerzoegert() {
                clearTimeout(timer);
                timer = setTimeout(layoutBracket, 60);
            }

            if (document.readyState === "complete") { layoutBracket(); }
            else { window.addEventListener("load", layoutBracket); }

            window.addEventListener("resize", layoutBracketVerzoegert);

            // WICHTIG: Diese Website blendet Abschnitte per Hash-Navigation ein/aus (die KO-Phase-Sektion
            // liegt beim Laden der Seite oft noch unsichtbar im DOM) - beim ersten Lauf oben wären dadurch
            // alle Höhen/Positionen 0, alle Karten würden übereinander landen. Ein ResizeObserver auf der
            // ersten (nie von diesem Skript selbst veränderten) Runden-Spalte erkennt zuverlässig sowohl
            // "Abschnitt wird sichtbar" (Größe springt von 0 auf echt) als auch normales Fenster-Resize/
            // Browser-Zoom, und löst dann automatisch ein Neu-Layout aus.
            var ersteRundenSpalte = document.querySelector("#bracketTree .bracket-round .bracket-round-matches");
            if (ersteRundenSpalte && window.ResizeObserver) {
                new ResizeObserver(layoutBracketVerzoegert).observe(ersteRundenSpalte);
            }

            // ============================================================================================
            // DRAG-TO-SCROLL PER MAUS (siehe Chat: "am PC mit Drag and Drop durchnavigieren, nicht nur
            // scrollen") - reine Maus-Events (mousedown/mousemove/mouseup), Touch-Geräte scrollen weiterhin
            // ganz normal per Fingergeste, damit kollidiert das hier nicht. Ein SCHWELLENWERT unterscheidet
            // "nur geklickt" von "wirklich gezogen" - erst ab ein paar Pixel Bewegung wird tatsächlich
            // gescrollt UND danach der folgende Klick unterdrückt (sonst würde z.B. ein Team-Link/Button
            // unter dem Mauszeiger versehentlich ausgelöst, wenn man über ihm loslässt).
            // ============================================================================================
            var scrollBereich = document.querySelector(".bracket-scroll");
            if (scrollBereich) {
                var ziehtGerade = false, bewegtSichSchon = false, startX = 0, startY = 0, startScrollLeft = 0, startScrollTop = 0;
                var SCHWELLENWERT = 4;

                scrollBereich.addEventListener("mousedown", function(e) {
                    if (e.button !== 0) { return; } // nur linke Maustaste
                    ziehtGerade = true;
                    bewegtSichSchon = false;
                    startX = e.pageX; startY = e.pageY;
                    startScrollLeft = scrollBereich.scrollLeft; startScrollTop = scrollBereich.scrollTop;
                });

                window.addEventListener("mousemove", function(e) {
                    if (!ziehtGerade) { return; }
                    var dx = e.pageX - startX, dy = e.pageY - startY;
                    if (!bewegtSichSchon && (Math.abs(dx) > SCHWELLENWERT || Math.abs(dy) > SCHWELLENWERT)) {
                        bewegtSichSchon = true;
                        scrollBereich.classList.add("bracket-scroll--grabbing");
                    }
                    if (bewegtSichSchon) {
                        e.preventDefault();
                        scrollBereich.scrollLeft = startScrollLeft - dx;
                        scrollBereich.scrollTop = startScrollTop - dy;
                    }
                });

                window.addEventListener("mouseup", function() {
                    if (!ziehtGerade) { return; }
                    ziehtGerade = false;
                    scrollBereich.classList.remove("bracket-scroll--grabbing");
                    if (bewegtSichSchon) {
                        var klickUnterdruecken = function(ev) {
                            ev.preventDefault();
                            ev.stopPropagation();
                            scrollBereich.removeEventListener("click", klickUnterdruecken, true);
                        };
                        scrollBereich.addEventListener("click", klickUnterdruecken, true);
                    }
                });
            }
        })();
        </script>
        ';

        // ============================================================================================
        // KO-EINZUG-FERTIG-/TURNIER-ABSCHLIESSEN-UMSCHALTER: identische Funktionalität wie in der
        // K.-o.-Tabelle (siehe printKO_PhaseTabellen), hier unterhalb des Baums statt in einer bestimmten
        // Spalte, da diese beiden Umschalter sich nicht sinnvoll einer einzelnen Bracket-Karte zuordnen
        // lassen.
        // ============================================================================================
        if ($darfTeamsBearbeiten) {
            $sqlEinzugFertig = 'SELECT einzug_ko_manuell_anlegen, einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei FROM Turnier_Main WHERE id = ' . $TurnierID;
            $rowEinzugFertig = $conn->query($sqlEinzugFertig)->fetch_assoc();
            $einzugKoManuellAnlegen = (int)($rowEinzugFertig['einzug_ko_manuell_anlegen'] ?? 0);
            $einzugFertig = (int)($rowEinzugFertig['einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei'] ?? 0);
            if ($einzugKoManuellAnlegen == 1) {
                $checkedAttr = ($einzugFertig == 1) ? "checked" : "";
                $statusText = ($einzugFertig == 1) ? "aktuell: aktiviert" : "aktuell: deaktiviert";
                echo "
                <div style='text-align:center;margin:1rem 0;'>
                <div style='display:inline-block; background: rgba(139, 92, 246, 0.15); border: 1px solid #8b5cf6; border-radius: 8px; padding: 0.6rem 1rem;'>
                <form action='website_datachange/edit_variables.php' method='POST' style='margin:0;display:inline-flex;align-items:center;gap:0.6rem;flex-wrap:wrap;justify-content:center;'>
                    <input type='hidden' name='TurnierID' value='$TurnierID'/>
                    <input type='hidden' name='action' value='Einzug_KO_Fertig_Umschalten'/>
                    <input type='hidden' name='bn' value='" . htmlspecialchars($bnEingeloggt, ENT_QUOTES, 'UTF-8') . "'/>
                    <input type='hidden' name='pw' value='" . htmlspecialchars($pwEingeloggt, ENT_QUOTES, 'UTF-8') . "'/>
                    <span>Gruppenphase beendet / K.-o.-Einzug fertig angelegt (<i>$statusText</i>):</span>
                    <input type='checkbox' id='ko_einzug_fertig_baum' name='einzug_ko_fertig' value='1' $checkedAttr>
                    <label for='ko_einzug_fertig_baum'>aktiviert</label>
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
        // "Turnier abschließen": bleibt bewusst Admin/Co-Admin-exklusiv (turnier_settings-Flag, NICHT
        // $darfTeamsBearbeiten), genau wie in printKO_PhaseTabellen - siehe rollen_definitionen.php.
        if ($darfTurnierSettingsAendern) {
            $rowFinaleSiegerBaum = $conn->query('SELECT * FROM Turnier_Begegnung WHERE ko_finallevel = 2 AND status NOT IN (3,6) AND fk_siegerteam IS NOT NULL AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') LIMIT 1')->fetch_assoc();
            if ($rowFinaleSiegerBaum) {
                $rowPhaseAktuellBaum = $conn->query('SELECT fk_turnier_phase FROM Turnier_Main WHERE id = ' . $TurnierID)->fetch_assoc();
                $turnierAbgeschlossenBaum = ((int)$rowPhaseAktuellBaum['fk_turnier_phase'] === 9);
                $taCheckedAttrBaum = $turnierAbgeschlossenBaum ? "checked" : "";
                $taStatusTextBaum = $turnierAbgeschlossenBaum ? "aktuell: abgeschlossen" : "aktuell: nicht abgeschlossen";
                echo "
                <div style='text-align:center;margin:1rem 0;'>
                <div style='display:inline-block; background: rgba(139, 92, 246, 0.15); border: 1px solid #8b5cf6; border-radius: 8px; padding: 0.6rem 1rem;'>
                <form action='website_datachange/edit_variables.php' method='POST' style='margin:0;display:inline-flex;align-items:center;gap:0.6rem;flex-wrap:wrap;justify-content:center;'>
                    <input type='hidden' name='TurnierID' value='$TurnierID'/>
                    <input type='hidden' name='action' value='Turnier_Abschliessen_Umschalten'/>
                    <input type='hidden' name='bn' value='" . htmlspecialchars($bnEingeloggt, ENT_QUOTES, 'UTF-8') . "'/>
                    <input type='hidden' name='pw' value='" . htmlspecialchars($pwEingeloggt, ENT_QUOTES, 'UTF-8') . "'/>
                    <span>Turnier abschließen (<i>$taStatusTextBaum</i>):</span>
                    <input type='checkbox' id='turnier_abgeschlossen_baum' name='turnier_abgeschlossen' value='1' $taCheckedAttrBaum>
                    <label for='turnier_abgeschlossen_baum'>aktiviert</label>
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

    function trigger_sieger_innen_treppe($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus){
        $platzierungen = []; //Array erstellen
        $zeahler = 0;
        while($zeahler<3){
            $actPlatzierung = $zeahler+1;
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND endplatzierung > 0 AND endplatzierung = '.$actPlatzierung.' ORDER BY endplatzierung ASC'; //AND NOT endplatzierung = NULL
            $resultTeam = $conn->query($sqlTeam);
            $platzierungen[$zeahler] = "platzhalter";
            while (!empty($rowTeam = $resultTeam->fetch_assoc())) {
                // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber den Teamnamen auf der oeffentlichen Sieger*innen-Treppe.
                // Team-ID wird mitgespeichert, damit der Name auf dem Treppchen zur Teamseite verlinkt werden kann.
                $platzierungen[$zeahler] = [
                    'name' => htmlspecialchars($rowTeam['name'], ENT_QUOTES, 'UTF-8'),
                    'teamId' => (int)$rowTeam['id'],
                ];
            }
            $zeahler++;
        }
        if($platzierungen[0]!="platzhalter"||$platzierungen[1]!="platzhalter"||$platzierungen[2]!="platzhalter"){
            print_sieger_innen_treppe($platzierungen);
            //echo "1";
        }
    }
    function print_sieger_innen_treppe($platzierungen){
        // NEU DESIGNT: echtes Siegertreppchen (3 unterschiedlich hohe Stufen, Gold/Silber/Bronze) statt
        // der vorherigen rohen CSS-Grid-Kästen mit rotem Hintergrund - auf ausdrücklichen Wunsch, siehe
        // Chat. Reihenfolge visuell klassisch 2.-1.-3. (1. Platz in der Mitte, am höchsten).
        // SICHERHEIT: Teamnamen sind hier bereits in trigger_sieger_innen_treppe() htmlspecialchars()-
        // escaped (siehe dort) - hier nicht nochmal escapen, sonst werden z.B. "&" doppelt kodiert.
        // Namen verlinken auf ausdrücklichen Wunsch zur jeweiligen Teamseite (gleiches Linkziel wie
        // printKuerzelWithLink() an anderer Stelle: ?teamId=X#teaminfo).
        $platzLink = function($platzierung) {
            if ($platzierung === "platzhalter") { return '<i>noch nicht bestimmt</i>'; }
            return "<a href='?teamId={$platzierung['teamId']}#teaminfo'>{$platzierung['name']}</a>";
        };
        $platz1 = $platzLink($platzierungen[0]);
        $platz2 = $platzLink($platzierungen[1]);
        $platz3 = $platzLink($platzierungen[2]);
        echo "
        <style>
            .podium-wrap { text-align: center; margin: 1.2rem 0 1.8rem; }
            .podium { display: flex; align-items: flex-end; justify-content: center; gap: 0.8rem; max-width: 36rem; margin: 1.5rem auto; }
            .podium-step { flex: 1 1 0; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
            .podium-name { font-weight: 700; font-size: 0.95rem; min-height: 2.6rem; display: flex; align-items: center; justify-content: center; padding: 0 0.3rem; }
            .podium-name a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
            .podium-name a:hover { color: var(--team-accent, #14b8a6); }
            .podium-block { width: 100%; border-radius: 10px 10px 0 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.2rem; color: #241a05; font-weight: 800; box-shadow: 0 -4px 16px rgba(0,0,0,0.25); }
            .podium-medal { font-size: 1.6rem; }
            .podium-rank { font-size: 1.25rem; }
            .podium-step--1 .podium-block { height: 9rem; background: linear-gradient(180deg, #ffe066, #f5b942); }
            .podium-step--2 .podium-block { height: 6.5rem; background: linear-gradient(180deg, #eef0f2, #b8bfc7); }
            .podium-step--3 .podium-block { height: 4.5rem; background: linear-gradient(180deg, #e3a877, #c97f4a); }
            @media (max-width: 480px) { .podium { max-width: 20rem; gap: 0.5rem; } .podium-name { font-size: 0.8rem; min-height: 2.2rem; } }
        </style>
        <div class='podium-wrap'>
            <h2>Die Ergebnisse stehen fest! &#127881;</h2>
            <div class='podium'>
                <div class='podium-step podium-step--2'>
                    <div class='podium-name'>$platz2</div>
                    <div class='podium-block'><span class='podium-medal'>&#129352;</span><span class='podium-rank'>2</span></div>
                </div>
                <div class='podium-step podium-step--1'>
                    <div class='podium-name'>$platz1</div>
                    <div class='podium-block'><span class='podium-medal'>&#129351;</span><span class='podium-rank'>1</span></div>
                </div>
                <div class='podium-step podium-step--3'>
                    <div class='podium-name'>$platz3</div>
                    <div class='podium-block'><span class='podium-medal'>&#129353;</span><span class='podium-rank'>3</span></div>
                </div>
            </div>
            <a href='#rangliste' class='button primary'>Gesamte Platzierung</a>
        </div>
        ";
    }

    // War bisher nirgends aufgerufen - die "#rangliste"-Seite zeigte stattdessen nur einen CMS-Text-
    // Platzhalter. Auf ausdrücklichen Wunsch jetzt direkt bei "#rangliste" eingebunden (siehe index.php),
    // CMS-Bindung dafür entfernt.
    function print_platzierungen($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $eigenesTeamId = null){
        echo "<ul class='alt platzierungs-liste'>";
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
            $eigeneZeileKlasse = '';
            $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND endplatzierung = '. $platzierungsZaehler .' ORDER BY endplatzierung DESC'; //AND NOT endplatzierung = NULL
            $resultTeam = $conn->query($sqlTeam);
            while (!empty($rowTeam = $resultTeam->fetch_assoc())) {
                //$endplatzierung = $resultTeam['endplatzierung'];
                // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber den (vom Team selbst
                // frei waehlbaren) Teamnamen - HIER escapen (vor dem Anhaengen des bereits fertigen,
                // selbst escapten Kuerzel-Links), damit der Link danach nicht nochmal mit-escaped wird.
                $teamName = htmlspecialchars($rowTeam['name'], ENT_QUOTES, 'UTF-8');
                $teamId = $rowTeam['id'];
                $teamKuerzel = printKuerzelWithLink($conn, $teamId);
                $teamName .= " ($teamKuerzel)";
                // Eigene Zeile hervorheben (gleiche Signalfarbe wie in den anderen Tabellen) - auf
                // ausdrücklichen Wunsch auch hier.
                if ($eigenesTeamId && (int)$teamId === (int)$eigenesTeamId) { $eigeneZeileKlasse = ' own-team-row'; }
            }
            echo "<li class=\"platzierungs-zeile$eigeneZeileKlasse\">$platzierungsZaehler. $teamName</li>";
            $platzierungsZaehler++;
        }
        echo "</ul>";

    }

    // ================================================================================================
    // BEARBEITUNGSMODUS-ANZEIGE: seit der Einführung des Team-Logins rein login-basiert (siehe
    // index.php, $gameEditMode wird dort aus Team- ODER Account-Bearbeitungsrechten hergeleitet,
    // kein manueller An/Aus-Toggle mehr). Diese Funktion zeigt entweder die Legende + Erklärung (wenn
    // $gameEditMode==1) oder - falls die Turnierphase Bearbeitung grundsätzlich erlaubt, aber niemand
    // berechtigt eingeloggt ist - ein kompaktes Team-Login-Formular, das direkt auf dieselbe Sektion
    // zurückführt (kein Redirect auf eine andere Seite).
    function printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, $action, $test_turnier_id, $darfUnfinalisieren = false, $darfSperren = false){
        if($gameEditMode == 1){
            $sqlGetNurOberesDreieckInGruppenphase = "SELECT nurOberesDreieckInGruppenphase FROM Turnier_Main WHERE id = ". $TurnierID;
            $resultNurOberesDreieck = $conn->query($sqlGetNurOberesDreieckInGruppenphase);
            $rowNurOberesDreieck = $resultNurOberesDreieck->fetch_assoc();
            $nurOberesDreieck = $rowNurOberesDreieck['nurOberesDreieckInGruppenphase'];
            $hakchenErklaerung = ($nurOberesDreieck === 1 || $action === "#kophase")
                ? 'Sobald ihr alle Spiele gegen ein bestimmtes Team eingetragen habt, klickt noch einmal das grüne Häkchen an - die Website weiß dann, dass sie auf keine weiteren Spiele mehr wartet und kann die Teams schon für die nächste Runde berechnen.'
                : 'Mit diesem Button tragt ihr ein, dass ein Spiel ergebnislos bleibt (z.B. weil es nicht stattfinden konnte).';
            // ★-Zeile nur erklären, wenn der/die Eingeloggte den Unfinalisieren-Button überhaupt sehen kann
            // (Teams mit reinen Bearbeitungsrechten sehen den Button seit dem Rollen-Gate nicht mehr, siehe
            // printGames()) - sonst würde die Legende einen Button erklären, der gar nicht da ist.
            $unfinalZeile = $darfUnfinalisieren
                ? "<div class='game-edit-legend-row'><span class='game-edit-legend-swatch game-edit-legend-swatch--unfinal'>&#9733;</span> Zeigt eine bereits final markierte Begegnung an - Klick hebt die Finalisierung wieder auf (nur für Schiedsrichter*in/Co-Admin/Admin sichtbar).</div>"
                : '';
            // Sperren-Zeile für Admin/Co-Admin/Turniermaster (teams-Flag, siehe rollen_definitionen.php)
            // - der Button selbst existiert ohnehin nur in der K.-o.-Phase.
            $sperrenZeile = $darfSperren
                ? "<div class='game-edit-legend-row'><span class='game-edit-legend-swatch game-edit-legend-swatch--sperren'>&#128465;</span> Blendet eine von der Website automatisch berechnete Begegnung aus (öffentlich unsichtbar, vor Nachrücken geschützt), damit ihr stattdessen manuell eine Green-Card-Begegnung als Ersatz anlegen könnt (nur für Turniermaster/Co-Admin/Admin sichtbar).</div>"
                : '';
            echo "
            <div class='game-edit-legend'>
                <div class='game-edit-legend-row'><span class='game-edit-legend-swatch game-edit-legend-swatch--score'>3:1</span> <span>Bezieht sich auf <em>ein</em> Spiel: Gewinnerteam hat 3 Flaschen getrunken, Verliererteam trotzdem noch eine (sonst wäre es \"3:0\") - nicht vier Spiele.</span></div>
                <div class='game-edit-legend-row'><span class='game-edit-legend-swatch game-edit-legend-swatch--add'>+</span> Neuen Spielstand hinzufügen.</div>
                <div class='game-edit-legend-row'><span class='game-edit-legend-swatch game-edit-legend-swatch--final'>&check;</span> $hakchenErklaerung</div>
                $unfinalZeile
                $sperrenZeile
            </div>
            ";
            // Nur für Teams (nicht Admin/Schiri, die brauchen sich ja nicht selbst zu kontaktieren):
            // kleiner Hinweis, an wen man sich bei versehentlichen/fehlerhaften Einträgen wenden kann.
            if (isset($_SESSION['team_bn']) && isset($_SESSION['team_pw'])) {
                echo "<div class='game-edit-team-hint'>Falls ihr versehentlich Spiele eingetragen habt oder etwas korrigieren müsst, wendet euch an die Orga.</div>";
            }
        }else{
            //Aktuelle Turnierphase herausfinden - erstmal ID
                $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                $resultTurnier = $conn->query($sqlTurnier);
                while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                    $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                }

                //SPIELPLAN
                if($turnier_phase_ID == 7 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
                    // Team-Session vorhanden, aber $gameEditMode trotzdem 0 -> die Session ist gültig
                    // (ungültige Team-Sessions raeumt index.php schon vorher auf), es fehlen also nur
                    // die Bearbeitungsrechte des Teams.
                    if (isset($_SESSION['team_bn']) && isset($_SESSION['team_pw'])) {
                        echo "<div class='game-edit-noaccess'>Euer Team ist eingeloggt, hat aber aktuell keine Bearbeitungsrechte für Ergebnisse. Wendet euch an die Orga.</div>";
                    } else {
                        // Eindeutige IDs pro Aufrufstelle (Gruppenphase/K.-o.-Phase/Losing Bracket),
                        // da alle drei Sektionen gleichzeitig im DOM stehen (Hash-Navigation blendet nur ein/aus).
                        $idSuffix = preg_replace('/[^a-zA-Z0-9_]/', '', $action);
                        echo "
                        <div class='game-edit-login-prompt'>
                            <h4>&#128274; Logge dich als Team ein, um Ergebnisse einzutragen</h4>
                            <form method='post' action='?test_turnier_id=$test_turnier_id$action'>
                                <input type='hidden' name='team_login_zurueck_hash' value='$idSuffix'>
                                <input type='text' id='team_login_kuerzel_$idSuffix' name='team_login_kuerzel' class='Eingabe' placeholder='Team-Kürzel' required autocomplete='username'>
                                <input type='password' id='team_login_passwort_$idSuffix' name='team_login_passwort' class='Eingabe' placeholder='Team-Passwort' required autocomplete='current-password'>
                                <button type='submit' class='button primary'>Einloggen</button>
                            </form>
                            <p class='note'>Admin oder Schiedsrichter*in? <a href='#login'>Oben einloggen.</a></p>
                        </div>
                        ";
                    }
                }else{
                    // Kein disabled Button mehr, sondern ein Hinweis, WARUM Ergebnisse aktuell nicht
                    // eingetragen werden können - je nach Turnierphase unterschiedlicher Text.
                    $phaseHinweise = [
                        1  => 'Die Anmeldephase hat noch nicht begonnen - das Turnier läuft noch nicht.',
                        3  => 'Die Anmeldephase läuft noch - das Turnier hat noch nicht begonnen.',
                        12 => 'Die Anmeldephase ist voll, neu angemeldete Teams landen auf der Warteliste - das Turnier hat noch nicht begonnen.',
                        4  => 'Die Gruppen werden gerade eingeteilt - gleich geht es los.',
                        5  => 'Die Gruppen werden gerade ausgelost - gleich geht es los.',
                        9  => 'Das Turnier ist bereits vorbei.',
                    ];
                    if (isset($phaseHinweise[$turnier_phase_ID])) {
                        $phaseHinweisText = $phaseHinweise[$turnier_phase_ID];
                    } else {
                        $sqlPhaseName = 'SELECT name FROM `Turnier_Setting_Phasen` WHERE id = ' . (int)$turnier_phase_ID;
                        $resultPhaseName = $conn->query($sqlPhaseName);
                        $rowPhaseName = $resultPhaseName ? $resultPhaseName->fetch_assoc() : null;
                        $phaseName = $rowPhaseName['name'] ?? 'unbekannt';
                        $phaseHinweisText = 'Aktuelle Turnierphase: ' . htmlspecialchars($phaseName, ENT_QUOTES, 'UTF-8') . '.';
                    }
                    echo "<div class='game-edit-phase-hint'>&#8505; Ergebnisse eintragen ist hier gerade nicht möglich - $phaseHinweisText</div>";
                }


        }
    }

    function printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status, $darfUnfinalisieren = false, $eigenesTeamId = null){
        //Heim- und Gegner-Namen herausfinden
        $sqlTeams = 'SELECT * FROM Turnier_Begegnung WHERE id = '. $begegnungId .';';
        $resultTeams = $conn->query($sqlTeams);
        while ( !empty( $rowTeams = $resultTeams->fetch_assoc() ) ){
            $heimteamId=$rowTeams['fk_heimteam'];
            $auswaertsteamId=$rowTeams['fk_auswaertsteam'];
        }
        // RECHTE-AUDIT: die interaktiven Buttons (Score bearbeiten/"+"/"✓") waren bisher überall sichtbar,
        // sobald irgendeine Bearbeitungsberechtigung vorlag - ein eingeloggtes Team sah sie also auch bei
        // fremden Begegnungen, obwohl ein Klick dort ohnehin serverseitig abgelehnt wird (siehe
        // $spielGehoertZuTeam-Prüfung in edit_games.php). Auf ausdrücklichen Wunsch jetzt schon in der
        // Anzeige eingeschränkt: wer das blanket "alle Spiele bearbeiten"-Recht hat (Schiri/Admin/
        // Co-Admin/Turniermaster), sieht die Buttons weiterhin überall - ein eingeloggtes Team nur noch
        // bei den eigenen Begegnungen. Das Unfinalisieren (★) bleibt bewusst exklusiv an $darfUnfinalisieren
        // gebunden (Teams dürfen das nie, siehe eigener Kommentar weiter unten).
        $darfDiesesSpielBearbeiten = $darfUnfinalisieren || ($eigenesTeamId && ($eigenesTeamId == $heimteamId || $eigenesTeamId == $auswaertsteamId));
        //Heimteam-Namen herausfinden
        $sqlHeimteam = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $heimteamId .';';
        $resultHeimteam = $conn->query($sqlHeimteam);
        while ( !empty( $rowHeimteam = $resultHeimteam->fetch_assoc() ) ){
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber das (vom Team frei waehlbare) Kuerzel
            $heimteam=htmlspecialchars($rowHeimteam['kuerzel'], ENT_QUOTES, 'UTF-8');
        }
        //Auswärtsteam-Namen herausfinden
        $sqlAusw = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $auswaertsteamId .';';
        $resultAusw = $conn->query($sqlAusw);
        while ( !empty( $rowAusw = $resultAusw->fetch_assoc() ) ){
            $auswaertsteam=htmlspecialchars($rowAusw['kuerzel'], ENT_QUOTES, 'UTF-8');
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
            if($gameEditMode == 1 && $darfDiesesSpielBearbeiten && $status != '5' && $status != '7'){ //editMode & noch nicht final
                ?>
                <form method='post' action='#changegame' style='margin: 0 0 0 0; display:inline;'>
                    <button type='submit' class='game-edit-btn game-edit-btn--score' name='action' value=''><?php echo $a?>:<?php echo $b?></button>
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
                // Gleiche kleine Badge-Optik wie die "3:1"-Kachel in der Bearbeiten-Legende (siehe
                // .game-edit-legend-swatch--score) - auf ausdrücklichen Wunsch überall wiederverwendet,
                // wo Spielstände angezeigt werden, damit sich mehrere Spiele einer Begegnung optisch
                // klar voneinander abgrenzen statt als eine lange Zeichenkette zu verschwimmen.
                echo "<span class='game-score-badge'>$a:$b</span>"; //FALL: SCHON FINAL
            }
        }

        if($status == '5' || $status == '7'){ //SCHON FINAL
            // Unfinalisieren-Button nur für Schiedsrichter*in/Co-Admin/Admin sichtbar (rechte_alle_spiele) -
            // Teams mit reinen Bearbeitungsrechten können ohnehin nicht unfinalisieren (siehe edit_games.php),
            // sollen den Button also gar nicht erst angezeigt bekommen (sonst würde sonst wegen der leeren
            // Verzweigung fälschlich der "+"-Button für ein bereits finalisiertes Spiel auftauchen).
            if($gameEditMode == 1 && $darfUnfinalisieren){
                //BEGEGNUNG UNFINALISIEREN
                ?>
                <form method='post' action='#changegame' style='margin: 0 0 0 0; display:inline;'>
                    <button type='submit' class='game-edit-btn game-edit-btn--unfinal' title='Finalisierung aufheben' name='action' value=''>&#9733;</button>
                    <input type='hidden' name='action' value='unfinal'/>
                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                </form>
                <?php
            }
        }else{ //NOCH NICHT FINAL
            //Fall, dass es noch keine Spiele gibt
            $sqlSpiel = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
            $resultSpiel = $conn->query($sqlSpiel);	
            //if (empty($rowSpiel = $resultSpiel->fetch_assoc())) {
                if($gameEditMode == 1 && $darfDiesesSpielBearbeiten){
                    ?>
                    <form method='post' action='#changegame' style='margin: 0 0 0 0; display:inline;'>
                        <button type='submit' class='game-edit-btn game-edit-btn--add' title='Spielstand hinzufügen'>+</button>
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
            if($gameEditMode == 1 && $darfDiesesSpielBearbeiten){
                //BEGEGNUNG FINAL MACHEN
                ?>
                <form method='post' action='#changegame' style='margin: 0 0 0 0; display:inline;'>
                    <button type='submit' class='game-edit-btn game-edit-btn--final' title='Begegnung finalisieren'>&#10003;</button>
                    <input type='hidden' name='action' value='final'/>
                    <input type='hidden' name='begegnungId' value='<?php echo $begegnungId ?>'/>
                    <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
                </form>
                <?php
            }else{ //do nothing 
            }
        }
    }

    function printSpielplanGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id, $darfAlleSpieleBearbeiten = false, $bnEingeloggt = '', $pwEingeloggt = '', $darfZufaelligeSpieleEintragen = false, $eigenesTeamId = null){
        try {
            //Button, mit dem man den Bearbeitungsmodus starten kann
            printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#gruppenphase", $test_turnier_id, $darfAlleSpieleBearbeiten);

            // ================================================================================================
            // TESTMODUS: "Zufällige Spiele eintragen" (nur sichtbar/wirksam im Testturnier, türkiser Rahmen)
            // ================================================================================================
            // Führt zur Auswahlseite backstage_zufaellige_spiele, wo man den Prozentsatz der noch offenen
            // Gruppenphasen-Begegnungen wählt, die auf einen Schlag zufällig befüllt+finalisiert werden.
            // Nur sichtbar für Admin/Co-Admin/Turniermaster/Backstage-Zugang/Schiedsrichter*in (siehe
            // $darfZufaelligeSpieleEintragen in index.php) - vorher fehlte hier jede Rollenprüfung.
            if ($test_turnier_id != 0 && $darfZufaelligeSpieleEintragen) {
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
                // Ist das eingeloggte Team in dieser Gruppe? -> Gruppe + eigene Zeile/Spalte farblich hervorheben,
                // damit Teams direkt sehen, wo sie gerade spielen.
                $istEigeneGruppe = false;
                if ($eigenesTeamId) {
                    $sqlEigeneGruppeCheck = 'SELECT COUNT(*) AS anzahl FROM Turnier_Team WHERE geloescht = 0 AND id = ' . (int)$eigenesTeamId . ' AND fk_gruppe = ' . (int)$rowGruppe['id'];
                    $istEigeneGruppe = ((int)$conn->query($sqlEigeneGruppeCheck)->fetch_assoc()['anzahl'] > 0);
                }
                // NEU: ist diese Gruppe komplett fertig gespielt (alle Begegnungen finalisiert)? Bestimmt,
                // ob das "Ihr spielt hier"-Badge in Gegenwarts- oder Vergangenheitsform steht - macht auf
                // ausdrücklichen Wunsch intuitiver sichtbar, wo das Turnier gerade steht. Gleiche
                // Zähl-Logik wie beim Finalisieren/Unfinalisieren-Button weiter unten, hier aber
                // unabhängig von Bearbeitungsrechten berechnet, da auch nicht-berechtigte Teams das
                // Badge sehen sollen.
                $gruppeIdFuerStatus = (int)$rowGruppe['id'];
                $sqlGruppeGesamtStatus = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 0 AND status <> 3 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ' . $gruppeIdFuerStatus . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ' . $gruppeIdFuerStatus . ')';
                $gruppeGesamtAnzahl = (int)$conn->query($sqlGruppeGesamtStatus)->fetch_assoc()['anzahl'];
                $gruppeIstAbgeschlossen = false;
                if ($gruppeGesamtAnzahl > 0) {
                    $sqlGruppeOffenStatus = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 0 AND status NOT IN (3,5,6,7) AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ' . $gruppeIdFuerStatus . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_gruppe = ' . $gruppeIdFuerStatus . ')';
                    $gruppeIstAbgeschlossen = ((int)$conn->query($sqlGruppeOffenStatus)->fetch_assoc()['anzahl'] === 0);
                }
                //SCHALTER -> soll in Gruppenphasentabelle nur obere Hälfte gefüllt werden -> 1 -> dann soll nämlich erste Zeile und erste Spalte wegfallen
                $sqlSchalter = 'SELECT * FROM Turnier_Main WHERE id = ' . $TurnierID . '';
                $resultSchalter = $conn->query($sqlSchalter);
                while ($rowSchalter = $resultSchalter->fetch_assoc()) {
                    $schalterDreieck = $rowSchalter['nurOberesDreieckInGruppenphase'];
                    $loescheErsteZeileUndSpalte = $rowSchalter['loescheErsteZeileUndSpalte'];
                }
                // MÖGLICHKEIT, EINE EINZELNE GRUPPE ZU FINALISIEREN/UNFINALISIEREN (eigener Button statt
                // anklickbarer Überschrift - die alte Variante nutzte einen falschen Action-Namen und
                // hatte gar keine bn/pw-Felder, konnte serverseitig also nie funktionieren).
                echo "<div class='matrix-group-heading" . ($istEigeneGruppe ? " matrix-group-heading--own-team" : "") . "'>";
                echo "<h2 style='display:inline-block; margin-right:0.6rem;'>Gruppe $gruppenname &#9733;</h2>";
                if ($istEigeneGruppe) {
                    echo $gruppeIstAbgeschlossen
                        ? "<span class='own-team-badge own-team-badge--past'>&#10003; Ihr habt hier gespielt</span>"
                        : "<span class='own-team-badge'>&#9654; Ihr spielt hier</span>";
                }
                if ($darfAlleSpieleBearbeiten) {
                    // BUGFIX: Der Button zeigte bisher IMMER "Gruppe finalisieren" mit fest verdrahteter
                    // Aktion Gruppe_Finalisieren, egal ob die Gruppe schon komplett finalisiert war -
                    // ein Klick auf eine bereits finalisierte Gruppe lief dadurch ins Leere (Status blieb
                    // gleich). Jetzt wie beim "Alle Gruppen"-Button: Zustand pro Gruppe prüfen und
                    // Beschriftung/Aktion entsprechend zwischen Finalisieren/Unfinalisieren umschalten.
                    // Wiederverwendet $gruppeGesamtAnzahl/$gruppeIstAbgeschlossen von oben statt erneut
                    // zu zählen (identische Abfrage).
                    $gruppeIdFuerCheck = (int)$rowGruppe['id'];
                    $gruppeAction = 'Gruppe_Finalisieren';
                    $gruppeLabel = 'Gruppe finalisieren';
                    if ($gruppeGesamtAnzahl > 0 && $gruppeIstAbgeschlossen) {
                        $gruppeAction = 'Gruppe_Uninalisieren';
                        $gruppeLabel = 'Gruppe unfinalisieren';
                    }
                    echo "<form action='website_datachange/edit_games.php' method='POST' style='display:inline-block; margin:0;'>
                        <input type='hidden' name='TurnierID' value='$TurnierID'>
                        <input type='hidden' name='bn' value='$bnAttrSp'>
                        <input type='hidden' name='pw' value='$pwAttrSp'>
                        <input type='hidden' name='action' value='$gruppeAction'>
                        <input type='hidden' name='groupId' value='$gruppeIdFuerCheck'>
                        <button type='submit' class='tbl-action-btn tbl-action-btn--admin'>$gruppeLabel</button>
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
                                $ownHeaderClass = ($eigenesTeamId && $teamId == $eigenesTeamId) ? ' own-team-cell' : '';
                                echo "<th class='$ownHeaderClass' style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap;'>";
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
                                $istEigeneZeile = ($eigenesTeamId && $teamId == $eigenesTeamId);
                                echo "<td class='" . ($istEigeneZeile ? 'own-team-cell' : '') . "' style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap; vertical-align: middle;'>";
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
                                    // Zelle gehört zum eingeloggten Team, wenn Zeile ODER Spalte dessen Team ist.
                                    $istEigeneZelle = $istEigeneZeile || ($eigenesTeamId && $rowTeamSpalte["id"] == $eigenesTeamId);
                                    $eigeneZelleClass = $istEigeneZelle ? ' own-team-cell' : '';
                                    //CHECKEN OB ES KEINE BEGEGNUNG GIBT - WENN JA DANN "-" ausgeben
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    if ( empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td class='$eigeneZelleClass' style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'>";  // Tabellen-Feld eröffnen
                                        echo " - ";
                                    }
                                    //SONST BEGEGNUNGEN AUSGEBEN
                                    $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND fk_heimteam = ' . $rowTeamZeile["id"] . ' AND fk_auswaertsteam = ' . $rowTeamSpalte["id"] . ' AND ko_finallevel = 0 ORDER BY ID';
                                    $resultBegegnung = $conn->query($sqlBegegnung);
                                    while ( !empty( $rowBegegnung = $resultBegegnung->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                                        echo "<td class='$eigeneZelleClass' style='text-align:center; padding: 0.05em 0.2em !important; white-space: nowrap;'>";   // Tabellen-Feld eröffnen
                                        $begegnungId = $rowBegegnung['id'];
                                        $status = $rowBegegnung['status']; //HERAUSFINDEN OB BEGEGNUNG FINAL
                                        printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status, $darfAlleSpieleBearbeiten, $eigenesTeamId);
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
    function printSpielplanLosingBracket($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id, $darfZufaelligeSpieleEintragen = false, $darfAlleSpieleBearbeiten = false, $eigenesTeamId = null){
        try {
            printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#losingbracket", $test_turnier_id, $darfAlleSpieleBearbeiten);

            // ================================================================================================
            // TESTMODUS: "Zufällige Spiele eintragen" - gab es hier bisher gar nicht, obwohl es in
            // Gruppenphase und K.-o.-Phase schon existierte. Losing-Bracket-Begegnungen laufen unter
            // ko_finallevel=20 (siehe Turnier_KO_Finallevel), technisch also einfach eine weitere
            // "Finalstufe" für den scope=ko-Zweig der Auswahlseite backstage_zufaellige_spiele.
            // ================================================================================================
            if ($test_turnier_id != 0 && $darfZufaelligeSpieleEintragen) {
                echo "<p><a href='?test_turnier_id=$test_turnier_id&zufall_scope=ko&zufall_ko_finallevel=20#backstage_zufaellige_spiele' class='tbl-action-btn tbl-action-btn--testmodus'>Zufällige Spiele eintragen</a></p>";
            }

            // Schalter aus Turnier_Main - eigener "nur oberes Dreieck"-Schalter fürs Losing Bracket,
            // "loescheErsteZeileUndSpalte" bleibt wie in der Gruppenphase gemeinsam genutzt.
            $schalterDreieck = 0; $loescheErsteZeileUndSpalte = 0;
            $sqlSchalter = 'SELECT nurOberesDreieckInLosingBracket, loescheErsteZeileUndSpalte FROM Turnier_Main WHERE id = ' . $TurnierID;
            $resultSchalter = $conn->query($sqlSchalter);
            if ($resultSchalter && ($rowSchalter = $resultSchalter->fetch_assoc())) {
                $schalterDreieck = (int)$rowSchalter['nurOberesDreieckInLosingBracket'];
                $loescheErsteZeileUndSpalte = (int)$rowSchalter['loescheErsteZeileUndSpalte'];
            }

            // Teilnehmer-Teams für LB dynamisch aus Begegnungen (ko_finallevel=20), nur dieses Turnier
            $teams = [];
            $sqlTeamsLB = 'SELECT DISTINCT t.id FROM Turnier_Team t WHERE t.geloescht = 0 AND t.fk_turnier = ' . $TurnierID . ' AND (t.id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20) OR t.id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20)) ORDER BY t.id';
            $resTeamsLB = $conn->query($sqlTeamsLB);
            while ($resTeamsLB && ($rt = $resTeamsLB->fetch_assoc())) { $teams[] = (int)$rt['id']; }

            // Ist das eingeloggte Team im Losing Bracket dabei? -> Überschrift + eigene Zeile/Spalte hervorheben.
            $istEigenesTeamImLB = ($eigenesTeamId && in_array((int)$eigenesTeamId, $teams, true));
            // NEU: sind ALLE eigenen Losing-Bracket-Begegnungen dieses Teams bereits finalisiert? Bestimmt
            // Gegenwarts- vs. Vergangenheitsform des Badges - siehe gleiche Logik bei der Gruppenphase.
            $lbEigenesTeamAbgeschlossen = false;
            if ($istEigenesTeamImLB) {
                $sqlLbGesamt = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 20 AND status <> 3 AND (fk_heimteam = ' . (int)$eigenesTeamId . ' OR fk_auswaertsteam = ' . (int)$eigenesTeamId . ')';
                $lbGesamtAnzahl = (int)$conn->query($sqlLbGesamt)->fetch_assoc()['anzahl'];
                if ($lbGesamtAnzahl > 0) {
                    $sqlLbOffen = 'SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 20 AND status NOT IN (3,5,6,7) AND (fk_heimteam = ' . (int)$eigenesTeamId . ' OR fk_auswaertsteam = ' . (int)$eigenesTeamId . ')';
                    $lbOffenAnzahl = (int)$conn->query($sqlLbOffen)->fetch_assoc()['anzahl'];
                    $lbEigenesTeamAbgeschlossen = ($lbOffenAnzahl === 0);
                }
            }
            echo "<div class='matrix-group-heading" . ($istEigenesTeamImLB ? " matrix-group-heading--own-team" : "") . "'><h2>Gruppe Losing Bracket &#9733;</h2>";
            if ($istEigenesTeamImLB) {
                echo $lbEigenesTeamAbgeschlossen
                    ? "<span class='own-team-badge own-team-badge--past'>&#10003; Ihr habt hier gespielt</span>"
                    : "<span class='own-team-badge'>&#9654; Ihr spielt hier</span>";
            }
            echo "</div>";
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
                $ownHeaderClass = ($eigenesTeamId && $tid == $eigenesTeamId) ? ' own-team-cell' : '';
                echo "<th class='$ownHeaderClass' style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap;'>";
                $return = printKuerzelWithLink($conn, $tid);
                echo $return;
                echo "</th>";
            }
            echo "</tr></thead><tbody>";

            $rowLimit = count($teams);
            if ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1 && $rowLimit > 0) { $rowLimit = $rowLimit - 1; }

            for ($ri = 0; $ri < $rowLimit; $ri++) {
                $rowTid = $teams[$ri];
                $istEigeneZeile = ($eigenesTeamId && $rowTid == $eigenesTeamId);
                echo "<tr>";
                echo "<td class='" . ($istEigeneZeile ? 'own-team-cell' : '') . "' style='padding: 0.05em 0.2em !important; text-align: center; white-space: nowrap; vertical-align: middle;'>";
                $return = printKuerzelWithLink($conn, $rowTid);
                echo $return;
                echo "</td>";

                $colStart = ($schalterDreieck == 1 && $loescheErsteZeileUndSpalte == 1) ? 1 : 0;
                for ($ci = $colStart; $ci < count($teams); $ci++) {
                    $colTid = $teams[$ci];
                    $eigeneZelleClass = ($istEigeneZeile || ($eigenesTeamId && $colTid == $eigenesTeamId)) ? ' own-team-cell' : '';
                    // BUGFIX: Unterdrückung der unteren Dreieckshälfte hing bisher zusätzlich am separaten
                    // "loescheErsteZeileUndSpalte"-Schalter (der nur die jetzt leere erste Spalte/letzte
                    // Zeile aus der Anzeige entfernt, siehe $colStart/$rowLimit oben) - dadurch blieb
                    // "nur oberes Dreieck im Losing Bracket" ohne diesen zweiten Schalter komplett wirkungslos,
                    // beide Zellen eines Team-Paars zeigten weiterhin dieselbe Begegnung zum Eintragen an.
                    if ($rowTid === $colTid || ($schalterDreieck == 1 && $ci <= $ri)) {
                        echo "<td style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'> - </td>";
                        continue;
                    }
                    $sqlBeg = 'SELECT * FROM `Turnier_Begegnung` WHERE `status` <> 3 AND ((fk_heimteam = ' . $rowTid . ' AND fk_auswaertsteam = ' . $colTid . ') OR (fk_heimteam = ' . $colTid . ' AND fk_auswaertsteam = ' . $rowTid . ')) AND ko_finallevel = 20 ORDER BY ID';
                    $resBeg = $conn->query($sqlBeg);
                    if ($resBeg && empty($resBeg->fetch_assoc())) {
                        echo "<td class='$eigeneZelleClass' style='text-align:center; padding: 0.1em 0.3em !important; white-space: nowrap;'> - </td>";
                    } else {
                        // erneut iterieren für Ausgabe
                        $resBeg = $conn->query($sqlBeg);
                        echo "<td class='$eigeneZelleClass' style='text-align:center; padding: 0.05em 0.2em !important; white-space: nowrap;'>";
                        while ($resBeg && ($rb = $resBeg->fetch_assoc())) {
                            $begegnungId = (int)$rb['id'];
                            $status = $rb['status'];
                            printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status, $darfAlleSpieleBearbeiten, $eigenesTeamId);
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

    function printPunktetabelleLosingBracket($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id, $eigenesTeamId = null){
        echo "<h2>Punktetabelle</h2>";
        echo "<table class='withBorderCollapse'><thead><tr><th>Team</th><th>Abk.</th><th>Sp.</th><th>Fl.</th><th>Pkt.</th></tr></thead><tbody>";
        $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' AND (id IN (SELECT fk_heimteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20) OR id IN (SELECT fk_auswaertsteam FROM Turnier_Begegnung WHERE status <> 3 AND ko_finallevel = 20)) ORDER BY gruppenphase_manuelle_platzierung asc, gruppenphase_punkte desc, gruppenphase_flaschen desc, gruppenphase_spiele desc';
        $resultTeamZeile = $conn->query($sqlTeam);
        $__lb_rows = 0;
        while ($resultTeamZeile && ($rowTeamZeile = $resultTeamZeile->fetch_assoc())) {
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber den Teamnamen
            $name=htmlspecialchars($rowTeamZeile["name"], ENT_QUOTES, 'UTF-8'); $teamId=$rowTeamZeile["id"];
            $gruppenphase_spiele=$rowTeamZeile["gruppenphase_spiele"];
            $gruppenphase_flaschen=$rowTeamZeile["gruppenphase_flaschen"];
            $gruppenphase_punkte=$rowTeamZeile["gruppenphase_punkte"];
            // Eigene Zeile hervorheben (gleiche Klasse/Farbe wie in den Spielplan-Tabellen) - auf
            // ausdrücklichen Wunsch auch in den Punktetabellen, nicht nur beim Spielplan selbst. Pro
            // <td> statt auf dem <tr> gesetzt, weil box-shadow (Teil von .own-team-row) auf <tr>-Elementen
            // in keinem gängigen Browser gerendert wird - nur der Hintergrund würde durchscheinen.
            $rowClassPkt = ($eigenesTeamId && (int)$teamId === (int)$eigenesTeamId) ? " own-team-row" : "";
            echo "<tr>";
            echo "<td class=\"$rowClassPkt\" style=\"text-align:left; padding: 0.1em 0.75em !important;\">$name</td>";
            echo "<td class=\"$rowClassPkt\" style=\"text-align:right; padding: 0.1em 0.75em !important;\">"; $return = printKuerzelWithLink($conn, $teamId); echo $return; echo "</td>";
            echo "<td class=\"$rowClassPkt\" style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_spiele</td>";
            echo "<td class=\"$rowClassPkt\" style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_flaschen</td>";
            echo "<td class=\"$rowClassPkt\" style=\"text-align:right; padding: 0.1em 0.75em !important;\">$gruppenphase_punkte</td>";
            echo "</tr>";
            $__lb_rows++;
        }
        if ($__lb_rows === 0) {
            echo "<tr><td colspan='5' style='text-align:center; opacity:.8;'>Noch keine Teams im Losing‑Bracket erfasst.</td></tr>";
        }
        echo "</tbody></table>";
    }

    function printPunktetabelleGruppenphase($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $test_turnier_id, $eigenesTeamId = null){
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
                        // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber den Teamnamen
                        $name=htmlspecialchars($rowTeamZeile["name"], ENT_QUOTES, 'UTF-8');
                        //$kuerzel=$rowTeamZeile["kuerzel"];
                        $teamId=$rowTeamZeile["id"];
                        $gruppenphase_spiele=$rowTeamZeile["gruppenphase_spiele"];
                        $gruppenphase_flaschen=$rowTeamZeile["gruppenphase_flaschen"];
                        $gruppenphase_punkte=$rowTeamZeile["gruppenphase_punkte"];
                        // Eigene Zeile hervorheben - pro <td> statt auf dem <tr>, weil box-shadow (Teil
                        // von .own-team-row) auf <tr>-Elementen in keinem gängigen Browser gerendert wird.
                        $rowClassPkt = ($eigenesTeamId && (int)$teamId === (int)$eigenesTeamId) ? " own-team-row" : "";
                        ?>
                        <!-- AUSGEBEN -->
                        <td class="<?php echo $rowClassPkt ?>" style="text-align='left';padding: 0.1em 0.75em !important;"><?php echo $name ?></td>
                        <td class="<?php echo $rowClassPkt ?>" style="text-align:right;padding: 0.1em 0.75em !important;"><?php $return = printKuerzelWithLink($conn, $teamId); echo "$return"; ?></td> <!-- echo $kuerzel -->
                        <td class="<?php echo $rowClassPkt ?>" style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_spiele ?></td> <!-- Anzahl der Spiele ausgeben -->
                        <td class="<?php echo $rowClassPkt ?>" style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_flaschen ?></td> <!-- Anzahl der Flaschen ausgeben -->
                        <td class="<?php echo $rowClassPkt ?>" style="text-align:right;padding: 0.1em 0.75em !important;"><?php echo $gruppenphase_punkte ?></td> <!-- Anzahl der Punkte ausgeben -->
                        </tr> <!-- nächste Zeile -->
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        <?php
        } 
    }

    // ================================================================================================
    // GETEILTE BAUSTEINE FÜR K.-O.-ANSICHTEN (Tabelle + Turnierbaum): einmal definiert, von beiden
    // printKO_PhaseTabellen() und printTurnierbaum() genutzt, damit beide Ansichten bei Rechten/
    // Verhalten (Sperren, Begegnungs-ID, Green-Card, Teamnamen) niemals auseinanderlaufen können.
    // ================================================================================================

    // CSS/HTML/JS für "Green-Card-Begegnung erstellen"/"Liste gesperrter Begegnungen"-Links + den
    // Sperren-Bestätigungsdialog. Identisch für Tabelle und Turnierbaum, daher hier zentral.
    function printBegegnungSperrenUI($darfTeamsBearbeiten, $bnEingeloggt, $pwEingeloggt){
        echo "
        <style>
            .green-card-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; margin-left: 3px; vertical-align: middle; }
            .begegnung-gesperrt-row { opacity: 0.5; }
            .begegnung-gesperrt-label { font-size: 10px; color: #e74c3c; }
            .begegnung-sperren-btn { display: inline-flex; align-items: center; justify-content: center; width: 1rem; height: 1rem; margin-left: 3px; border-radius: 3px; background: rgba(239, 68, 68, 0.85); color: #fff; font-size: 0.65rem; line-height: 1; text-decoration: none; vertical-align: middle; cursor: pointer; border: none; padding: 0; }
            .begegnung-sperren-btn:hover { background: #ef4444; }
        </style>
        ";
        // RECHTE-AUDIT: Begegnungs-ID, Sperren-Button und diese beiden Menü-Links hängen am teams-Flag
        // (= Admin/Co-Admin/Turniermaster, siehe rollen_definitionen.php) - auf ausdrücklichen Wunsch,
        // siehe Chat. "Begegnung bearbeiten" heißt jetzt "Green-Card-Begegnung erstellen" (reine
        // Anlegen-Funktion, Sperren ist rausgelöst in den neuen Sperren-Button je Begegnung), dazu eine
        // neue Übersicht aller gesperrten Begegnungen.
        if ($darfTeamsBearbeiten) {
            $bnSperrenSafe = htmlspecialchars($bnEingeloggt, ENT_QUOTES, 'UTF-8');
            $pwSperrenSafe = htmlspecialchars($pwEingeloggt, ENT_QUOTES, 'UTF-8');
            echo "
            <a href='#backstage_greencard_begegnungen_erstellen' class='admin-menu-button'>Green-Card-Begegnung erstellen</a>
            <a href='#backstage_gesperrte_begegnungen' class='admin-menu-button'>Liste gesperrter Begegnungen</a>
            <h5><br/></h5>
            <style>
                .bs-modal-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; padding: 1rem; background: rgba(0,0,0,0.6); z-index: 20000; }
                .bs-modal-overlay.bs-modal-overlay--offen { display: flex; }
                .bs-modal { max-width: 26rem; width: 100%; background: #1a0f2e; border: 1px solid var(--admin-accent, #8b5cf6); border-radius: 12px; padding: 1.3rem 1.4rem; box-shadow: 0 20px 60px rgba(0,0,0,0.5); text-align: left; }
                .bs-modal h3 { margin: 0 0 0.6rem; }
                .bs-modal p { font-size: 0.9rem; opacity: 0.9; margin: 0 0 1rem; line-height: 1.5; }
                .bs-modal-actions { display: flex; gap: 0.6rem; justify-content: flex-end; }
                .bs-modal-actions button { margin: 0; }
                .bs-modal-cancel { background: rgba(255,255,255,0.1) !important; }
                .bs-modal-confirm { background: #ef4444 !important; }
            </style>
            <div id='bs-modal-overlay' class='bs-modal-overlay' onclick=\"if(event.target===this){begegnungSperrenDialogSchliessen();}\">
                <div class='bs-modal'>
                    <h3>&#128465; Begegnung sperren?</h3>
                    <p><b id='bs-modal-info'></b><br/><br/>
                    Eine gesperrte Begegnung bekommt eine <b>Red Card</b>: sie ist vor dem automatischen Nachrücken geschützt und für die Öffentlichkeit unsichtbar. Ihr könnt das später jederzeit wieder aufheben (Liste gesperrter Begegnungen).</p>
                    <form method='post' action='website_datachange/edit_games.php'>
                        <input type='hidden' name='action' value='Begegnung_Sperren'>
                        " . csrf_field() . "
                        <input type='hidden' name='bn' value='$bnSperrenSafe'>
                        <input type='hidden' name='pw' value='$pwSperrenSafe'>
                        <input type='hidden' name='begegnungIdSperren' id='bs-modal-begegnung-id' value=''>
                        <div class='bs-modal-actions'>
                            <button type='button' class='button bs-modal-cancel' onclick='begegnungSperrenDialogSchliessen();'>Abbrechen</button>
                            <button type='submit' class='button bs-modal-confirm'>Ja, sperren (Red Card)</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                function begegnungSperrenDialogOeffnen(begegnungId, info) {
                    document.getElementById('bs-modal-begegnung-id').value = begegnungId;
                    document.getElementById('bs-modal-info').textContent = info;
                    document.getElementById('bs-modal-overlay').classList.add('bs-modal-overlay--offen');
                }
                function begegnungSperrenDialogSchliessen() {
                    document.getElementById('bs-modal-overlay').classList.remove('bs-modal-overlay--offen');
                }
            </script>
            ";
        }
    }

    // Alle abgeleiteten Anzeige-Werte für EINE Begegnung, die Tabelle UND Turnierbaum brauchen (Team-
    // namen, Begegnungs-ID+Sperren-Button, Green-Card-Punkt, gesperrt-Label, eigene-Zeile-Hervorhebung).
    // Reine Datenaufbereitung, kein Markup für die eigentliche Karte/Zeile - das bleibt Sache des
    // jeweiligen Aufrufers, weil Tabelle und Turnierbaum das unterschiedlich anordnen.
    function ermittleBegegnungsDaten($conn, $rowBegegnung, $rundenName, $darfTeamsBearbeiten, $eigenesTeamId){
        $ko_turnierbaumposition = $rowBegegnung['ko_turnierbaumposition'];
        $heimteamID = $rowBegegnung['fk_heimteam'];
        $auswaertsteamID = $rowBegegnung['fk_auswaertsteam'];
        $begegnungId = $rowBegegnung['id'];
        $siegerteam = $rowBegegnung['fk_siegerteam'];
        $status = $rowBegegnung['status'];
        $istGesperrt = ($status == 6);
        // Auf ausdrücklichen Wunsch nur für Turniermaster/Co-Admin/Admin sichtbar (gleiches Flag wie
        // die Begegnungs-ID/Sperren-Button weiter unten) - für alle anderen (auch eingeloggte Teams)
        // bleibt eine Green-Card-Begegnung optisch nicht von einer normalen Begegnung unterscheidbar.
        $greenCardDot = ($darfTeamsBearbeiten && ($status == 4 || $status == 7)) ? " <span class='green-card-dot' title='Green Card (manuell angelegt)'></span>" : '';
        $gesperrtLabel = $istGesperrt ? " <span class='begegnung-gesperrt-label'>(gesperrt)</span>" : '';

        $begegnungIdAnzeige = '';
        if ($darfTeamsBearbeiten) {
            $begegnungIdAnzeige = "<span style='border:1px solid #22c55e; border-radius:4px; padding:0 0.25rem;' title='Sichtbar für Turniermaster/Co-Admin/Admin'>#$begegnungId</span>";
            if (!$istGesperrt) {
                $begegnungIdAnzeige .= " <button type='button' class='begegnung-sperren-btn' title='Begegnung sperren' onclick=\"begegnungSperrenDialogOeffnen($begegnungId, '" . addslashes($rundenName ?? '') . " #$ko_turnierbaumposition');\">&#128465;</button>";
            }
        }

        $eigeneZeileClass = ($eigenesTeamId && ($heimteamID == $eigenesTeamId || $auswaertsteamID == $eigenesTeamId)) ? ' own-team-row' : '';

        // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber den Teamnamen
        $rowTeam1 = $conn->query('SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . (int)$heimteamID)->fetch_assoc();
        $heimteam = $rowTeam1 ? htmlspecialchars($rowTeam1['name'], ENT_QUOTES, 'UTF-8') : '?';
        $teamId1 = $rowTeam1 ? $rowTeam1['id'] : null;
        $rowTeam2 = $conn->query('SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . (int)$auswaertsteamID)->fetch_assoc();
        $auswaertsteam = $rowTeam2 ? htmlspecialchars($rowTeam2['name'], ENT_QUOTES, 'UTF-8') : '?';
        $teamId2 = $rowTeam2 ? $rowTeam2['id'] : null;

        return [
            'ko_turnierbaumposition' => $ko_turnierbaumposition,
            'begegnungId' => $begegnungId,
            'status' => $status,
            'istGesperrt' => $istGesperrt,
            'greenCardDot' => $greenCardDot,
            'gesperrtLabel' => $gesperrtLabel,
            'begegnungIdAnzeige' => $begegnungIdAnzeige,
            'eigeneZeileClass' => $eigeneZeileClass,
            'heimteamID' => $heimteamID, 'auswaertsteamID' => $auswaertsteamID,
            'teamId1' => $teamId1, 'teamId2' => $teamId2,
            'heimteam' => $heimteam, 'auswaertsteam' => $auswaertsteam,
            'siegerteam' => $siegerteam,
        ];
    }

    // RECHTE-AUDIT: 7. Parameter ($darfTurnierSettingsAendern) ist jetzt NUR noch für "Turnier
    // abschließen" (exklusiv Admin/Co-Admin) zuständig. Green-Card/Sperren/Begegnungs-ID/KO-Einzug-
    // fertig-Toggle hängen seit der Rechte-Erweiterung auf ausdrücklichen Wunsch am neuen letzten
    // Parameter $darfTeamsBearbeiten (teams-Flag = Admin/Co-Admin/Turniermaster), siehe Chat
    // ("Turniermaster soll immer mindestens das können was Backstage kann" + die Liste konkreter
    // Funktionen, die Turniermaster jetzt zusätzlich bedienen darf).
    function printKO_PhaseTabellen($TurnierID, $conn, $istBackstageEingeloggt, $gameEditMode, $expertenmodus, $test_turnier_id, $darfTurnierSettingsAendern = false, $bnEingeloggt = '', $pwEingeloggt = '', $darfZufaelligeSpieleEintragen = false, $darfAlleSpieleBearbeiten = false, $eigenesTeamId = null, $darfTeamsBearbeiten = false){
        //Button, mit dem man den Bearbeitungsmodus starten kann
        printEditModeStuff($conn, $TurnierID, $gameEditMode, $expertenmodus, "#kophase", $test_turnier_id, $darfAlleSpieleBearbeiten, $darfTeamsBearbeiten);

        // CSS immer ausgeben (billig, unabhängig von Rechten) - die eigentlichen Rechte-Prüfungen
        // passieren weiter unten pro Begegnung bzw. bei den beiden Menü-Links direkt danach.
        printBegegnungSperrenUI($darfTeamsBearbeiten, $bnEingeloggt, $pwEingeloggt);

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
                // NEU: "Ihr spielt hier"/"Ihr habt hier gespielt"-Badge auch in der K.-o.-Phase (gab es
                // bisher nur bei Gruppenphase/Losing Bracket) - Gegenwarts-/Vergangenheitsform je nachdem,
                // ob die eigene Begegnung dieser Runde schon finalisiert ist. Siehe Chat.
                $koRundeEigeneBadge = '';
                if ($eigenesTeamId) {
                    $sqlEigeneRunde = 'SELECT status FROM Turnier_Begegnung WHERE ko_finallevel = ' . $ko_finallevel . ' AND status <> 3 AND (fk_heimteam = ' . (int)$eigenesTeamId . ' OR fk_auswaertsteam = ' . (int)$eigenesTeamId . ') ORDER BY id DESC LIMIT 1';
                    $rowEigeneRunde = $conn->query($sqlEigeneRunde)->fetch_assoc();
                    if ($rowEigeneRunde) {
                        $koRundeAbgeschlossen = in_array((int)$rowEigeneRunde['status'], [5, 7], true);
                        $koRundeEigeneBadge = $koRundeAbgeschlossen
                            ? " <span class='own-team-badge own-team-badge--past'>&#10003; Ihr habt hier gespielt</span>"
                            : " <span class='own-team-badge'>&#9654; Ihr spielt hier</span>";
                    }
                }
                echo "<h3>$name$koRundeEigeneBadge</h3>";
            }
            // ============================================================================================
            // TESTMODUS: "Zufällige Spiele eintragen" für GENAU DIESE Finalstufe (nur im Testturnier,
            // türkiser Rahmen) - nur für Admin/Co-Admin/Turniermaster/Backstage-Zugang/Schiedsrichter*in.
            // ============================================================================================
            if ($test_turnier_id != 0 && $darfZufaelligeSpieleEintragen) {
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
                            $bd = ermittleBegegnungsDaten($conn, $rowBegegnung, $name ?? '', $darfTeamsBearbeiten, $eigenesTeamId);
                            $ko_turnierbaumposition = $bd['ko_turnierbaumposition'];
                            $heimteamID = $bd['heimteamID']; $auswaertsteamID = $bd['auswaertsteamID'];
                            $begegnungId = $bd['begegnungId']; $siegerteam = $bd['siegerteam']; $status = $bd['status'];
                            $zellenStyle = $bd['istGesperrt'] ? "opacity: 0.5;" : "";
                            $greenCardDot = $bd['greenCardDot']; $gesperrtLabel = $bd['gesperrtLabel'];
                            $begegnungIdAnzeige = $bd['begegnungIdAnzeige']; $eigeneZeileClass = $bd['eigeneZeileClass'];
                            $heimteam = $bd['heimteam']; $auswaertsteam = $bd['auswaertsteam'];
                            $teamId1 = $bd['teamId1']; $teamId2 = $bd['teamId2'];
                            //Ausgeben
                            if($siegerteam == $heimteamID){
                                echo "<td class='$eigeneZeileClass' style='$zellenStyle'>$ko_turnierbaumposition. <p style='font-size: 10px'>$begegnungIdAnzeige$greenCardDot$gesperrtLabel</p></td><td class='$eigeneZeileClass' style='$zellenStyle background-color:green;word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td class='$eigeneZeileClass' style='$zellenStyle'>"; //Heimteam kommt ganz links hin
                            }else{
                                echo "<td class='$eigeneZeileClass' style='$zellenStyle'>$ko_turnierbaumposition. <p style='font-size: 10px'>$begegnungIdAnzeige$greenCardDot$gesperrtLabel</p></td><td class='$eigeneZeileClass' style='$zellenStyle word-wrap: break-word;'>$heimteam ("; $return = printKuerzelWithLink($conn, $teamId1); echo"$return)</td><td class='$eigeneZeileClass' style='$zellenStyle'>"; //Heimteam kommt ganz links hin
                            }

                            //Spiele zu den Begegnungen finden
                            printGames($TurnierID, $conn, $begegnungId, $gameEditMode, $status, $darfAlleSpieleBearbeiten, $eigenesTeamId);
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
                                echo "</td><td class='$eigeneZeileClass' style='$zellenStyle background-color:green;word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }else{
                                echo "</td><td class='$eigeneZeileClass' style='$zellenStyle word-wrap: break-word;'>$auswaertsteam ("; $return = printKuerzelWithLink($conn, $teamId2); echo"$return)</td>"; //Auswärtsteam kommt ganz rechts hin
                            }

                            echo "</tr><tr>";
                        }
            echo"   </tr>
                </tbody>
            </table>";

            // Direkt unter der ersten Finalstufe: Umschalter für "Gruppenphase vorbei / KO-Einzug fertig".
            // Gehört inhaltlich zu "Einzug ins KO-System" -> teams-Flag (Admin/Co-Admin/Turniermaster).
            // Nur relevant/sichtbar, wenn der Einzug in die K.-o.-Phase laut Turnier Settings überhaupt manuell angelegt wird -
            // im Automatik-Modus berechnet die Website das selbst, dieser Schalter hätte dort keine Wirkung.
            if ($ko_finallevel == $start_ko_finallevel && $darfTeamsBearbeiten) {
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
                    // Funktion mit Rechte-Voraussetzung ist (sichtbar nur mit teams-Flag).
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
        // SICHERHEIT: (int)-Cast schliesst SQL-Injection (defensiv, falls ein Aufrufer irgendwann mal
        // einen nicht schon gecasteten Wert uebergibt); htmlspecialchars() auf $teamKuerzel schliesst
        // gespeichertes XSS ueber das (vom Team selbst frei waehlbare) Team-Kuerzel.
        $teamId = (int)$teamId;
        //KÜRZEL HERAUSFINDEN
        $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = ' . $teamId . ' ORDER BY id';
        $result = $conn->query($sql);
        $teamKuerzel = " ";
        while (!empty($row = $result->fetch_assoc())) {
            $teamKuerzel = $row['kuerzel'];
        }
        return "<a href='?teamId=$teamId#teaminfo'>" . htmlspecialchars($teamKuerzel, ENT_QUOTES, 'UTF-8') . "</a>";
    }

    function printSpielerWithLink($conn, $spielerId){
        // SICHERHEIT: siehe printKuerzelWithLink() oben - gleiches Prinzip.
        $spielerId = (int)$spielerId;
        //KÜRZEL HERAUSFINDEN
        $sql = 'SELECT * FROM Turnier_Spieler_in WHERE id = ' . $spielerId . ' ORDER BY id';
        $result = $conn->query($sql);
        $name = " ";
        while (!empty($row = $result->fetch_assoc())) {
            $name = $row['name'];
        }
        return "<a href='?spielerId=$spielerId#spielerinfo'>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</a>";
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

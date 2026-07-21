<?php
// ================================================================================================
// PAUSENRAUM - wieder aktiviert als 6. (letzter) Button direkt auf der Startseite.
// ================================================================================================
// Der frühere Pausenraum-Inhalt (Sterni Zähler, Bierball Locations, Achievements) nutzte ein eigenes,
// zweites Login-System mit unverschlüsselten SQL-Abfragen direkt aus $_POST (u.a. eine echte SQL-
// Injection über accountId) und ein zweites, mit der heutigen Selbstregistrierung kollidierendes
// "register_account". Bei der Reaktivierung deshalb komplett neu geschrieben: dieselbe zentrale
// Anmeldung wie überall sonst auf der Website ($rollenInfo/getUserRollenInfo), die Benutzer-ID kommt
// ausschließlich aus diesem echten Login (nie aus einem rohen $_POST-Feld), durchgehend Prepared
// Statements, CSRF-Token-Pflicht bei allen Formularen, und alle Nutzereingaben (Location-/Bewertungs-
// namen etc.) werden beim Ausgeben mit htmlspecialchars() escaped (gespeichertes XSS wäre sonst über
// jede beliebige Bewertung möglich gewesen - siehe die Team-/Spielername-XSS-Fixes an anderer Stelle
// der Website).
// ================================================================================================
// Sterni Zähler/Bierball Locations/Achievements sind bewusst Admin/Co-Admin-only (nicht "irgendeine
// Rolle") - $istAdminOderCoAdmin ist dieselbe, schon in index.php berechnete Variable, die auch für
// den Rest der Website Admin/Co-Admin-Funktionen absichert (identisch zur Bernstein-/Orange-Stufe im
// Farb-Legende-System der Settings-Seite).
$pausenraumDarfNutzen = isset($istAdminOderCoAdmin) && $istAdminOderCoAdmin;
?>

<!-- PAUSENRAUM -->
<article id="pausenraum">
    <h1>Pausenraum</h1>
    <p>Willkommen im Pausenraum! Hier findest du Beschäftigung für zwischen den Spielen - oder wenn
    gerade kein Blankiball-Turnier läuft.</p>

    <h2>Blankiball-Simulator 2D</h2>
    <p>Ein ganz kleines Wurfspiel für zwischendurch - zielen, werfen, treffen. Mit richtigem Bier gespielt, nicht nur digital.</p>
    <a href="#blankiball_simulator_2d" class="button primary">&#127918; Zum Blankiball-Simulator 2D</a>

    <p></br></p>
    <!-- Von der Startseite (Footer) hierher verschoben, auf ausdrücklichen Wunsch - vorher lag das
         als CMS-Inhalt im Footer, jetzt fest hier im Pausenraum. Die alte CMS-Version im Footer bleibt
         bestehen, bis sie über den roten "Löschen"-Button im CMS-Bearbeitungsmodus entfernt wird (das
         kann ich als Code-Änderung nicht selbst - siehe Chat). -->
    <h2>Blankiball-Simulator</h2>
    <p>Der Blankiball-Simulator als Video - schau dir an, wie er in echt aussieht.</p>
    <img src="images/Sonstiges/blankiball_simulator.jpg" alt="" style="width:20rem;max-width:100%;"/>
    <br/>
    <a href="#blankiball_simulator" class="button primary">Zum Blankiball-Simulator</a>

    <p></br></p>
    <h2>THE ONE</h2>
    <p>Die eine Trinkspielapp, die alle anderen ersetzt.</p>
    <img src="images/Sonstiges/the_one_logo_weinglas_mit_schriftzug.png" alt="" style="width:20rem;max-width:100%;"/>
    <br/>
    <a href="https://www.instagram.com/app.theone/" class="button primary">Zur App</a>

    <p></br></p>
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>

    <?php
    // ============================================================================================
    // STERNI ZÄHLER / BIERBALL LOCATIONS / ACHIEVEMENTS - Admin/Co-Admin-only
    // ============================================================================================
    // Bewusst ein farbig umrandeter Kasten wie die übrigen Admin/Co-Admin-Funktionen der Website
    // (Bernstein/Orange = --admin-border-coadmin, dieselbe Farbstufe wie in der Farb-Legende der
    // Settings-Seite) - für alle anderen (auch angemeldete Nutzer*innen ohne Admin/Co-Admin-Rolle)
    // komplett unsichtbar, nicht nur als deaktivierte Buttons, damit gar nicht erst auffällt, dass es
    // diesen Bereich überhaupt gibt (gleiche Konvention wie z.B. beim Nutzermanagement-Button).
    if ($pausenraumDarfNutzen) { ?>
    <style>
        .pausenraum-admin-box {
            text-align: left;
            max-width: 32rem;
            margin: 1rem auto;
            padding: 0.9rem 1.1rem;
            border-radius: 8px;
            background: rgba(245, 158, 11, 0.08);
            border: 2px solid var(--admin-border-coadmin, #f59e0b);
        }
        .pausenraum-admin-box h2, .pausenraum-admin-box h3 { margin: 0 0 0.4rem; }
        .pausenraum-admin-box p { margin: 0 0 0.6rem; font-size: 0.85rem; opacity: 0.9; }
    </style>
    <div class="pausenraum-admin-box">
        <h2>Für angemeldete Nutzer*innen</h2>
        <p>Sterni Zähler, Bierball Locations und Achievements - nur für Admins/Co-Admins sichtbar.</p>
        <a href="#sterni_zaehler" class="button primary">Sterni Zähler <img src='images/icon/sterni1.png' width='20' height='20' alt=''></a>
        <br/><br/>
        <a href="#bierball_locations" class="button primary">Bierball Locations</a>
        <br/><br/>
        <a href="#achievements" class="button primary">Achievements</a>
        <h3>&#9888; Hinweis</h3>
        <p>Diese drei Features sind seit Kurzem wieder echt funktionierend (komplett neu geschrieben:
        sicheres Login, Prepared Statements, CSRF-Schutz, XSS-Escaping) - nicht mehr nur eine Vorschau.</p>
    </div>
    <?php } ?>
</article>

<!-- ################################################################################################ -->
<!-- ###  BLANKIBALL-SIMULATOR 2D  ################################################################## -->
<!-- ################################################################################################ -->
<!-- Kleines, in sich geschlossenes Ziel-Wurfspiel (Vanilla-JS + Canvas). Steuerung per Pointer Events
     (funktioniert dadurch identisch mit Maus am PC UND Touch am Handy/Tablet, ohne zwei getrennte
     Code-Pfade). Ablauf: vom Wurfkreis unten nach oben ziehen zum Zielen, loslassen zum Werfen. Bei
     Treffer beginnt eine Zeitmessung ("die andere Mannschaft darf trinken, bis 'Stop!' gerufen wird")
     mit manuellem Stop-Button, weil die tatsächliche Dauer laut Regeln von den Spieler*innen selbst
     bestimmt wird (aufstellen, zurücklaufen, "Stop!" rufen) und nicht von der Website vorgegeben
     werden soll. Absichtlich sehr simpel gehalten ("ganz kleines Basic Spiel") - kein echtes Physik-
     Modell, nur eine einfache geradlinige Flugbahn mit Kollisionsabfrage gegen die Flaschen-Hitboxen. -->
<article id="blankiball_simulator_2d">
    <h1>Blankiball-Simulator 2D</h1>
    <a href="#pausenraum" class="button">Zurück zum Pausenraum</a>
    <p></br></p>

    <style>
        #bbsim-wrap {
            position: relative;
            max-width: 22rem;
            margin: 0 auto;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none;
        }
        #bbsim-canvas {
            display: block;
            width: 100%;
            max-width: 22rem;
            aspect-ratio: 9 / 13;
            margin: 0 auto;
            border-radius: 10px;
            border: 2px solid rgba(139, 92, 246, 0.4);
            background: linear-gradient(180deg, #1b2a1b 0%, #14351a 55%, #0e2a12 100%);
            touch-action: none;
        }
        #bbsim-instructions {
            text-align: left;
            max-width: 22rem;
            margin: 0 auto 1rem;
            padding: 0.9rem 1.1rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(139, 92, 246, 0.25);
            font-size: 0.85rem;
        }
        #bbsim-instructions h3 { margin: 0 0 0.4rem; }
        #bbsim-instructions ul { margin: 0 0 0.6rem; padding-left: 1.2rem; }
        #bbsim-instructions li { margin: 0.2rem 0; }
        #bbsim-status {
            max-width: 22rem;
            margin: 0.8rem auto 0;
            font-size: 0.95rem;
            min-height: 1.4rem;
        }
        #bbsim-status.bbsim-status--drink {
            color: #ffd166;
            font-weight: 700;
        }
        #bbsim-timer {
            font-size: 1.6rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        #bbsim-stop-btn {
            display: none;
            margin-top: 0.6rem;
        }
        #bbsim-reset-btn {
            display: none;
            margin-top: 0.6rem;
        }
    </style>

    <div id="bbsim-wrap">
        <div id="bbsim-instructions">
            <h3>Wie geht's?</h3>
            <ul>
                <li>Vom kleinen Kreis unten mit Maus oder Finger nach oben ziehen, um Richtung &amp; Wurfstärke festzulegen - loslassen zum Werfen.</li>
                <li>Triffst du eine Flasche, läuft die Zeit: die gegnerische Mannschaft darf trinken, bis in echt "Stop!" gerufen wird - dann auf den Stop-Button tippen.</li>
                <li>Die vollständigen Regeln stehen unter <a href="#regeln">Regeln</a> - das hier ersetzt nur den Zielwurf, gespielt wird mit echtem Bier.</li>
            </ul>
            <button id="bbsim-start-btn" class="button primary">Los geht's!</button>
        </div>

        <canvas id="bbsim-canvas" width="330" height="480" style="display:none;"></canvas>
        <div id="bbsim-status"></div>
        <button id="bbsim-stop-btn" class="button primary">&#9209; Stop!</button>
        <button id="bbsim-reset-btn" class="button primary">Nächster Wurf</button>
    </div>

    <script>
    (function(){
        var startBtn = document.getElementById('bbsim-start-btn');
        var instructions = document.getElementById('bbsim-instructions');
        var canvas = document.getElementById('bbsim-canvas');
        var statusEl = document.getElementById('bbsim-status');
        var stopBtn = document.getElementById('bbsim-stop-btn');
        var resetBtn = document.getElementById('bbsim-reset-btn');
        if (!startBtn || !canvas) { return; }
        var ctx = canvas.getContext('2d');

        var W = canvas.width, H = canvas.height;
        var THROW_X = W / 2, THROW_Y = H - 40;
        var BOTTLE_W = 22, BOTTLE_H = 46;
        var bottles = [];
        var ball = null; // {x,y,vx,vy}
        var phase = 'idle'; // idle | aiming | flying | hit | miss
        var aimCurrent = null;
        var drinkStartTime = 0;
        var timerRAF = null;

        function layoutBottles(){
            bottles = [];
            var count = 6;
            var rowY = 70;
            var gap = W / (count + 1);
            for (var i = 1; i <= count; i++) {
                bottles.push({ x: gap * i, y: rowY, w: BOTTLE_W, h: BOTTLE_H, alive: true });
            }
        }

        function resetRound(){
            layoutBottles();
            ball = null;
            phase = 'idle';
            aimCurrent = null;
            statusEl.textContent = '';
            statusEl.className = '';
            stopBtn.style.display = 'none';
            resetBtn.style.display = 'none';
            draw();
        }

        function draw(){
            ctx.clearRect(0, 0, W, H);

            // Boden-/Wurfmarkierung
            ctx.beginPath();
            ctx.arc(THROW_X, THROW_Y, 16, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255,255,255,0.5)';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Flaschen
            bottles.forEach(function(b){
                if (!b.alive) { return; }
                ctx.fillStyle = '#2ecc71';
                ctx.fillRect(b.x - b.w/2, b.y - b.h/2, b.w, b.h * 0.7);
                ctx.fillStyle = '#27ae60';
                ctx.fillRect(b.x - b.w/4, b.y - b.h/2 - b.h*0.3, b.w/2, b.h*0.3);
            });

            // Ball
            if (ball) {
                ctx.beginPath();
                ctx.arc(ball.x, ball.y, 8, 0, Math.PI * 2);
                ctx.fillStyle = '#f5deb3';
                ctx.fill();
            }

            // Ziellinie waehrend des Zielens
            if (phase === 'aiming' && aimCurrent) {
                ctx.beginPath();
                ctx.moveTo(THROW_X, THROW_Y);
                ctx.lineTo(aimCurrent.x, aimCurrent.y);
                ctx.strokeStyle = 'rgba(255,209,102,0.8)';
                ctx.lineWidth = 3;
                ctx.stroke();
            }
        }

        function pointerPos(evt){
            var rect = canvas.getBoundingClientRect();
            var scaleX = W / rect.width, scaleY = H / rect.height;
            var clientX = evt.clientX, clientY = evt.clientY;
            return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
        }

        function onPointerDown(evt){
            if (phase !== 'idle') { return; }
            evt.preventDefault();
            phase = 'aiming';
            aimCurrent = pointerPos(evt);
            draw();
        }

        function onPointerMove(evt){
            if (phase !== 'aiming') { return; }
            evt.preventDefault();
            aimCurrent = pointerPos(evt);
            draw();
        }

        function onPointerUp(evt){
            if (phase !== 'aiming') { return; }
            evt.preventDefault();
            var pos = pointerPos(evt);
            var dx = pos.x - THROW_X;
            var dy = pos.y - THROW_Y;
            // Ziehen nach OBEN wirft nach oben Richtung Flaschen - Wurfrichtung ist daher entgegen der
            // Zugrichtung (Slingshot-Prinzip), Kraft proportional zur Zugdistanz.
            var power = Math.min(Math.sqrt(dx*dx + dy*dy) / 12, 16);
            if (power < 2) { // zu kurzer Zug -> kein Wurf, einfach abbrechen
                phase = 'idle';
                aimCurrent = null;
                draw();
                return;
            }
            var angle = Math.atan2(dy, dx);
            ball = {
                x: THROW_X,
                y: THROW_Y,
                vx: -Math.cos(angle) * power,
                vy: -Math.sin(angle) * power
            };
            phase = 'flying';
            aimCurrent = null;
            requestAnimationFrame(step);
        }

        function step(){
            if (phase !== 'flying' || !ball) { return; }
            ball.x += ball.vx;
            ball.y += ball.vy;

            // Kollision mit Flaschen (einfache Rechteck-Abstandspruefung)
            for (var i = 0; i < bottles.length; i++) {
                var b = bottles[i];
                if (!b.alive) { continue; }
                if (Math.abs(ball.x - b.x) < b.w/2 + 8 && Math.abs(ball.y - b.y) < b.h/2 + 8) {
                    b.alive = false;
                    onHit();
                    return;
                }
            }

            if (ball.x < -20 || ball.x > W + 20 || ball.y < -20 || ball.y > H + 20) {
                onMiss();
                return;
            }

            draw();
            requestAnimationFrame(step);
        }

        function onHit(){
            phase = 'hit';
            ball = null;
            draw();
            statusEl.textContent = 'TREFFER! 🍻 Die andere Mannschaft darf trinken...';
            statusEl.className = 'bbsim-status--drink';
            drinkStartTime = Date.now();
            stopBtn.style.display = 'inline-block';
            resetBtn.style.display = 'none';
            tickTimer();
        }

        function tickTimer(){
            if (phase !== 'hit') { return; }
            var seconds = ((Date.now() - drinkStartTime) / 1000).toFixed(1);
            statusEl.innerHTML = 'TREFFER! 🍻 Trinken, bis "Stop!" gerufen wird - <span id="bbsim-timer">' + seconds + 's</span>';
            timerRAF = requestAnimationFrame(tickTimer);
        }

        function onMiss(){
            phase = 'miss';
            ball = null;
            draw();
            statusEl.textContent = 'Leider daneben! Nächster Versuch...';
            statusEl.className = '';
            resetBtn.style.display = 'inline-block';
        }

        stopBtn.addEventListener('click', function(){
            if (timerRAF) { cancelAnimationFrame(timerRAF); timerRAF = null; }
            var seconds = ((Date.now() - drinkStartTime) / 1000).toFixed(1);
            statusEl.textContent = 'Stop! Getrunken für ' + seconds + 's.';
            statusEl.className = '';
            stopBtn.style.display = 'none';
            resetBtn.style.display = 'inline-block';
            phase = 'stopped';
        });

        resetBtn.addEventListener('click', resetRound);

        canvas.addEventListener('pointerdown', onPointerDown);
        canvas.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);

        startBtn.addEventListener('click', function(){
            instructions.style.display = 'none';
            canvas.style.display = 'block';
            resetRound();
        });
    })();
    </script>

    <p></br></p>
    <a href="#pausenraum" class="button">Zurück zum Pausenraum</a>
    <p></br></p>
    <p></br></p>
</article>

<?php
// ================================================================================================
// STERNI ZÄHLER / BIERBALL LOCATIONS / ACHIEVEMENTS - Backend-Helfer.
// ================================================================================================
// myDb_execute() braucht edit_interface.php, das index.php normalerweise nicht selbst einbindet
// (nur die website_datachange/edit_*.php-Backend-Skripte tun das) - hier explizit nachgeladen.
include_once 'website_datachange/edit_interface.php';

function bbSterniIncrement($conn, $accountId, $TurnierID, $bn) {
    $drink_type = "Sterni";
    $sql = "INSERT INTO Pausenraum_Sterni_Zaehler (fk_account, drink_type) VALUES (?, ?)";
    myDb_execute($conn, $TurnierID, $bn, "pausenraum sterni increment", $sql, array($accountId, $drink_type));
}
function bbSterniReset($conn, $accountId, $TurnierID, $bn) {
    $sql = "DELETE FROM Pausenraum_Sterni_Zaehler WHERE fk_account = ?";
    myDb_execute($conn, $TurnierID, $bn, "pausenraum sterni reset", $sql, array($accountId));
}
function bbSterniAnzahl($conn, $accountId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS anzahl FROM Pausenraum_Sterni_Zaehler WHERE fk_account = ?");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['anzahl'] ?? 0);
}
function bbAchievementEintragen($conn, $accountId, $typeId, $addText, $TurnierID, $bn) {
    $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type, add_text) VALUES (?, ?, ?)";
    myDb_execute($conn, $TurnierID, $bn, "pausenraum achievement", $sql, array($accountId, $typeId, $addText));
}
?>

<!-- ################################################################################################ -->
<!-- ###  STERNI ZÄHLER  ############################################################################# -->
<!-- ################################################################################################ -->
<article id="sterni_zaehler">
    <h1>Sterni Zähler</h1>
    <?php if (!$pausenraumDarfNutzen) { ?>
        <p>Nur für Admins/Co-Admins verfügbar.</p>
    <?php } else {
        $sterniAccountId = $rollenInfo['benutzer_id'];
        $sterniJustIncremented = false;

        // SICHERHEIT: CSRF-Pflicht + Login/Rollen-Check oben - vorher gab es hier gar keine Prüfung,
        // der accountId kam sogar direkt unvalidiert aus $_POST.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sterni_action']) && csrf_verify()) {
            if ($_POST['sterni_action'] === 'increment') {
                bbSterniIncrement($conn, $sterniAccountId, $TurnierID, $bn);
                $sterniJustIncremented = true;
            } else if ($_POST['sterni_action'] === 'reset') {
                bbSterniReset($conn, $sterniAccountId, $TurnierID, $bn);
            }
        }

        $sterniZaehler = bbSterniAnzahl($conn, $sterniAccountId);

        // Meilenstein-Achievements nur direkt NACH einem echten Increment eintragen (nicht bei jedem
        // Seiten-Reload, das war ein Bug in der alten Version - sonst gäbe es bei jedem Neuladen der
        // Seite auf Stand "genau 1/20/50/100" ein weiteres, dupliziertes Achievement).
        if ($sterniJustIncremented) {
            $sterniMeilensteine = [1 => 1, 20 => 3, 50 => 4, 100 => 5];
            if (isset($sterniMeilensteine[$sterniZaehler])) {
                bbAchievementEintragen($conn, $sterniAccountId, $sterniMeilensteine[$sterniZaehler], '', $TurnierID, $bn);
            }
        }
        ?>
        <div style='text-align:center'>
            <h3>Behalte immer den Überblick, wann du wie viel Sterni trinkst</h3>
            <p></br></p>
            <form method='post' action='#sterni_zaehler' style='display:inline;'>
                <button style='width:auto;height:auto;'><img src='images/hermann_logo/export.png' width='200' height='200' alt=''></button>
                <input type='hidden' name='sterni_action' value='increment'/>
                <?php echo csrf_field(); ?>
            </form>
            <h2 style='color:red'>Du hast <b><?php echo $sterniZaehler; ?></b> Sternis getrunken!</h2>
            <a href='#sterni_zaehler_statistik' class='button primary'>Statistik</a>
            <p></p>
            <form method='post' action='#sterni_zaehler' style='display:inline;' onsubmit="return confirm('Zähler wirklich auf 0 zurücksetzen?');">
                <button style='width:auto;height:auto;'>Reset</button>
                <input type='hidden' name='sterni_action' value='reset'/>
                <?php echo csrf_field(); ?>
            </form>
        </div>
    <?php } ?>
    <a href="#pausenraum" class="button">Zurück</a>
    <p></br></p>
</article>

<!-- STERNI ZÄHLER STATISTIK -->
<article id="sterni_zaehler_statistik">
    <h2>Deine Sterni-Zähler-Statistik</h2>
    <a href="#sterni_zaehler" class="button">Zurück</a>
    <p></p>
    <?php if ($pausenraumDarfNutzen) {
        $stmtHist = $conn->prepare("SELECT timestamp, drink_type FROM Pausenraum_Sterni_Zaehler WHERE fk_account = ? ORDER BY id DESC");
        $stmtHist->bind_param("i", $rollenInfo['benutzer_id']);
        $stmtHist->execute();
        $resHist = $stmtHist->get_result();
        echo "<ul class='alt'>";
        while ($rowHist = $resHist->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($rowHist['timestamp']) . " : 1 " . htmlspecialchars($rowHist['drink_type']) . "</li>";
        }
        echo "</ul>";
    } ?>
    <a href="#sterni_zaehler" class="button">Zurück</a>
    <p></br></p>
</article>

<!-- ################################################################################################ -->
<!-- ###  BIERBALL LOCATIONS  ######################################################################## -->
<!-- ################################################################################################ -->
<article id="bierball_locations">
    <h1>Gute Bierball Locations</h1>
    <?php if (!$pausenraumDarfNutzen) { ?>
        <p>Nur für Admins/Co-Admins verfügbar.</p>
    <?php } else { ?>
    <ul class="alt">
        <?php
        $stmtLoc = $conn->prepare("SELECT id, name, description FROM Pausenraum_Location ORDER BY id ASC");
        $stmtLoc->execute();
        $resLoc = $stmtLoc->get_result();
        while ($rowLoc = $resLoc->fetch_assoc()) {
            $locationId = (int)$rowLoc['id'];
            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS - Location-Name/Beschreibung
            // kommen von Nutzer*innen selbst (Formular "Location hinzufügen").
            $locationName = htmlspecialchars($rowLoc['name'], ENT_QUOTES, 'UTF-8');
            $locationDescription = htmlspecialchars($rowLoc['description'], ENT_QUOTES, 'UTF-8');
            echo "<hr>";
            echo "<h2 style='color: green'>$locationName</h2>";
            echo "<p style='color: green'>Beschreibung: $locationDescription</p>";

            $stmtBew = $conn->prepare("SELECT sterne FROM Pausenraum_Location_Bewertung WHERE fk_location = ?");
            $stmtBew->bind_param("i", $locationId);
            $stmtBew->execute();
            $resBew = $stmtBew->get_result();
            $summe = 0; $anzahl = 0;
            while ($rowBew = $resBew->fetch_assoc()) {
                $summe += (int)$rowBew['sterne'];
                $anzahl++;
            }
            if ($anzahl > 0) {
                echo "<p>Durchschnittliche Bewertung: " . round($summe / $anzahl, 1) . " &#9733; ($anzahl Bewertungen)</p>";
            } else {
                echo "<p><i>Noch keine Bewertungen</i></p>";
            }
            ?>
            <form method='post' action='#bierball_locations_bewertungen' style='display:inline;'>
                <input type='hidden' name='location_id' value='<?php echo $locationId; ?>'/>
                <input type='hidden' name='location_name' value='<?php echo $locationName; ?>'/>
                <button type='submit' class='button primary'>Bewertungen ansehen</button>
            </form>
            <?php
        }
        ?>
    </ul>
    <p><br/></p>
    <a href='#bierball_locations_hinzufuegen' class='button primary'>Location hinzufügen</a>
    <?php } ?>
    <ul class="actions">
        <li><a href="#pausenraum" class="button">Zurück</a></li>
    </ul>
    <p></br></p>
</article>

<!-- BIERBALL LOCATIONS: BEWERTUNGEN ANSEHEN -->
<article id="bierball_locations_bewertungen">
    <?php if ($pausenraumDarfNutzen && isset($_POST['location_id'])) {
        $bewLocationId = (int)$_POST['location_id'];
        $bewLocationName = htmlspecialchars(isset($_POST['location_name']) ? $_POST['location_name'] : '', ENT_QUOTES, 'UTF-8');
        echo "<h1 style='color: green'>Bewertungen für $bewLocationName</h1>";
        ?>
        <ul class="alt">
        <?php
        $stmtBew = $conn->prepare("SELECT b.name, b.description, b.sterne, s.Benutzername AS autor FROM Pausenraum_Location_Bewertung b LEFT JOIN System_Benutzer_in s ON s.id = b.autor WHERE b.fk_location = ? ORDER BY b.id DESC");
        $stmtBew->bind_param("i", $bewLocationId);
        $stmtBew->execute();
        $resBew = $stmtBew->get_result();
        while ($rowBew = $resBew->fetch_assoc()) {
            $autorName = $rowBew['autor'] !== null ? $rowBew['autor'] : 'unbekannter Autor';
            echo "<li>" . htmlspecialchars($rowBew['name']) . " | " . htmlspecialchars($rowBew['description']) . " | " . (int)$rowBew['sterne'] . " &#9733; | Autor*in: " . htmlspecialchars($autorName) . "</li>";
        }
        ?>
        </ul>
        <form method='post' action='#bierball_locations_bewertungen_hinzufuegen'>
            <input type='hidden' name='location_id' value='<?php echo $bewLocationId; ?>'/>
            <input type='hidden' name='location_name' value='<?php echo $bewLocationName; ?>'/>
            <button type='submit' class='button primary'>Bewertung hinzufügen</button>
        </form>
    <?php } ?>
    <ul class="actions">
        <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul>
    <p></br></p>
</article>

<!-- BIERBALL LOCATIONS: LOCATION HINZUFÜGEN -->
<article id="bierball_locations_hinzufuegen">
    <h1>Location hinzufügen</h1>
    <?php if ($pausenraumDarfNutzen) { ?>
    <ul class="alt">
        <form action="website_datachange/edit_locations.php" method="POST">
            <input type="text" name="name" class="Eingabe" placeholder="Location" style="color: white" required><br/>
            <input type="text" name="description" class="Eingabe" placeholder="Beschreibung" style="color: white" required><br/>
            <input type='hidden' name='action' value='new_location'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES, 'UTF-8'); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES, 'UTF-8'); ?>'/>
            <input type='hidden' name='TurnierID' value='<?php echo (int)$TurnierID; ?>'/>
            <?php echo csrf_field(); ?>
            <p><button type="submit">Posten</button></p>
        </form>
    </ul>
    <?php } ?>
    <ul class="actions">
        <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul>
    <p></br></p>
</article>

<!-- BIERBALL LOCATIONS: BEWERTUNG HINZUFÜGEN -->
<article id="bierball_locations_bewertungen_hinzufuegen">
    <h1>Bewertung hinzufügen</h1>
    <?php if ($pausenraumDarfNutzen && isset($_POST['location_id'])) {
        $neueBewLocationId = (int)$_POST['location_id'];
        $neueBewLocationName = htmlspecialchars(isset($_POST['location_name']) ? $_POST['location_name'] : '', ENT_QUOTES, 'UTF-8');
        ?>
        <ul class="alt">
            <form action="website_datachange/edit_locations.php" method="POST">
                <input type="text" name="name" class="Eingabe" placeholder="Titel" style="color: white" required><br/>
                <input type="number" min="0" max="5" name="sterne" class="Eingabe" placeholder="Sterne (0-5)" style="color: black" required><br/>
                <input type="text" name="description" class="Eingabe" placeholder="Beschreibung" style="color: white" required><br/>
                <input type='hidden' name='action' value='new_rating'/>
                <input type='hidden' name='fk_location' value='<?php echo $neueBewLocationId; ?>'/>
                <input type='hidden' name='location_name' value='<?php echo $neueBewLocationName; ?>'/>
                <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES, 'UTF-8'); ?>'/>
                <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES, 'UTF-8'); ?>'/>
                <input type='hidden' name='TurnierID' value='<?php echo (int)$TurnierID; ?>'/>
                <?php echo csrf_field(); ?>
                <p><button type="submit">Posten</button></p>
            </form>
        </ul>
    <?php } ?>
    <ul class="actions">
        <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul>
    <p></br></p>
</article>

<!-- ################################################################################################ -->
<!-- ###  ACHIEVEMENTS  ############################################################################## -->
<!-- ################################################################################################ -->
<article id="achievements">
    <h1>Achievements</h1>
    <?php if ($pausenraumDarfNutzen) { ?>
    <ul class="alt">
        <?php
        $stmtAch = $conn->prepare("SELECT a.add_text, t.name AS typeName, s.Benutzername AS autor FROM Pausenraum_Achievement a LEFT JOIN Pausenraum_Achievement_Type t ON t.id = a.fk_type LEFT JOIN System_Benutzer_in s ON s.id = a.fk_account ORDER BY a.id DESC LIMIT 50");
        $stmtAch->execute();
        $resAch = $stmtAch->get_result();
        while ($rowAch = $resAch->fetch_assoc()) {
            $autorName = $rowAch['autor'] !== null ? $rowAch['autor'] : 'unbekannt';
            $typeName = $rowAch['typeName'] !== null ? $rowAch['typeName'] : 'Achievement';
            $addText = $rowAch['add_text'] !== null ? $rowAch['add_text'] : '';
            echo "<li>" . htmlspecialchars($autorName) . " " . htmlspecialchars($typeName) . " " . htmlspecialchars($addText) . "</li>";
        }
        ?>
    </ul>
    <?php } else { ?>
        <p>Nur für Admins/Co-Admins verfügbar.</p>
    <?php } ?>
    <a href="#pausenraum" class="button">Zurück</a>
    <p></br></p>
</article>

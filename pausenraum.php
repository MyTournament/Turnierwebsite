<?php
// ================================================================================================
// PAUSENRAUM - wieder aktiviert als 6. (letzter) Button direkt auf der Startseite.
// ================================================================================================
// Der frühere Pausenraum-Inhalt (Sterni Zähler, Bierball Locations, Achievements) nutzte ein eigenes,
// zweites Login-System mit unverschlüsselten SQL-Abfragen direkt aus $_POST (u.a. eine echte SQL-
// Injection über accountId) und ein zweites, mit der heutigen Selbstregistrierung kollidierendes
// "register_account". Auf ausdrücklichen Wunsch wird das NICHT als echtes, funktionierendes Feature
// reaktiviert, sondern nur noch als rein statische, inerte Vorschau in einer rot umrandeten "Sandbox"-
// Box gezeigt - sichtbar ausschließlich für echte Admins (nicht Co-Admin), als Erinnerung/Vorschau an
// das, was früher hier stand. Keine der Buttons/Links darin ist aktuell funktional.
?>

<!-- PAUSENRAUM -->
<article id="pausenraum">
    <h1>Pausenraum</h1>

    <?php if (isset($istEchterAdmin) && $istEchterAdmin) { ?>
    <style>
        .pausenraum-admin-sandbox {
            text-align: left;
            max-width: 32rem;
            margin: 1rem auto;
            padding: 0.9rem 1.1rem;
            border-radius: 8px;
            background: rgba(122, 32, 32, 0.12);
            border: 2px solid #a33;
        }
        .pausenraum-admin-sandbox h3 { margin: 0 0 0.4rem; color: #ff8a8a; }
        .pausenraum-admin-sandbox p { margin: 0 0 0.6rem; font-size: 0.85rem; opacity: 0.85; }
        .pausenraum-admin-sandbox .button { margin: 0.2rem 0.3rem 0.2rem 0; }
    </style>
    <div class="pausenraum-admin-sandbox">
        <h3>&#9888; Alter Pausenraum-Inhalt (nur für Admins sichtbar)</h3>
        <p>Reine Vorschau/Sandbox - diese alten Funktionen (eigenes Login, Sterni Zähler, Bierball
        Locations, Achievements) sind bewusst NICHT reaktiviert und funktionieren nicht wirklich.
        Niemand außer Admins sieht diese Box.</p>
        <a class="button disabled">Sterni Zähler <img src='images/icon/sterni1.png' width='16' height='16' alt=''></a>
        <a class="button disabled">Bierball Locations</a>
        <a class="button disabled">Achievements</a>
    </div>
    <?php } ?>

    <h2>Blankiball-Simulator 2D</h2>
    <p>Ein ganz kleines Wurfspiel für zwischendurch - zielen, werfen, treffen. Mit richtigem Bier gespielt, nicht nur digital &#128513;</p>
    <a href="#blankiball_simulator_2d" class="button primary">&#127918; Zum Blankiball-Simulator 2D</a>

    <p></br></p>
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
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

// ================================================================================================
// HERBSTLAUB-EFFEKT - Gegenstück zum Schnee-Effekt (snow.js), aktiviert über Turnier_Main.herbstlaub.
// ================================================================================================
// Blätter fallen mit leichtem Pendeln und Drehen von oben nach unten und starten nach Verlassen des
// Sichtbereichs wieder oben. position:fixed + pointer-events:none, damit sie weder Klicks abfangen
// noch Scrollbalken erzeugen. Bei "prefers-reduced-motion" wird der Effekt nicht gestartet.
(function () {
    var BILDER = [
        'images/icon/herbstblatt_orange.svg',
        'images/icon/herbstblatt_rot.svg',
        'images/icon/herbstblatt_gelb.svg'
    ];
    var ANZAHL = 18;

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

    function zufall(min, max) { return min + Math.random() * (max - min); }

    function neuesBlatt(blatt, vonOben) {
        blatt.groesse = zufall(26, 46);
        blatt.x = zufall(0, window.innerWidth - blatt.groesse);
        blatt.y = vonOben ? -blatt.groesse - zufall(0, 200) : zufall(-blatt.groesse, window.innerHeight);
        blatt.fallTempo = zufall(0.6, 1.5);        // px pro Frame
        blatt.pendelWeite = zufall(15, 45);        // px seitlicher Ausschlag
        blatt.pendelTempo = zufall(0.01, 0.03);    // rad pro Frame
        blatt.phase = zufall(0, Math.PI * 2);
        blatt.drehung = zufall(0, 360);
        blatt.drehTempo = zufall(-1.5, 1.5);       // Grad pro Frame
        blatt.el.src = BILDER[Math.floor(Math.random() * BILDER.length)];
        blatt.el.style.width = blatt.groesse + 'px';
        blatt.el.style.height = blatt.groesse + 'px';
    }

    function start() {
        var blaetter = [];
        for (var i = 0; i < ANZAHL; i++) {
            var img = document.createElement('img');
            img.alt = '';
            img.setAttribute('aria-hidden', 'true');
            img.style.cssText = 'position:fixed;top:0;left:0;z-index:1;pointer-events:none;will-change:transform;';
            document.body.appendChild(img);
            var blatt = { el: img };
            neuesBlatt(blatt, false);
            blaetter.push(blatt);
        }

        function schritt() {
            for (var i = 0; i < blaetter.length; i++) {
                var b = blaetter[i];
                b.y += b.fallTempo;
                b.phase += b.pendelTempo;
                b.drehung += b.drehTempo;
                if (b.y > window.innerHeight) { neuesBlatt(b, true); }
                var x = b.x + Math.sin(b.phase) * b.pendelWeite;
                // Innerhalb des Sichtbereichs halten, damit keine horizontalen Scrollbalken entstehen
                x = Math.max(0, Math.min(x, window.innerWidth - b.groesse - 4));
                // Pendelbewegung zusätzlich als leichte Kippung darstellen - wirkt wie echtes Segeln
                var kippung = Math.cos(b.phase) * 25;
                b.el.style.transform = 'translate(' + x + 'px,' + b.y + 'px) rotate(' + (b.drehung + kippung) + 'deg)';
            }
            window.requestAnimationFrame(schritt);
        }
        window.requestAnimationFrame(schritt);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();

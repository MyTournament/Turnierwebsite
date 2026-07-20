<?php
// ================================================================================================
// SESSION-BASIERTE PERSISTENZ FÜR DEN TESTMODUS (analog zur Admin-Login-Session in index.php)
// ================================================================================================
// Vorher hing test_turnier_id ausschließlich an GET/POST-Feldern. Sobald irgendein Formular (z.B.
// Turnierphase ändern) OHNE "?test_turnier_id=..." in der action-URL absendete, ging der Testmodus
// beim Redirect verloren - man landete unvermittelt und OHNE es zu merken wieder in der echten
// Turnier-Ansicht ("aus dem Testturnier rausgeschmissen"), und eine dort danach abgeschickte Änderung
// (z.B. Turnierphase) traf dann fälschlich das echte, laufende Turnier statt des Testturniers.
// Jetzt wird test_turnier_id zusätzlich in der Session gemerkt (eigener Schlüssel
// $_SESSION['test_turnier_id'], kollidiert daher nicht mit der Admin-Login-Session
// $_SESSION['admin_bn']/['admin_pw']) und als Fallback genutzt, wenn weder GET noch POST das Feld
// überhaupt mitschicken - dadurch bleibt man bei jeder Navigation zuverlässig im Testturnier, bis man
// es über "Testmodus verlassen" (setzt $_POST['verlasse_testmodus']) bewusst beendet.
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (isset($_POST['verlasse_testmodus'])) {
    unset($_SESSION['test_turnier_id']);
}

$test_turnier_id = 0;
$test_turnier_id_explizit_uebergeben = false;
if (isset($_GET["test_turnier_id"])) {
    $test_turnier_id = $_GET["test_turnier_id"];
    $test_turnier_id_explizit_uebergeben = true;
} else if (isset($_POST["test_turnier_id"])) {
    $test_turnier_id = $_POST["test_turnier_id"];
    $test_turnier_id_explizit_uebergeben = true;
}
// Nur wenn WEDER GET NOCH POST das Feld überhaupt mitgeschickt haben, aus der Session übernehmen -
// so bleibt ein explizites "?test_turnier_id=0" (falls es das je gäbe) unangetastet.
if (!$test_turnier_id_explizit_uebergeben && !isset($_POST['verlasse_testmodus']) && isset($_SESSION['test_turnier_id'])) {
    $test_turnier_id = $_SESSION['test_turnier_id'];
}
if ($test_turnier_id != 0 && $test_turnier_id !== '' && $test_turnier_id !== null) {
    $_SESSION['test_turnier_id'] = $test_turnier_id;
} else {
    unset($_SESSION['test_turnier_id']);
}

$history_turnier_id = 0;
if (isset($_GET["history_turnier_id"])) { $history_turnier_id = $_GET["history_turnier_id"]; }
if($history_turnier_id == NULL && isset($_POST["history_turnier_id"])){
    $history_turnier_id = $_POST["history_turnier_id"];
}
if ($history_turnier_id != 0) {
    //TurnierID überschreiben weil ich in den Test-Modus möchte
    $TurnierID = $history[$history_turnier_id][1]; //kommt aus variables.php
    $TurnierName = $history[$history_turnier_id][2];
    echo "
    <div style='background-color:#7700FF;'>
    <!--<div style='color:white; text-align: right;'>-->
        <div style='text-align: center;position:fixed; top: 2px; right: 2px;'>
            <!--<form style='color:#00FF00;margin: 0 0 0 0;' method='post' action='/'>
                <button href='/' style='color:white; background-color:#7700FF;' class='button'><p>🚶 Leave 🚶‍♀️</p></button>
            </form>-->
            <a href='/' class='button' style='color:white; background-color:#7700FF;'><p>🚶 Leave 🚶‍♀️</p></a>
        </div>
    <!--</div>-->
    <div style='color:white; text-align: center;'>
        <h3>History</h3>
        <h2>$TurnierName</h2>
    </div> <!-- #7700FF -->
    </div>
    ";
    //echo "<script>console.log('TurnierID: $TurnierID')</script>";
    // button -> name='content'
}else if ($test_turnier_id != 0) {
    //TurnierID überschreiben weil ich in den Test-Modus möchte
    $TurnierID = $testTurniere[$test_turnier_id][1]; //kommt aus variables.php
    $TurnierName = $testTurniere[$test_turnier_id][2];
    // ============================================================================================
    // FIXIERTE TESTMODUS-LEISTE (bleibt wie die Admin-Leiste oben stehen statt wegzuscrollen)
    // ============================================================================================
    // Eigene Farbe (dunkelblau statt violett) zur klaren Unterscheidung von "eingeloggt" (violett).
    // Die Positionierung unterhalb der Admin-Leiste passiert bewusst per JS (nicht per fest
    // verdrahtetem CSS-Pixel-Wert): test_turnier_mode.php wird VOR dem Login-Block gerendert, weiß
    // an dieser Stelle also noch gar nicht, ob/wie hoch die Admin-Leiste nachher wird. Die tatsächliche
    // Höhe von #admin-bar wird daher zur Laufzeit im Browser gemessen und beide Leisten sauber
    // übereinander gestapelt, inkl. passendem Innenabstand für #wrapper.
    echo "
    <style>
        #test-modus-bar { position: fixed; left: 0; width: 100%; z-index: 9998; display: flex; align-items: center; justify-content: center; gap: 0.8rem; padding: 0.4rem 1rem; background: #123a5c; border-bottom: 2px solid #1e5c8f; box-sizing: border-box; color: #ffffff; font-size: 0.85rem; }
        #test-modus-bar form { margin: 0; }
        #test-modus-bar .button { margin: 0; padding: 0.3rem 0.8rem; font-size: 0.75rem; font-weight: 300; background: #1e5c8f; color: #ffffff !important; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var testBar = document.getElementById('test-modus-bar');
            var adminBar = document.getElementById('admin-bar');
            var wrapper = document.getElementById('wrapper');
            var offset = adminBar ? adminBar.offsetHeight : 0;
            testBar.style.top = offset + 'px';
            if (wrapper) {
                wrapper.style.paddingTop = (offset + testBar.offsetHeight) + 'px';
            }
        });
    </script>
    <div id='test-modus-bar'>
        <span>Du befindest dich aktuell im Testmodus! ($TurnierName)</span>
        <form method='post' action='/'>
            <input type='hidden' name='verlasse_testmodus' value='1'>
            <button type='submit' class='button'>Testmodus verlassen</button>
        </form>
    </div>
    ";
    //echo "<script>console.log('TurnierID: $TurnierID')</script>";
    // button -> name='content'
}
?>

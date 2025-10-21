<?php

//IMPORT PHP-DOCS
include_once 'database/db_connection.php'; //Datenbanklogin //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht
//include_once 'database/db_backup.php';

include_once 'variables.php'; //Variablen einbinden (Turniernummer) //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht

// DEBUGGING TEMPLATE
// $log_file_path = substr(stream_resolve_include_path("index.php"), 0, -strlen("index.php"))."debug.log";
// $debug_message = "This is a debug message!\n";
// error_log($debug_message, 3, $log_file_path);

//BULLEREI KOMMT
// Fallback-Initialisierungen für lokale Umgebung
if (!isset($websiteId)) { $websiteId = 1; }
if (!isset($sperrung)) { $sperrung = 0; }
$sqlWebsite = 'SELECT * FROM `System_Website` WHERE id = '. $websiteId .' ORDER BY ID';
$resultWebsite = $conn->query($sqlWebsite);
while ($rowWebsite = $resultWebsite->fetch_assoc()) {
    $sperrung = isset($rowWebsite['sperrung']) ? $rowWebsite['sperrung'] : 0;
}

if($sperrung == 1){
    header("Location: /home.php");
}

include_once 'website_functionalities/load_website.php';
$website_array = determine_domain_id($conn);
$websiteId = 1; //$website_array[0]; //TODO: auch die anderen Websites die der Domain zugeordnet sind irgendwie nutzen #?bersicht
if ($websiteId == null){
    echo "WEBSITE nicht gefunden";
}

// Umgebungspr?fung (Localhost/Private Netzwerke)
if (!function_exists('is_local_env')) {
    function is_local_env(): bool {
        $hosts = [];
        if (isset($_SERVER['REMOTE_ADDR'])) { $hosts[] = $_SERVER['REMOTE_ADDR']; }
        if (isset($_SERVER['SERVER_ADDR'])) { $hosts[] = $_SERVER['SERVER_ADDR']; }
        if (isset($_SERVER['HTTP_HOST']))   { $hosts[] = $_SERVER['HTTP_HOST']; }
        foreach ($hosts as $h) {
            $h = strtolower((string)$h);
            if ($h === 'localhost' || $h === '127.0.0.1' || $h === '::1') { return true; }
            if (strpos($h, 'localhost') !== false || str_ends_with($h, '.local')) { return true; }
            if (preg_match('/^10\./', $h)) { return true; }
            if (preg_match('/^192\.168\./', $h)) { return true; }
            if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $h)) { return true; }
        }
        return false;
    }
}
if (!isset($is_localhost)) { $is_localhost = is_local_env(); }

//TRAFFIC
include_once 'database/traffic_analytics.php';
if (!$is_localhost) { insert_traffic($conn, $websiteId, 'anonym', 3 , ' hat die Website besucht'); }

$sqlAnzahlWebsiteBesuche = 'SELECT COUNT(*) AS c FROM `System_Traffic` WHERE fk_kategorie = 3 AND fk_website = '. (int)$websiteId;
$restultAnzahlWebsiteBesuche = $conn->query($sqlAnzahlWebsiteBesuche);
$anzahlWebsiteBesuche = 0;
if ($restultAnzahlWebsiteBesuche) {
    $rowAnzahlWebsiteBesuche = $restultAnzahlWebsiteBesuche->fetch_assoc();
    if ($rowAnzahlWebsiteBesuche && isset($rowAnzahlWebsiteBesuche['c'])) { $anzahlWebsiteBesuche = (int)$rowAnzahlWebsiteBesuche['c']; }
}
?>

<!DOCTYPE HTML>
<html>
    <head>
        <title>Blankiball Bierball Turnier</title>
        <meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta name="description" content="Berlins größtes Bierball-Turnier">
        <meta name="author" content="Hermann Blankenstein">
		<link rel="stylesheet" href="assets/css/main.css" />
        <meta name="keywords" content="REDACTED, bierball, turnier, flunkyball, bier" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
        <link href="images/icon/logo_export_icon/transparent/favicon-96x96.png" rel="shortcut icon" type="image/png">
        
         <!-- für Galerie -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <link rel="stylesheet" type="text/css" href="assets/css/elastislide.css" />
        <!-- hCaptcha: lazy load only when needed to reduce third-party frames -->
        <?php if (!(isset($is_localhost) && $is_localhost)) { ?>
        <script>
        (function(){
            if (window.__hcaptchaLoaderInit) return; // guard
            window.__hcaptchaLoaderInit = true;
            var loaded = false;
            function loadHcaptcha(){
                if (loaded) return; loaded = true;
                var s = document.createElement('script');
                s.src = 'https://js.hcaptcha.com/1/api.js?recaptchacompat=off';
                s.async = true; s.defer = true;
                document.head.appendChild(s);
            }
            function hasHCaptcha(){ return document.querySelector('.h-captcha'); }
            function setup(){
                try {
                    var io = new IntersectionObserver(function(entries){
                        entries.forEach(function(e){ if (e.isIntersecting) { loadHcaptcha(); io.disconnect(); } });
                    }, {rootMargin: '200px'});
                    document.querySelectorAll('.h-captcha').forEach(function(el){ io.observe(el); });
                } catch(e){ loadHcaptcha(); }
                ['click','focus','touchstart'].forEach(function(ev){ window.addEventListener(ev, loadHcaptcha, {once:true, passive:true}); });
            }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', setup); }
            else { setup(); }

            // On-demand creation for contact form
            function createContactCaptcha(){
                var ph = document.getElementById('contact_captcha_placeholder');
                if (!ph) return;
                if (ph.__hasCaptcha) return; ph.__hasCaptcha = true;
                var d = document.createElement('div');
                d.className = 'h-captcha';
                d.setAttribute('data-sitekey','REDACTED');
                ph.innerHTML = '';
                ph.appendChild(d);
                loadHcaptcha();
                setup();
            }
            window.addEventListener('click', function(ev){ if (ev.target && ev.target.id === 'load_contact_captcha') { createContactCaptcha(); } }, {passive:true});
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', createContactCaptcha, {once:true}); }
            // On-demand creation for register form (if present)
            function createRegisterCaptcha(){
                var ph = document.getElementById('register_captcha_placeholder');
                if (!ph) return;
                if (ph.__hasCaptcha) return; ph.__hasCaptcha = true;
                var d = document.createElement('div');
                d.className = 'h-captcha';
                d.setAttribute('data-sitekey','REDACTED');
                ph.innerHTML = '';
                ph.appendChild(d);
                loadHcaptcha();
                setup();
            }
            window.addEventListener('click', function(ev){ if (ev.target && ev.target.id === 'load_register_captcha') { createRegisterCaptcha(); } }, {passive:true});
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', createRegisterCaptcha, {once:true}); }
        })();
        </script>
        <?php } ?>
        <script>
            // Unterdr?cke laute Debug-Logs aus eingebundenem PHP/JS
            try { if (!window.__suppressLogs) { window.__suppressLogs = true; console.log = function(){}; } } catch(e){}
            // Entsch?rfe doppelte IDs, um DOM-Warnungen zu vermeiden
            (function(){
                var ids = ['benutzercheck','passwdcheck','changegame_bn','changegame_pw','email','kuerzel','message','name','passwort'];
                function deDupe(id){
                    var nodes = document.querySelectorAll('#'+CSS.escape(id));
                    if (nodes.length > 1){
                        for (var i=1;i<nodes.length;i++){
                            var el = nodes[i];
                            // nur anpassen, wenn exakt dieser id-Wert gesetzt ist
                            if (el.id === id) el.id = id + '_' + (i+1);
                        }
                    }
                }
                if (document.readyState === 'loading'){
                    document.addEventListener('DOMContentLoaded', function(){ ids.forEach(deDupe); });
                } else {
                    ids.forEach(deDupe);
                }
            })();
        </script>
        <?php if (!(isset($is_localhost) && $is_localhost)) { ?>
        <link href='https://fonts.googleapis.com/css?family=PT+Sans+Narrow&v1' rel='stylesheet' type='text/css' />
        <link href='https://fonts.googleapis.com/css?family=Pacifico' rel='stylesheet' type='text/css' />
        <?php } ?>

        <!-- für Captcha -->
        <?php if (!(isset($is_localhost) && $is_localhost)) { ?><script src="https://js.hcaptcha.com/1/api.js" async defer></script><?php } ?>
        
        <noscript>
            <style>
                .es-carousel ul {
                    display: block;
                }
            </style>
        </noscript>
        <script id="img-wrapper-tmpl" type="text/x-jquery-tmpl">
            <div class="rg-image-wrapper">
                {{if itemsCount > 1}}
                <div class="rg-image-nav">
                    <a href="#" class="rg-image-nav-prev">Previous Image</a>
                    <a href="#" class="rg-image-nav-next">Next Image</a>
                </div>
                {{/if}}
                <div class="rg-image"></div>
                <div class="rg-loading"></div>
                <div class="rg-caption-wrapper">
                    <div class="rg-caption" style="display:none;">
                        <p></p>
                    </div>
                </div>
            </div>
        </script>
        <!--Ende Galerie -->

        <!-- HOME SCREEN LINK -->
        <!-- AddToHomeScreen entfernt (nicht genutzt / lokal teuer) -->

        <!-- jQuery: lokal aus Assets statt CDN -->
        <?php if (isset($is_localhost) && $is_localhost) { ?>
        <script src="assets/js/jquery.min.js"></script>
        <?php } else { ?>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <?php } ?>
	</head>
<body class="is-preload">

<!-- Wrapper -->
<div id="wrapper">

<?php
    // Debug-Ausgabe entfernt: WebsiteId
    include_once 'website_functionalities/countdown.php';
    include_once 'website_functionalities/test_turnier_mode.php'; //Test-Modus
    include_once 'database/db_update.php'; //Wichtig dass das nach Test-Modus-Abfrage kommt damit das mit aktualisierter TurnierID passiert
    // Localhost erkennen und DB-Update standardm??ig deaktivieren
    $is_localhost = false;
    if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1'])) { $is_localhost = true; }
    if (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'localhost') !== false) { $is_localhost = true; }
    $should_run_update = true;
    if ($is_localhost) {
        $should_run_update = (isset($_POST['run_db_update']) && $_POST['run_db_update'] == '1');
    }
    // Hinweis: im lokalen Testmodus wird db_update nur manuell per Button ausgef?hrt (siehe Banner oben)
    if ($should_run_update) { try{
        db_update($conn, $TurnierID); //db_update.php AUSF?HREN
    }catch (Exception $e) {
        $message = $e->getMessage();
        print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***$message*** ###</i>";
    }catch (Throwable $e) { //Alles was nicht schon vorher abgefangen wird
        print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***unbekannter Fehler*** ###</i>";
    }
    }
    foreach (glob("website_print_functions/*.php") as $filename){
        include_once $filename;
    }
    $siteID = 1; // SITE ID (Für CMS)
    // Lokale Defaults f?r optionale POST-Werte
    if (!isset($_POST['gameEditMode'])) { $_POST['gameEditMode'] = 0; }
    if (!isset($_POST['expertenmodus'])) { $_POST['expertenmodus'] = 0; }
    if (!isset($_POST['bn'])) { $_POST['bn'] = null; }
    if (!isset($_POST['pw'])) { $_POST['pw'] = null; }

    $gameEditMode = 0; //
    $expertenmodus = 0;
    $gameEditMode = $_POST['gameEditMode'];
    $expertenmodus = $_POST['expertenmodus'];

    //ANMELDUNG F?R CMS
    $bn = $_POST["bn"];
    $pw = $_POST["pw"];
    
    $LoggedInWithCMSorHigher = False;
        foreach ($conn->query("SELECT * FROM System_Benutzer_in WHERE fk_rechte <= 5") as $row) {
            if ($bn == $row["Benutzername"] && $pw == $row["Passwort"]) {
                $LoggedInWithCMSorHigher = True;
                $edit_content_mode = False;
            }
        }
    if (!isset($edit_content_mode)) { $edit_content_mode = False; }
    if($LoggedInWithCMSorHigher){
        $edit_content_mode = isset($_POST["edit_content_mode"]) ? $_POST["edit_content_mode"] : False;
        //align-items: right;webkit-align-items: right;
        echo "
        <div style='position: absolute; text-align: right;width:100%'>
            <p style='color: green;text-align: right'>Du bist eingeloggt als <i>$bn</i></p>
            <a style='text-align: right;background-color: green' href='/' class='button'>logout</a>
        
        ";
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='/' method='POST'>";
        }else{ //Testturniere
            echo "<form action='/?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        echo "
        <input type='hidden' name='bn' value='$bn'>
        <input type='hidden' name='pw' value='$pw'>
        ";
        if ($edit_content_mode == True) {
            echo"

            <button type='submit'>normale Ansicht</button>
            </form>
            
            </div>";
        }else if($edit_content_mode == False){
            echo"
            <input type='hidden' name='edit_content_mode' value='True'>
            <button type='submit'>bearbeiten</button>
            </form>
            
            </div>";
        }
    }

?>

<header id="header"> 
    <?php if (isset($is_localhost) && $is_localhost && isset($should_run_update) && !$should_run_update) { ?>
        <div id="local-db-update-banner" style="position:fixed; top:10px; right:10px; z-index:9999; background: rgba(0,0,0,0.7); color:#fff; padding:8px 12px; border-radius:8px; font-size:12px; line-height:1.3; box-shadow:0 2px 8px rgba(0,0,0,0.2); display:flex; align-items:center; gap:8px;">
            <span>Lokaler Modus: Automatisches DB-Update deaktiviert.</span>
            <form method="POST" style="margin:0;">
                <?php
                    // Vorhandene relevante POST-Felder erhalten
                    $preserve_fields = ['bn','pw','edit_content_mode','gameEditMode','expertenmodus'];
                    foreach ($preserve_fields as $f) {
                        if (isset($_POST[$f])) {
                            $v = is_scalar($_POST[$f]) ? (string)$_POST[$f] : json_encode($_POST[$f]);
                            $v = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                            echo "<input type='hidden' name='".$f."' value='".$v."'>";
                        }
                    }
                ?>
                <input type="hidden" name="run_db_update" value="1">
                <button type="submit" style="background:#28a745; color:#fff; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; font-size:12px;">Update jetzt ausf?hren</button>
            </form>
        </div>
    <?php } ?>
    <div > <!-- class="logo" -->
        <!-- <img src="images/icon/sterni1.png" width="70" height="70" border="10" alt="Home"> -->
        <img src="images/sterni_logo/logo_sterni.png" width="150" height=auto border="10" alt="Home">
    </div>
    <div class="content">
        <div class="inner">
            <?php 
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $anzeige_datum = $rowTurnier['anzeige_datum'];
                $anzeige_titel = $rowTurnier['anzeige_titel'];
                $anzeige_subtitel = $rowTurnier['anzeige_subtitel'];
            }
            echo"<h2>$anzeige_datum</h2>";
            echo"<h1>$anzeige_titel</h1>";
            echo"<p>$anzeige_subtitel</p>";
            ?>
            <?php /* cmsPrintSection($websiteId, $siteID, $TurnierID, 8, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); */ ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
        </div>
    </div>
    <!--<button onclick="insert_traffic($conn, 1, 'anonym', 1 , ' hat sich die Regeln angesehen')"> Click2 </button>-->
    <nav>
        <ul>
            <li><a href="#info">Info</a></li>
            <li><a href="#regeln" onclick="insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich die Regeln angesehen');">Regeln</a></li>
            <li class='button'><a href='#teams'>Teams</a></li>
            <?php 
            //Aktuelle Turnierphase herausfinden - erstmal ID
                $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                $resultTurnier = $conn->query($sqlTurnier);
                while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                    $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                    $schnee = $rowTurnier['schnee'];
                }

                //SPIELPLAN
                if($turnier_phase_ID == 4 ||$turnier_phase_ID == 5 || $turnier_phase_ID == 7 || $turnier_phase_ID == 9 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
                    echo"<li><a href='#spielplan' >Spielplan</a></li>";
                    //onclick='"insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich den Spielplan angesehen');"
                }else{
                    echo"<li class='button disabled'><a href='#spielplan'>Spielplan</a></li>";
                }
            ?>    
            <li><a href="https://www.paypal.com/paypalme/REDACTED?country.x=DE&locale.x=de_DE">Spenden</a></li>        
        </ul>
    </nav>
    <div class="content">
        <div class="inner">
            
            
            <!-- Datum rausfinden -->
            <?php
            $sql = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY id';
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $countdown_date = $row['countdown_start'];
            }
            //$countdown_date = "Sep 26, 2022 14:00:00";
            ?>

            <h2 id='demo' style='color: white'></h2>
            <p id="test"></p>
            <script>
            (function(){
                var datum_aus_db = "<?php echo $countdown_date?>"; // "Sep 26, 2022 14:00:00"
                if (!window.__countdownInit) {
                    window.__countdownInit = true;
                    var _cd = new Date(datum_aus_db).getTime();
                    countdown(_cd);
                }
            })();
            </script>
            <!-- "Sep 26, 2022 14:00:00" -->
            <?php //ANMELDUNG
            if($turnier_phase_ID == 1){
                echo"<a href='#anmelden' class='button disabled'>Team anmelden</a>";
                cmsPrintSection($websiteId, $siteID, $TurnierID, 32, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ANMELDEFRIST
            }else if($turnier_phase_ID == 3 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
                echo"<a href='#anmelden' class='button primary'>Team anmelden</a>";
                cmsPrintSection($websiteId, $siteID, $TurnierID, 19, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ANMELDEFRIST
            }else if($turnier_phase_ID == 12){ //WARTELISTE
                echo "<p><i>Hinweis: Der Anmeldezeitraum ist leider schon beendet. Es gibt aber eine Warteliste. Du kannst dein Team also trotzdem noch anmelden, wir können nur nicht versprechen dass wir noch Kapazität haben.</i></p>";
                echo"<a href='#anmelden' class='button primary'>Team anmelden (Warteliste)</a>";
                echo "<br/><br/>";
            } ?>
            <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 5, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
            
            
            
        </div>
    </div>
</header>


<!-- Main -->
<div id="main">

<!-- ###################################################################################################################################################################################################################################### -->
<!-- ################################################################################################## CMS ############################################################################################################################### -->
<!-- ###################################################################################################################################################################################################################################### -->
<!-- CHANGE OR DELETE CONTENT -->
<article id="changecontent">
    <h2>Content ändern</h2>
    <p></p>
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 3, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <?php 
        if (isset($_POST['contentID'])) {
            $cid = $_POST['contentID'];
            $ccontent = isset($_POST['content']) ? $_POST['content'] : null;
            $cstyle = isset($_POST['content_style_tag']) ? $_POST['content_style_tag'] : null;
            $cfunc = isset($_POST['function']) ? $_POST['function'] : null;
            $corder = isset($_POST['content_order_in_group']) ? $_POST['content_order_in_group'] : null;
            changeContent($conn, $TurnierID, $cid, $ccontent, $cstyle, $cfunc, $corder);
        }
    ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- ADD CONTENT -->
<article id="addcontent">
    <h2>Content hinzufügen</h2>
    <p></p>
    <?php if (isset($_POST['contentID'])) { addContent($_POST['contentID'], $TurnierID); } ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="allgemeine_info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 4, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="map">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 6, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- REGELN -->
<article id="regeln">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 1, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- TEAMS -->
<article id="teams">
    <?php //ANMELDUNG
    // Login check
    $loggedInValue = False;
    if (isset($_COOKIE['turnier-loggedin'])) {
        $loggedInValue = $_COOKIE['turnier-loggedin'];
    }

    if($loggedInValue){
        if($turnier_phase_ID == 3 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
            echo"<a href='#anmelden' class='button primary'>Team anmelden</a>";
            cmsPrintSection($websiteId, $siteID, $TurnierID, 19, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ANMELDEFRIST
        }else if($turnier_phase_ID == 12){ //WARTELISTE
            echo "<p><i>Hinweis: Der Anmeldezeitraum ist leider schon beendet. Es gibt aber eine Warteliste. Du kannst dein Team also trotzdem noch anmelden, wir können nur nicht versprechen dass wir noch Kapazität haben.</i></p>";
            echo"<a href='#anmelden' class='button primary'>Team anmelden (Warteliste)</a>";
        }else{
            echo"<a href='#anmelden' class='button disabled'>Team anmelden</a>";
        }
    
        cmsPrintSection($websiteId, $siteID, $TurnierID, 2, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) 
    }else{
        // login form
        echo "<div style='color:white; text-align: center;'>";
            echo "<h2>Turnierpasswort</h2>";
            echo "<p>Aus Datenschutzgründen sind die Personendaten mit einem Passwort geschützt.</p>";
            echo "<p>Das Passwort kannst du bei den Organisator*innen erfragen</p>";
            echo "<form id='turnier-login-form' action='website_functionalities/turnier_logincheck.php' method='POST' autocomplete='on'>";
            echo "<input type='text' name='turnier_username' value='Turnierpasswort' autocomplete='Turnierusername' readonly style='background-color: lightgrey; color: grey;'>";
            echo "<input type='password' class='Eingabe' name='turnier_pw' placeholder='turnierpassword' style='color: white' required>";
            echo "<input type='hidden' name='TurnierID' value='" . $TurnierID . "'/>";
            echo "<input type='hidden' name='NextSection' value='teams'/>";
            
            echo "<script>console.log('index | history_turnier_id = ' + $history_turnier_id  + ';')</script>";
            echo "<script>console.log('index | test_turnier_id = ' + $test_turnier_id + ';')</script>";
            if ($test_turnier_id != NULL) {
                echo "<input type='hidden' name='test_turnier_id' value='" . $test_turnier_id . "'/>";
                //echo "<script>console.log('index -> formular | test_turnier_id = ' + $test_turnier_id  + ';')</script>";
            }
            if ($history_turnier_id != NULL) {
                echo "<input type='hidden' name='history_turnier_id' value='" . $history_turnier_id . "'/>";
                //echo "<script>console.log('index -> formular | history_turnier_id = ' + $history_turnier_id + ';')</script>";
            }
            echo "</br><input type='submit' value='Login'>";
            echo "</form>";
        echo "</div>";
    }
    ?> 
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>
<!-- SPIELER INFO - LOGIN -->                       
<article id="spielerinfo_login">
    <a href="#teams" class="button">Zur?ck zu den Teams</a></br></br>
    <?php 
    $spielerId = isset($_GET['spielerId']) ? $_GET['spielerId'] : null;
    printSpielerInfoLogin($TurnierID, $conn, $spielerId); 
    ?>
    </br></br><a href="#teams" class="button">Zur?ck zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELER INFO -->                       
<article id="spielerinfo">
    <a href="#teams" class="button">Zur?ck zu den Teams</a></br></br>
    <?php 
    $spielerId = isset($_GET['spielerId']) ? $_GET['spielerId'] : null;
    printSpielerInfo($TurnierID, $conn, $spielerId); 
    ?>
    </br></br><a href="#teams" class="button">Zur?ck zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- TEAM INFO -->                       
<article id="teaminfo">
    <a href="#" class="button">Zur?ck zur Startseite</a></br></br>
    <?php 
    $teamId = isset($_GET['teamId']) ? $_GET['teamId'] : null;
    printTeamInfo($TurnierID, $conn, $teamId); 
    ?>
    </br></br><a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN ?BERSICHT -->                       
<article id="spielplan">
    <!-- Lgin check und Spielplan-Anzeige -->
    <?php
    // Login check
    $loggedInValue = False;
    if (isset($_COOKIE['turnier-loggedin'])) {
        $loggedInValue = $_COOKIE['turnier-loggedin'];
    }

    if($loggedInValue){
        // Check if Excel is been used
        $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
        $resultTurnier = $conn->query($sqlTurnier);
        while ($rowTurnier = $resultTurnier->fetch_assoc()) {
            $use_excel = $rowTurnier['use_excel'];
            $excel_link = $rowTurnier['excel_link'];
        }
    
        if($use_excel==0){
            //cmsPrintSection($websiteId, $siteID, $TurnierID, 10, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);  // ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS)
            echo '<h1 class="section-header">Spielplan <img src="images/icon/sterni1.png" width="40" height="40" alt="Home"></h1>

            <!-- <iframe loading="lazy" width="100%" height="600" frameborder="0" scrolling="no" src="https://onedrive.live.com/embed?resid=35950B6E8DF41A30%21156&authkey=%21AMoZmMl3Yb55bbY&em=2&wdAllowInteractivity=False&ActiveCell=Spielplan!A3&wdHideGridlines=True&wdHideHeaders=True&wdDownloadButton=True&wdInConfigurator=True&wdInConfigurator=True&edesNext=false&resen=false" async></iframe>
            -->
            <div class="section-intro" style="text-align:center">

            <p class="muted">Das Turnier besteht aus Gruppenphase und KO-Phase.</p>

            <!-- Alte Bilder ausgeblendet: ersetzen wir durch kompakte Karten mit Icons
            <img src="images/Sonstiges/Gruppenhase.jpg" alt="Gruppenphase Bild"  style="width:30%;"/>
            <img src="images/Sonstiges/KO.jpg" alt="KO-Phase Bild" style="width:30%;"/>
            -->

            <div class="phase-cards">
              <div class="phase-card">
                <h3><img class="icon" src="images/icon/sterni1.png" alt="Icon"> Gruppenphase</h3>
                <p class="muted">Alle Teams werden in Gruppen eingeteilt und spielen dort im Modus Jede*r gegen Jede*n. Die besten Teams jeder Gruppe ziehen in die KO-Phase ein.</p>
                <a href="#gruppenphase" class="button primary">Zur Gruppenphase</a>
              </div>
              <div class="phase-card">
                <h3><img class="icon" src="images/icon/sterni2.png" alt="Icon"> KO-Phase</h3>
                <p class="muted">In der KO-Phase entscheidet jedes Spiel: Sieg bedeutet Weiterkommen - eine Niederlage das Ausscheiden. Verfolge den Weg durch den Turnierbaum.</p>
                <a href="#kophase" class="button primary">Zur KO-Phase</a>
              </div>
              <div class="phase-card">
                <h3><img class="icon" src="images/icon/sterni1.png" alt="Icon"> Losing-Bracket</h3>
                <p class="muted">Im Losing-Bracket geht es f?r ausgeschiedene Teams weiter ? mit Chancen auf bessere Platzierungen und zus?tzliche Matches.</p>
                <a href="#losingbracket" class="button primary">Zum Losing-Bracket</a>
              </div>
            </div>

            </div>';
        }else{
            echo"<h1>Der Spielplan <img src='images/icon/sterni1.png' width='40' height='40' border='10' alt='Home'></h1>
            <iframe loading='lazy' width='100%' height='600' frameborder='0' scrolling='no' src='$excel_link' async></iframe>";
        }

    }else{
        // login form
        echo "</form>";
        echo "<div style='color:white; text-align: center;'>";
            echo "<h2>Turnierpasswort</h2>";
            echo "<p>Aus Datenschutzgründen sind die Personendaten mit einem Passwort geschützt.</p>";
            echo "<p>Das Passwort kannst du bei den Organisator*innen erfragen</p>";
            echo "<form id='turnier-login-form' action='website_functionalities/turnier_logincheck.php' method='POST' autocomplete='on'>";
            echo "<input type='text' name='turnier_username' value='Turnierpasswort' autocomplete='Turnierusername' readonly style='background-color: lightgrey; color: grey;'>";
            echo "<input type='password' class='Eingabe' name='turnier_pw' placeholder='password' style='color: white' required>";
            echo "<input type='hidden' name='TurnierID' value='" . $TurnierID . "'/>";
            echo "<input type='hidden' name='NextSection' value='spielplan'/>";

            echo "<script>console.log('index | history_turnier_id = ' + $history_turnier_id  + ';')</script>";
            echo "<script>console.log('index | test_turnier_id = ' + $test_turnier_id + ';')</script>";
            if ($test_turnier_id != NULL) {
                echo "<input type='hidden' name='test_turnier_id' value='" . $test_turnier_id . "'/>";
                //echo "<script>console.log('index -> formular | test_turnier_id = ' + $test_turnier_id  + ';')</script>";
            }
            if ($history_turnier_id != NULL) {
                echo "<input type='hidden' name='history_turnier_id' value='" . $history_turnier_id . "'/>";
                //echo "<script>console.log('index -> formular | history_turnier_id = ' + $history_turnier_id + ';')</script>";
            }

            echo "</br><input type='submit' value='Login'>";
            echo "</form>";
        echo "</div>";
    }
    ?>
    <a href='#' class='button'>Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN GRUPPEN -->
<article id="gruppen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 28, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#teams" class="button">Zur?ck zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- GRUPPENPHASE - SPIELPLAN -->
<article id="gruppenphase">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 11, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <h1 class="section-header">Gruppenphase</h1>
    <ul class="actions">         
    <li><a href="#punktetabelle" class="button primary">?? Zur Punktetabelle</a></li>            
    </ul>
    <p style="font-size: 0.8rem;"><i>Hinweis: "3:1" bedeutet nicht, dass vier Spiele gemacht wurden, sondern der Spielstand bezieht sich auf ein Spiel, bei dem das Gewinnerteam 3 Flaschen getrunken hat und das Verliererteam aber trotzdem eine Flasche geleert hat. W?rde das Verliererteam keine Flasche leeren, wäre der Spielstand "3:0".</i></p>   
    <?php  printSpielplanGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <a href="#spielplan" class="button">Zur?ck zur ?bersicht</a>  
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                          
</article>
<!-- Punktetabelle -->
<article id="punktetabelle">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 12, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->                      
    <a href="#gruppenphase" class="button">Zur?ck zum Spielplan</a>
    <h1>Punktetabelle der Gruppenphase</h1>
    <?php printPunktetabelleGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?>
    
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>  
</article>
<!-- KO-Phase -->
<article id="kophase">
    <h2>KnockOut-Phase</h2>
    <?php //cmsPrintSection( $websiteId, $siteID, $TurnierID, 13, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <?php printKO_PhaseTabellen($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <a href="#spielplan" class="button">Zur?ck zur ?bersicht</a>
    <p></br></p>
    <p></br></p>
<!-- Losing Bracket -->
</article>
<article id="losingbracket">
    <h1 class="section-header">Losing-Bracket <img src="images/icon/sterni2.png" width="32" height="32" alt="Icon"></h1>
    <div class="note">Teams, die aus der KO-Phase ausgeschieden sind, spielen hier weitere Partien um bessere Platzierungen.</div>
    <?php 
    //cmsPrintSection($websiteId, $siteID, $TurnierID, 35, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);
    // Direkte Ausgabe: nur Losing-Bracket-Gruppe, aber mit den gleichen Tabellen wie Gruppenphase
    include_once 'website_print_functions/table_print_functions.php';
    printSpielplanLosingBracket($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);
    printPunktetabelleLosingBracket($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);
    ?>
    <a href="#kophase" class="button">Zur?ck zur KO-Phase</a>
    <p></br></p> <!-- Abst??nde unten damit Button auf Handys nicht von Cookiewarnung Oberdeckt wird -->
    <p></br></p>  
</article>
<!-- Turnierbaum -->
<article id="turnierbaum">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 29, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->   
    <h1>Turnierbaum der KO-Phase <img src="images/icon/sterni1.png" width="40" height="40" border="10" alt="Home"></h1>
    <p>Hier k?nnt ihr nachverfolgen, wie sich die verschiedenen Matches ergeben. In einem K?stchen steht immer das Gewinnerteam eines Matches und in der Spalte rechts daneben das Gewinnerteam der n?chsten Stufe.</p>
    <?php printTurnierbaum($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus); ?>
    
    <a href="#kophase" class="button">Zur?ck zur KO-Phase</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>  
</article>
<!-- IMPRESSUM -->
<article id="impressum">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 14, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- datenschutzerklärung -->
<article id="datenschutzerklaerung">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 17, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 15, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- ZEITPLAN -->
<article id="zeitplan">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 20, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- FAQ -->
<article id="faq">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 21, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- PLATZHALTER -->
<article id="platzhalter">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 16, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <a href="/" class="button">Zur?ck zur Startseite</a>
    <h5><br/></h5>  
</article>

<!-- schiedsrichter*innen -->
<article id="schiedsrichterinnen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 24, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="REDACTED_simulator">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 30, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- Merch -->
<article id="merch">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 34, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<article id="telefonjoker">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 33, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####--> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<article id="galerie">
    
    <!-- Galerie -->
    <section id="galerie" class="container content-section text-center">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="rules">
                    <h2>Galerie</h2>
                    <p>Ihr habt coole Bilder vom Turnier? Schickt sie uns! </p>
                    
                </div>
            </div>
        </div>
    </section>

    <div class="container">

        <div class="header">
 
            </span>
            <div class="clr"></div>
        </div><!-- header -->
        <div class="content">
<!--            <h1>Nice Pics</h1> -->
            <div id="rg-gallery" class="rg-gallery">
                <div class="rg-thumbs">
                    <!-- Elastislide Carousel Thumbnail Viewer -->
                    <div class="es-carousel-wrapper">
                        <div class="es-nav">
                            <span class="es-nav-prev">Previous</span>
                            <span class="es-nav-next">Next</span>
                        </div>
                        <div class="es-carousel">
                            <ul>
                                <li><a href="#"><img src="images/galerie/thumbs/1.jpg" data-large="images/galerie/1.jpg" alt="image01" data-description="From off a hill whose concave womb reworded" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/2.jpg" data-large="images/galerie/2.jpg" alt="image02" data-description="A plaintful story from a sistering vale" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/3.JPG" data-large="images/galerie/3.JPG" alt="image03" data-description="A plaintful story from a sistering vale" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/4.jpg" data-large="images/galerie/4.jpg" alt="image04" data-description="My spirits to attend this double voice accorded" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/5.jpg" data-large="images/galerie/5.jpg" alt="image05" data-description="And down I laid to list the sad-tuned tale" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/6.jpg" data-large="images/galerie/6.jpg" alt="image06" data-description="Ere long espied a fickle maid full pale" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/7.jpg" data-large="images/galerie/7.jpg" alt="image07" data-description="Tearing of papers, breaking rings a-twain" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/8.jpg" data-large="images/galerie/8.jpg" alt="image08" data-description="Storming her world with sorrow's wind and rain" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/9.jpg" data-large="images/galerie/9.jpg" alt="image09" data-description="Upon her head a platted hive of straw" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/10.jpg" data-large="images/galerie/10.jpg" alt="image10" data-description="Which fortified her visage from the sun" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/11.jpg" data-large="images/galerie/11.jpg" alt="image11" data-description="Whereon the thought might think sometime it saw" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/12.jpg" data-large="images/galerie/12.jpg" alt="image12" data-description="The carcass of beauty spent and done" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/13.jpg" data-large="images/galerie/13.jpg" alt="image13" data-description="Time had not scythed all that youth begun" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/14.jpg" data-large="images/galerie/14.jpg" alt="image14" data-description="Nor youth all quit; but, spite of heaven's fell rage" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/15.jpg" data-large="images/galerie/15.jpg" alt="image15" data-description="Some beauty peep'd through lattice of sear'd age" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/16.jpg" data-large="images/galerie/16.jpg" alt="image16" data-description="Oft did she heave her napkin to her eyne" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/17.jpg" data-large="images/galerie/17.jpg" alt="image17" data-description="Which on it had conceited characters" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/18.jpg" data-large="images/galerie/18.jpg" alt="image18" data-description="Laundering the silken figures in the brine" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/19.jpg" data-large="images/galerie/19.jpg" alt="image19" data-description="That season'd woe had pelleted in tears" /></a></li>
                                <li><a href="#"><img src="images/galerie/thumbs/20.jpg" data-large="images/galerie/20.jpg" alt="image20" data-description="And often reading what contents it bears" /></a></li>
                                
                            </ul>
                        </div>
                    </div>
                    <!-- End Elastislide Carousel Thumbnail Viewer -->
                </div><!-- rg-thumbs -->
            </div><!-- rg-gallery -->
            <p class="sub"></p>
        </div><!-- content -->
    </div><!-- container -->
    
    <a href="#info" class="button">Zur?ck</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="history">
    <h1>Vergangene Turniere</h1>
    <p>Wähle ein Turnier aus der folgenden Liste aus oder klicke unten auf die alte Website.</p>
    <?php history_auswahl($history, $TurnierName); ?>
    <p>Hier geht's zur alten Website (2017-2020)</p>
    <a href="https://2018-20.REDACTED.de" class="button primary">Alte Website (2017-2020)</a>
    <p></br></p>
    <a href="#" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="history_info">
    <div style='background-color:#7700FF;'>
        <div style='color:white; text-align: center;'>
            </br>
            <h1>History</h1>
            <p> Du befindest dich in der History-Ansicht! </p>
            <!--Alle Informationen, die Teams und Spiele betreffen, wurden vom gewünschten Turnier geladen. 
            Alle sonstigen Infos bleiben aber die vom aktuellen Turnier. -->
            <p>Zum verlassen des History-Modus, klicke oben rechts auf "Leave".</p>
            <a href="#" class="button">Ok</a>
            <p></br></p>
        </div> <!-- #7700FF -->
        
    </div>
</article>


<!-- ###################################################################################################################################################################################################################################### -->
<!-- ######################################################################################## Kein CMS #################################################################################################################################### -->
<!-- ###################################################################################################################################################################################################################################### -->

<!-- BRAUCHT KEIN CMS - zu komplex -->
<!-- EDIT GAME -->
<article id="changegame">
    <?php printEditGames($TurnierID, $test_turnier_id); ?>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- BEGEGNUNG VERWALTEN -->
<article id="begegnung_verwalten">
    <?php printEditGames($TurnierID, $test_turnier_id); ?>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- ANMELDEN -->
<article id="anmelden">
        <?php
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $teilnahmebeitrag = $rowTurnier['teilnahmebeitrag'];
            }
        ?>
    <?php printTeamAnmelden($TurnierID, $test_turnier_id, $teilnahmebeitrag); ?>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="sieger_innen_treppe">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="rangliste">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 23, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- BULLEREI KOMMT -->
<article id='bullerei_kommt'>
    <div style='text-align: center'> 
        <?php printBullereiKommt($conn, $websiteId, $TurnierID) ?>
        <a href='#' class='button'>Zur?ck</a>
        <h5><br /></h5>  
    </div>
</article>


<!-- ELEMENTS -->
<?php include_once 'elements.php'; ?>

<!-- PAUSENRAUM: lokal deaktiviert -->
<?php if (!(isset($is_localhost) && $is_localhost)) { include_once 'pausenraum.php'; } ?>

<!-- KONTAKT -->
<article id="kontakt">
    <h2 class="major">Kontakt</h2>
    <p>Falls du Dinge hast, die du uns gerne mitteilen möchtest oder zum Beispiel dein Team wieder abmelden wollen solltest, ist hier der perfekte Ort dafür. Falls du dein Team abmelden möchtest, schreib bitte dein Teampasswort dazu.</p>
    <form method="post" action="website_functionalities/contact.php">
        <div class="fields">
            <div class="field half">
                <label for="name">Name</label>
                <input type="text" name="name" id="name" required/>
            </div>
            <div class="field half">
                <label for="email">Email/Tel</label>
                <input type="text" name="email" id="email" required/>
            </div>
            <div class="field">
                <label for="message">Deine Nachricht</label>
                <textarea name="message" id="message" rows="4" required></textarea>
            </div>
            <br/><br/>
            <div id="contact_captcha_placeholder">
                <button type="button" class="button" id="load_contact_captcha">Captcha laden</button>
            </div>
        </div>
        
        <ul class="actions">
            <li><input type="submit" value="Nachricht senden" class="primary"/></li>
            <input type="hidden" name="action" value="send_message"/>
            <li><input type="reset" value="Abbrechen" /></li>
        </ul>
    </form>
</article>

<!-- ANMELDEN -->
<article id="kontakt_success">
    <p></br></p>
    <h2>Vielen Dank für deine Nachricht!</h2>
    <p>Wir werden dir sobald wie möglich eine Antwort schicken.</p>
    <a href="/" class="button">Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- NEWS -->
<article id="news">
    <h2>NEWS</h2>
    <p></p>
    <ul class="actions">
        <li><a href="#platzhalter" class="button">News erstellen</a></li>
    </ul> 
    <ul class="alt">
        <?php /*
        $sqlNews = 'SELECT * FROM `Content_News` ORDER BY id DESC';
        $resultNews = $conn->query($sqlNews);
        while ($rowNews = $resultNews->fetch_assoc()) {
            $ueberschrift=$rowNews["name"];		
            $newsID=$rowNews["id"];
            echo "<h3>$ueberschrift</h3>";					
            $sqlNewsParagraph = 'SELECT * FROM `Content_News_Parapraph` WHERE `fk_content_news` = ' . $newsID . ' ORDER BY paragraph_order asc, ID desc';  
            $resultNewsParagraph = $conn->query($sqlNewsParagraph);
            TODO: while (!empty($rowNewsParagraph = $resultNewsParagraph->fetch_assoc())) {
                $paragraph=$rowNewsParagraph["paragraph_content"];
                echo "<p>$paragraph</p>";
            }
            echo"<hr>";
        }*/
        ?>
    </ul>
    <p><br/></p> 
    <ul class="actions">
            <li><a href="#info" class="button">Zur?ck</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>


<!-- LOGIN - F?R WORDPRESS -->
<article id="login">
<title>Backstage-Login</title>
    <h2>Anzahl Websitebesuche</h2>
    <?php echo"<p>$anzahlWebsiteBesuche</p>"; ?>

    <p></br></p> 
    
    <h2>Testmodus</h2>
    <p>Der Testmodus ist dafür da, alle Funktionen der Website auszuprobieren. Der Testmous läuft mit einem Test-Turnier mit ausgedachten Teams.</p>
    <form method='post' action='#'>
        <select name='test_turnier_id'>
            <option value='0'><i><?php echo $TurnierName ?></i></option>";
            <?php
            foreach ($testTurniere as &$value){
                $index = $value[0];
                $tName = $value[2];
                echo "<option value=$index>$tName</option>";
            }
            ?>
        </select>
        <!-- <input type='hidden' name='test_turnier_id' value='1'/> -->
        <p></p>  
        <button  name='content' class='button primary'>Testmodus starten</button> 
    </form>

    <p></br></p> 

    <div id="LogIn">
    <h2>Backstage-Bereich</h2>
    <?php 
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='backstage.php' method='POST'>";
    }else{ //Testturniere
        echo "<form action='backstage.php?test_turnier_id=$test_turnier_id' method='POST'>";
    }
    ?>
    <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
    <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <!--<input type="submit" value="Absenden" style="color: black"/> -->
    <p></br></p>
    <button value="Anmelden" type="submit">Anmelden</button>
    </form>

    <p></br></p> 

    <title>Adressbuch</title>
    <div id="LogIn">
    <h2>Content-Management-System</h2>
    <form action="/" method="POST">
        <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
        <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <!--<input type="submit" value="Absenden" style="color: black"/> -->
    <p></br></p>
    <button value="Anmelden" type="submit">Anmelden</button>
    </form>

    <p></br></p>

    <h2>Registrieren</h2>
    <p>Noch keinen Account? Hier kannst du einen erstellen. Sag danach einfach Richard Bescheid, damit er dich freischalten kann.</p>
    <a href='#register_account' class='button primary'>Registrieren</a>

    <p></br></p>
    
    <a href="#pausenraum">?? Pausenraum</a>

    <p></br></p>

    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 18, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
    <a href='#rangliste' class='button primary'>Rangliste</a>
    <a id="bookmark-this" href="#" title="Bookmark This Page">Bookmark This Page</a>

    
    
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- ###################################################################################################################################################################################################################################### -->
<!-- ######################################################################################## SEITEN NACH WEBSITE_DATACHANGE ################################################################################################################################ -->
<!-- ###################################################################################################################################################################################################################################### -->                

<!-- vielendankfuerdeineanmeldung -->
<article id="vielendankfuerdeineanmeldung">
    <div style='text-align: center'>  
        </br>
        <h1>Vielen Dank für deine Anmeldung!</h1>
        <p>Deine Anmeldung wird jetzt bearbeitet und bald kannst du dein Team in der Team-Liste sehen.</a></p>
        </br>
        <h2>Hier kannst du testen ob dein Login funktioniert.</h2>
        </br>
        <?php
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='website_functionalities/logincheck.php' method='POST'>";
        }else{ //Testturniere
            echo "<form action='website_functionalities/logincheck.php?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        ?>
            <input type="text" id="benutzercheck" name="bn" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
            <input type="password" id="passwdcheck" class="Eingabe" name="pw" placeholder="Dein Team-Passwort" style="color: white" required>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
        <!--<input type="submit" value="Absenden" style="color: black"/> -->
        </br>
        <button value="Anmelden" type="submit">Anmelden</button>
        </form>

        </br></br></br>
        <?php
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $teilnahmebeitrag = $rowTurnier['teilnahmebeitrag'];
            }
            if($teilnahmebeitrag == 1){
                echo "
                    <h3><a href=$link_solibeitrag>??Teilnahmebeitrag??</a></h3>
                    <p><b>Nicht vergessen, die 10€ Teilnahmegebühr pro Team per Paypal an kummerkasten@REDACTED.de zu bezahlen! (Verwendungszweck: Euer Teamname)</b> Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website, Sticker und der Rest fließt in Bier fürs Turnier.</p>                  
                    <a class='button' style='background-color: pink; color: black' href='https://paypal.me/REDACTED?country.x=DE&locale.x=de_DE'>Direkt zu Paypal</a>
                ";
            }
        ?>
        
    </div>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- vielendankfuerdeineanmeldung WARTELISTE-->
<article id="vielendankfuerdeineanmeldung_warteliste">
    <div style='text-align: center'>  
        </br>
        <h3>Vielen Dank für deine Anmeldung!</h3>
        <h3>Leider ist die maximale Teamanzahl schon erreicht! Deswegen wurde dein Team einer Warteliste hinzugefügt und kann dann nachrücken, wenn sich andere Teams wieder abmelden. Informiert euch am besten ab und zu mal auf der Website, ob ihr eventuell noch nachgerückt seid.</a></h3>
        </br>
        <h4>Hier kannst du schonmal testen ob dein Login funktioniert.</h4>
        </br>
        <?php
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='website_functionalities/logincheck.php' method='POST'>";
        }else{ //Testturniere
            echo "<form action='website_functionalities/logincheck.php?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        ?>
            <input type="text" id="benutzercheck" name="bn" class="Eingabe" placeholder="Dein Team-Kürzel" style="color: white" required>
            <input type="password" id="passwdcheck" class="Eingabe" name="pw" placeholder="Dein Team-Passwort" style="color: white" required>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
        <!--<input type="submit" value="Absenden" style="color: black"/> -->
        </br>
        <button value="Anmelden" type="submit">Anmelden</button>
        </form>
    </div>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- logincheck_success -->
<article id="logincheck_success">
    <div style='text-align: center'>  
        </br>  
        <h2>Dein Team wurde erfolgreich angemeldet!</h2>  
        </br>
        <?php 
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $link_solibeitrag = $rowTurnier['link_solibeitrag'];
                $link_whatsapp_info = $rowTurnier['link_whatsapp_info'];
                $link_whatsapp_chat = $rowTurnier['link_whatsapp_chat'];
                $link_telegram = $rowTurnier['link_telegram'];
            }
            /*echo"<h3><a href=$link_solibeitrag>??Unterstütze uns??</a></h3>";
            echo"
            <p>Gerne kannst du uns mit einem Solibeitrag unterstützen. Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website und das Grillevent am letzten Tag.</p>                  
            <a class='button' style='background-color: pink; color: black' href='https://paypal.me/REDACTED?country.x=DE&locale.x=de_DE'>Zum Solibeitrag</a> ";
            */
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $teilnahmebeitrag = $rowTurnier['teilnahmebeitrag'];
            }
            if($teilnahmebeitrag == 1){
                echo "
                    <h3><a href=$link_solibeitrag>??Teilnahmebeitrag??</a></h3>
                    <p><b>Nicht vergessen, die 10€ Teilnahmegebühr pro Team per Paypal an kummerkasten@REDACTED.de zu bezahlen! (Verwendungszweck: Euer Teamname)</b> Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website, Sticker und der Rest fließt in Bier fürs Turnier.</p>                  
                    <a class='button' style='background-color: pink; color: black' href='https://paypal.me/REDACTED?country.x=DE&locale.x=de_DE'>Direkt zu Paypal</a>
                ";
            }
            
            
            echo "</br></br></br>
            <h3><img src='images/icon/whatsapp.png' width='20' height='20' border='5' alt='Home'> Komm in die Gruppe</h3>
            <p>Tritt jetzt der Blankiball-Whatsapp-Gruppe bei um alle Turnier-Infos rechtzeitig mitzubekommen!</p> <!-- (... oder der Telegram-Gruppe, falls du kein Whatsapp hast oder Whatsapp kacke findest)-->
            <ul class='actions stacked'>
                <li><a class='button' style='background-color: green' href=$link_whatsapp_info>Offizielle Whatsapp Gruppe</a></li>
                <!--<li><a class='button' style='background-color: green' href=$link_whatsapp_chat>Chat-Gruppe</a></li>-->
                <!--<li><a class='button' style='background-color: blue' href=$link_telegram>Telegram-Gruppe</a></li>-->
                </br>
                <li><a class='button' href='#'>Zur?ck zur Startseite</a></li>
            </ul>
            ";
            
        ?>
        
        
    </div>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- logincheck_failure -->
<article id="logincheck_failure">
    <h1>Login fehlgeschlagen</h1>
    <p>Entweder du hast das Kürzel/Passwort falschgeschrieben oder der Anmeldezeitraum ist abgelaufen und du wurdest jetzt in die Warteliste eingefügt. Falls der Anmeldezeitraum noch läuft, versuche entweder noch einmal dein Team anzumelden oder wende dich an kummerkasten@REDACTED.de</p>
    <a class="button" href='#'>Zur?ck zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- edit_games_success -->
<article id="edit_games_success">
    <h1>Danke für deinen Eintrag!</h1>
    <p>Dein Eintrag sollte direkt auf der Website sichtbar sein. Falls du Fragen oder Probleme hast, wende dich an kummerkasten@REDACTED.de!</p>
    <a class="button" href='#spielplan'>Zum Spielplan</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>		

<!-- edit_games_failure -->
<article id="edit_games_failure">
    <h1>Ups, da ist wohl etwas schiefgelaufen!</h1>
    <p>Vielleicht war dein Passwort falsch, vielleicht hast du nicht die nötigen Rechte. Vielleicht hat Hermann auch einen Fehler gemacht. Falls du Fragen oder Probleme hast, wende dich an kummerkasten@REDACTED.de!</p>
    <a class="button" href='#spielplan'>Zum Spielplan</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>		
               
<!-- ###################################################################################################################################################################################################################################### -->
<!-- ######################################################################################## ENDE DER DIV ################################################################################################################################ -->
<!-- ###################################################################################################################################################################################################################################### -->                
</div>             
                
<!-- ########################## -->
<!-- ########  FOOTER  ######### -->
<!-- ########################## -->  
<footer id="footer">
    <!--SIEGER*INNEN_TREPPE-->
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 

    <!-- Lädt Song runter: style="display: none" autostart='true' <section><embed name='Songtitel' src='assets/audio/kein_bier_mehr_da.opus' border='0' width='152' height='10' style="color: black"  Delay='0' VOLUME='100' loop='true' controls='smallconsole'> </section>  -->
    
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 7, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ?BERGEBEN (Für CMS) #####-->
</footer>

</div>

<!-- ########################## -->
<!-- ########  BG  ######### -->
<!-- ########################## -->  
<div id="bg"></div>

<!-- ########################## -->
<!-- ########  SCRIPTS  ######### -->
<!-- ########################## -->  
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/browser.min.js"></script>
<script src="assets/js/breakpoints.min.js"></script>
<script src="assets/js/util.js"></script>
<script src="assets/js/main.js"></script>

<!-- GALERIE -->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script type="text/javascript" src="assets/js/gallery/jquery.tmpl.min.js"></script>
<script type="text/javascript" src="assets/js/gallery/jquery.easing.1.3.js"></script>
<script type="text/javascript" src="assets/js/gallery/jquery.elastislide.js"></script>
<script type="text/javascript" src="assets/js/gallery/gallery.js"></script>

<!-- ########################## -->
<!-- ########  COOKIES  ######### -->
<!-- ########################## -->  
<?php
if($schnee==1){
    include_once 'assets/js/snow.js';
    echo '<script type="text/javascript">',
        'startSnow();',
     '</script>';
}else{
    include_once 'assets/js/cookies.js';
    echo "<script type='text/javascript' id='cookieinfo'
    src='/assets/js/cookieinfo.min.js' data-linkmsg='Zeig mir diese Cookies &#9733;' data-moreinfo='javascript:start()' data-onclick='javascript:start()' data-expires='1min Wartezeit bis die Cookies gelöscht werden. Zu verändern in der .js Datei'>
    </script>";
}
?>



<!-- BOOK MARK SCRIPT 
<script>
jQuery(function ($) {

$('#bookmark-this').click(function (e) {
  var bookmarkTitle = document.title;
  var bookmarkUrl = window.location.href;

  if ('addToHomescreen' in window && addToHomescreen.isCompatible) {
    // Mobile browsers
    addToHomescreen({ autostart: false, startDelay: 0 }).show(true);
  } else if (/CriOS\//.test(navigator.userAgent)) {
    // Chrome for iOS
    alert('To add to Home Screen, launch this website in Safari, then tap the Share button and select "Add to Home Screen".');
  } else if (window.sidebar && window.sidebar.addPanel) {
    // Firefox <=22
    window.sidebar.addPanel(bookmarkTitle, bookmarkUrl, '');
  } else if ((window.sidebar && /Firefox/i.test(navigator.userAgent) && !Object.fromEntries) || (window.opera && window.print)) {
    // Firefox 23-62 and Opera <=14
    $(this).attr({
      href: bookmarkUrl,
      title: bookmarkTitle,
      rel: 'sidebar'
    }).off(e);
    return true;
  } else if (window.external && ('AddFavorite' in window.external)) {
    // IE Favorites
    window.external.AddFavorite(bookmarkUrl, bookmarkTitle);
  } else {
    // Other browsers (Chrome, Safari, Firefox 63+, Opera 15+)
    alert('Press ' + (/Mac/i.test(navigator.platform) ? 'Cmd' : 'Ctrl') + '+D to bookmark this page.');
  }

  return false;
});

});
</script> -->


	</body>
</html>








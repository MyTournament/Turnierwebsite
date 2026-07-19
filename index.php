<?php
// Start PHP session early so captcha tokens persist via cookie
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Admin-Login (CMS/Backstage) explizit ausloggen, bevor irgendwas anderes passiert
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_bn'], $_SESSION['admin_pw']);
}

//IMPORT PHP-DOCS
include_once 'database/db_connection.php'; //Datenbanklogin //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht
//include_once 'database/db_backup.php';

include_once 'variables.php'; //Variablen einbinden (Turniernummer) //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht

// DEBUGGING TEMPLATE
// $log_file_path = substr(stream_resolve_include_path("index.php"), 0, -strlen("index.php"))."debug.log";
// $debug_message = "This is a debug message!\n";
// error_log($debug_message, 3, $log_file_path);

//BULLEREI KOMMT
// Fallback-Initialisierungen f�r lokale Umgebung
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
$websiteId = 1; //$website_array[0]; //TODO: auch die anderen Websites die der Domain zugeordnet sind irgendwie nutzen #übersicht
if ($websiteId == null){
    echo "WEBSITE nicht gefunden";
}

// Umgebungsprüfung (Localhost/Private Netzwerke)
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
        <meta name="description" content="Blankiball ist Berlins groesstes Bierball- und Flunkyball-Turnier. Infos, Regeln, Teams und Anmeldung.">
        <meta name="author" content="Hermann Blankenstein">
		<link rel="stylesheet" href="assets/css/main.css" />
        <meta name="keywords" content="Blankiball, Bierball, Bierball Berlin, Bierball Turnier, Flunkyball, Flunkyball Turnier, Flunkyball Berlin, Bierball Team Anmeldung, Bierball Regeln, Blankiball Turnier" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
        <link href="images/icon/logo_export_icon/transparent/favicon-96x96.png" rel="shortcut icon" type="image/png">
        
         <!-- f�r Galerie -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <link rel="stylesheet" type="text/css" href="assets/css/elastislide.css" />
        <?php /* JS für Captcha deaktiviert: server-submit Modus */ ?>
        <!-- hCaptcha: Altcode auskommentiert und durch eigenes Bild-Captcha ersetzt
        <?php if (!(isset($is_localhost) && $is_localhost)) { ?>
        <script>
        (function(){ /* hCaptcha Lazy-Loader deaktiviert */ })();
        </script>
        <?php } ?>
        -->
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
        <?php
        // Strukturierte Navigation f�r Suchmaschinen (Sitelinks-Hinweis)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $scheme . '://' . $host;
        $siteNav = [
            ['@type' => 'SiteNavigationElement', 'name' => 'Teams', 'url' => $baseUrl . '/#teams'],
            ['@type' => 'SiteNavigationElement', 'name' => 'Team Anmelden', 'url' => $baseUrl . '/#anmelden'],
            ['@type' => 'SiteNavigationElement', 'name' => 'Vergangene Turniere', 'url' => $baseUrl . '/#history'],
            ['@type' => 'SiteNavigationElement', 'name' => 'Info', 'url' => $baseUrl . '/#info'],
            ['@type' => 'SiteNavigationElement', 'name' => 'Regeln', 'url' => $baseUrl . '/#regeln'],
            ['@type' => 'SiteNavigationElement', 'name' => 'Instagram', 'url' => 'https://www.instagram.com/blankiball_official/?hl=de/'],
        ];
        ?>
        <script type="application/ld+json">
            <?php echo json_encode(['@context' => 'https://schema.org', '@graph' => $siteNav], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
        </script>
        <?php if (!(isset($is_localhost) && $is_localhost)) { ?>
        <link href='https://fonts.googleapis.com/css?family=PT+Sans+Narrow&v1' rel='stylesheet' type='text/css' />
        <link href='https://fonts.googleapis.com/css?family=Pacifico' rel='stylesheet' type='text/css' />
        <?php } ?>

        <!-- f�r Captcha -->
        <?php /* if (!(isset($is_localhost) && $is_localhost)) { ?><script src="https://js.hcaptcha.com/1/api.js" async defer></script><?php } */ ?>
        
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
                    <a href="#" class="rg-image-nav-prev" aria-label="Previous image"></a>
                    <a href="#" class="rg-image-nav-next" aria-label="Next image"></a>
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
        <style>
            /* Eigenes Design nur für die KO-Navigation (Turnierbaum / Rangliste) */
            .ko-phase-cta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.85rem;
                width: 100%;
                margin: 1.5rem 0 0;
            }
            .ko-phase-cta--single {
                max-width: 440px;
            }
            .ko-phase-btn {
                position: relative;
                display: flex;
                flex: 1 1 260px;
                align-items: center;
                justify-content: space-between;
                gap: 0.9rem;
                min-height: 78px;
                padding: 0.9rem 1.35rem;
                border-radius: 18px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                font-weight: 800;
                line-height: 1.35;
                white-space: normal;
                color: #ffffff !important;
                box-shadow: 0 18px 32px rgba(8, 21, 45, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255,255,255,0.08);
                background-clip: padding-box;
                overflow: hidden;
                transition: transform 0.16s ease, box-shadow 0.3s ease, filter 0.35s ease;
            }
            .ko-phase-btn,
            .ko-phase-btn:visited,
            .ko-phase-btn .ko-btn-label,
            .ko-phase-btn .ko-btn-sub {
                color: #ffffff !important;
            }
            .ko-phase-btn::before {
                content: "";
                position: absolute;
                inset: 0;
                background: linear-gradient(120deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 40%);
                mix-blend-mode: screen;
                opacity: 0.8;
                transition: opacity 0.35s ease;
            }
            .ko-phase-btn::after {
                content: "";
                position: absolute;
                right: -18%;
                top: -40%;
                width: 42%;
                height: 180%;
                background: radial-gradient(circle at center, rgba(255,255,255,0.32), rgba(255,255,255,0));
                transform: rotate(18deg);
                opacity: 0.7;
                transition: transform 0.35s ease, opacity 0.35s ease;
            }
            .ko-phase-btn .ko-btn-label {
                font-size: 1.08rem;
                letter-spacing: 0.08em;
            }
            .ko-phase-btn .ko-btn-sub {
                font-size: 0.84rem;
                letter-spacing: 0.02em;
                opacity: 0.95;
                display: block;
            }
            .ko-phase-btn--tree {
                background: linear-gradient(135deg, #15457c 0%, #0f7ed6 45%, #11b7c6 100%);
            }
            .ko-phase-btn--rank {
                background: linear-gradient(135deg, #130b1d 0%, #5d1784 45%, #d24678 100%);
            }
            .ko-phase-btn--points {
                background: linear-gradient(135deg, #0d2f2a 0%, #0f8a6d 45%, #3fc7a9 100%);
            }
            .ko-phase-btn:hover {
                transform: translateY(-1px) scale(1.005);
                box-shadow: 0 22px 38px rgba(8, 21, 45, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.18);
                filter: saturate(1.05);
            }
            .ko-phase-btn:hover::before { opacity: 1; }
            .ko-phase-btn:hover::after {
                opacity: 0.9;
                transform: rotate(12deg) translateX(4%);
            }
            .ko-phase-btn:active {
                transform: translateY(0);
                box-shadow: 0 12px 24px rgba(8, 21, 45, 0.36);
            }
        </style>
	</head>
<body class="is-preload">

<!-- Wrapper -->
<div id="wrapper">

<?php

// Ensure UTF-8 output for correct umlaut rendering
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
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
    $siteID = 1; // SITE ID (F�r CMS)
    // Lokale Defaults für optionale POST-Werte
    if (!isset($_POST['gameEditMode'])) { $_POST['gameEditMode'] = 0; }
    if (!isset($_POST['expertenmodus'])) { $_POST['expertenmodus'] = 0; }
    if (!isset($_POST['bn'])) { $_POST['bn'] = null; }
    if (!isset($_POST['pw'])) { $_POST['pw'] = null; }

    $gameEditMode = 0; //
    $expertenmodus = 0;
    $gameEditMode = $_POST['gameEditMode'];
    $expertenmodus = $_POST['expertenmodus'];

    //ANMELDUNG für CMS & Backstage (gemeinsames Login-Feld)
    // Fallback auf die Session, damit der Login nach einem Redirect (z.B. nach dem Speichern in Edit Data) erhalten bleibt
    $bn = $_POST["bn"] !== null ? $_POST["bn"] : (isset($_SESSION['admin_bn']) ? $_SESSION['admin_bn'] : null);
    $pw = $_POST["pw"] !== null ? $_POST["pw"] : (isset($_SESSION['admin_pw']) ? $_SESSION['admin_pw'] : null);

    include_once 'website_datachange/login_interface.php';
    $rollenInfo = ($bn !== null && $pw !== null) ? getUserRollenInfo($conn, $bn, $pw) : null;
    $rechteFlags = $rollenInfo['flags'] ?? array_fill_keys(['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'], false);

    // Admin (Rolle 1) oder Co-Admin (Rolle 2): dürfen strukturelle Turnier-Settings ändern
    $istAdminOderCoAdmin = $rollenInfo !== null && ($rollenInfo['ist_admin'] || $rollenInfo['ist_co_admin']);
    $LoggedInWithCMSorHigher = $rollenInfo !== null && ($rechteFlags['cms'] || $istAdminOderCoAdmin);
    // Backstage-Bereich: sichtbar für jede Rolle mit mindestens einer der Teil-Berechtigungen -
    // welche einzelnen Backstage-Unterseiten dann konkret zu sehen sind, wird weiter unten je Rolle geprüft
    $LoggedInWithBackstageOrHigher = $rollenInfo !== null && (
        $rechteFlags['backstage'] || $rechteFlags['teams'] || $rechteFlags['alle_spiele']
        || $rechteFlags['cms'] || $rechteFlags['turnier_settings'] || $istAdminOderCoAdmin
    );
    if ($LoggedInWithBackstageOrHigher) {
        // Login in der Session merken, damit er nach einem Redirect (z.B. edit_variables.php, edit_teams.php) erhalten bleibt
        $_SESSION['admin_bn'] = $bn;
        $_SESSION['admin_pw'] = $pw;
    } else {
        unset($_SESSION['admin_bn'], $_SESSION['admin_pw']);
    }
    if (!isset($edit_content_mode)) { $edit_content_mode = False; }
    if($LoggedInWithCMSorHigher){
        $edit_content_mode = isset($_POST["edit_content_mode"]) ? $_POST["edit_content_mode"] : False;
    }
    if ($LoggedInWithCMSorHigher || $LoggedInWithBackstageOrHigher) {
        $adminBarActionUrl = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
        echo "
        <style>
            :root { --admin-accent: #8b5cf6; --admin-accent-deep: #6d28d9; --admin-accent-light: #ddd6fe; }
            #admin-bar { position: fixed; top: 0; left: 0; width: 100%; z-index: 10000; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; padding: 0.5rem 1rem; background: rgba(30, 12, 48, 0.94); border-bottom: 2px solid var(--admin-accent); box-shadow: 0 2px 12px rgba(139, 92, 246, 0.35); box-sizing: border-box; }
            #admin-bar-status { color: var(--admin-accent-light); font-size: 0.8rem; display: flex; align-items: center; gap: 0.6rem; white-space: nowrap; }
            #admin-bar-status i { color: #fff; }
            #admin-bar-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
            #admin-bar-buttons form { margin: 0; display: inline; }
            #admin-bar .button { margin: 0; padding: 0.45rem 0.9rem; font-size: 0.8rem; white-space: nowrap; background: var(--admin-accent-deep); }
            #wrapper { padding-top: 64px; }
            .admin-menu-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; max-width: 640px; margin: 1rem auto; }
            .admin-menu-button { display: inline-block; min-width: 190px; margin: 0; padding: 0.5rem 1rem; font-size: 0.85rem; line-height: 1.2; border-radius: 6px; background: linear-gradient(135deg, var(--admin-accent-deep), var(--admin-accent)); border: 1px solid rgba(255,255,255,0.15); color: #f5f2ff !important; text-transform: none; letter-spacing: 0.02em; text-align: center; text-decoration: none; }
            .admin-menu-button:hover { background: linear-gradient(135deg, var(--admin-accent), #a78bfa); }
            .admin-toggle { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border-radius: 8px; background: rgba(139, 92, 246, 0.12); border: 1px solid var(--admin-accent); color: var(--admin-accent-light); cursor: pointer; }
            .admin-toggle input[type='checkbox'] { accent-color: var(--admin-accent); width: 1.1rem; height: 1.1rem; }
            #main article[id^='backstage_'] { border-top: 3px solid var(--admin-accent); box-shadow: 0 0 24px rgba(139, 92, 246, 0.25); }
        </style>
        <div id='admin-bar'>
            <div id='admin-bar-status'>
                <span>Eingeloggt als <i>$bn</i></span>
                <a href='/?logout=1' class='button' style='background-color:#555'>Logout</a>
            </div>
            <div id='admin-bar-buttons'>
        ";
        if ($LoggedInWithCMSorHigher) {
            echo "<form action='$adminBarActionUrl' method='POST'>
                <input type='hidden' name='bn' value='$bn'>
                <input type='hidden' name='pw' value='$pw'>";
            if ($edit_content_mode == True) {
                echo "<button type='submit' class='button primary'>Website Inhalte verlassen</button>";
            } else {
                echo "<input type='hidden' name='edit_content_mode' value='True'>
                <button type='submit' class='button primary'>Website Inhalte bearbeiten</button>";
            }
            echo "</form>";
        }
        $hatInfosVerlaufZugang = $rechteFlags['backstage'] || $istAdminOderCoAdmin;
        if ($LoggedInWithBackstageOrHigher) {
            echo "<a href='#backstage_daten_bearbeiten' class='button'>Settings</a>";
        }
        if ($hatInfosVerlaufZugang) {
            echo "<a href='#backstage_info' class='button'>Infos</a>";
        }
        echo "
            </div>
        </div>
        ";
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
            <?php /* cmsPrintSection($websiteId, $siteID, $TurnierID, 8, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); */ ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
        </div>
    </div>
    <!--<button onclick="insert_traffic($conn, 1, 'anonym', 1 , ' hat sich die Regeln angesehen')"> Click2 </button>-->
    <nav>
        <ul>
            <li><a href="#info">ℹ️ Info</a></li>
            <li><a href="#regeln" onclick="insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich die Regeln angesehen');">📖 Regeln</a></li>
            <li class='button'><a href='#teams'>👩‍👧‍👦Teams</a></li>
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
                    echo"<li><a href='#spielplan' >🗓️ Spielplan</a></li>";
                    //onclick='"insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich den Spielplan angesehen');"
                }else{
                    echo"<li class='button disabled'><a href='#spielplan'>🗓️ Spielplan</a></li>";
                }
            ?>    
            <li><a href="https://www.paypal.com/paypalme/blankiball?country.x=DE&locale.x=de_DE">♥️ Spenden</a></li>
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
            <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 5, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
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
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 3, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
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
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- ADD CONTENT -->
<article id="addcontent">
    <h2>Content hinzufügen</h2>
    <p></p>
    <?php if (isset($_POST['contentID'])) { addContent($_POST['contentID'], $TurnierID); } ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="allgemeine_info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 4, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#info" class="button">Zurück</a>-_>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="map">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 6, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#info" class="button">Zurück</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- REGELN -->
<article id="regeln">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 1, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- ADVENTSCUP SPECIAL -->
<article id="adventscup-special">
    <h2>Special-Regeln für den Adventscup</h2>
    <p>Zieh per Knopfdruck eine zufällige Sonderregel für den Adventscup.</p>
    <div class="advent-lottery" id="advent-lottery">
        <div class="advent-pot">
            <div class="pot-lid"></div>
            <div class="pot-ribbon"></div>
            <div class="pot-dots">
                <span style="--d:0s;"></span>
                <span style="--d:0.08s;"></span>
                <span style="--d:0.16s;"></span>
                <span style="--d:0.24s;"></span>
            </div>
            <div class="pot-glow"></div>
            <div class="pot-aura"></div>
            <div class="pot-label">Lostopf</div>
        </div>
        <div class="advent-result">
            <p class="muted" id="advent-draw-status">Bereit zum Ziehen</p>
            <h3 id="advent-draw-title">---</h3>
            <p id="advent-draw-text" class="advent-rule-text">Tippe auf den Button, um eine Regel zu ziehen.</p>
            <button type="button" class="button primary" id="advent-draw-btn">Regel ziehen</button>
        </div>
    </div>
    <style>
        #adventscup-special h2 {
            font-size: clamp(2rem, 3vw, 2.6rem);
            margin-bottom: 0.4rem;
        }
        #adventscup-special p {
            font-size: 1rem;
        }
        #advent-lottery {
            display: grid;
            grid-template-columns: minmax(180px, 220px) 1fr;
            gap: 1.2rem;
            align-items: center;
            padding: 1.2rem;
            margin-top: 0.8rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            box-shadow: 0 22px 40px rgba(0,0,0,0.25);
        }
        @media (max-width: 720px) {
            #advent-lottery {
                grid-template-columns: 1fr;
            }
        }
        #advent-lottery .advent-pot {
            position: relative;
            width: 100%;
            padding-top: 100%;
            background: linear-gradient(160deg, #1a2839, #1f3f56 45%, #122031);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05), 0 12px 26px rgba(0,0,0,0.35);
        }
        @media (max-width: 720px) {
            #advent-lottery .advent-pot {
                padding-top: 55%; /* in der mobilen Ansicht etwa halb so hoch */
            }
        }
        #advent-lottery .advent-pot::before {
            content: "";
            position: absolute;
            inset: 10%;
            border-radius: 22px;
            background: conic-gradient(from 0deg, rgba(255,255,255,0.14), rgba(255,255,255,0), rgba(255,255,255,0.22));
            opacity: 0.8;
            filter: blur(8px);
            transform-origin: center;
            animation: potGlow 6s linear infinite;
            pointer-events: none;
        }
        #advent-lottery .advent-pot::after {
            content: "";
            position: absolute;
            inset: 6%;
            border-radius: 22px;
            background:
                linear-gradient(135deg, rgba(255,215,0,0.6), rgba(255,215,0,0)),
                linear-gradient(225deg, rgba(200,0,0,0.3), rgba(200,0,0,0)),
                linear-gradient(0deg, rgba(255,255,255,0.06), rgba(255,255,255,0));
            mix-blend-mode: screen;
            opacity: 0.7;
        }
        #advent-lottery .pot-lid {
            position: absolute;
            top: 12%;
            left: 12%;
            right: 12%;
            height: 14%;
            background: linear-gradient(120deg, #b4232e, #e94545 50%, #b4232e);
            border-radius: 14px 14px 10px 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.25), inset 0 0 0 2px rgba(255,255,255,0.15);
        }
        #advent-lottery.is-drawing .pot-lid {
            animation: lidWobble 0.9s ease-in-out infinite;
            transform-origin: 50% 110%;
        }
        #advent-lottery .pot-ribbon {
            position: absolute;
            inset: 38% 0 38% 0;
            background: linear-gradient(90deg, #c5152f, #f54b4b, #c5152f);
            opacity: 0.9;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.08);
        }
        #advent-lottery .pot-ribbon::after {
            content: "";
            position: absolute;
            left: 50%;
            top: -28%;
            width: 40%;
            height: 70%;
            transform: translateX(-50%) rotate(-2deg);
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.6), rgba(255,255,255,0));
            filter: blur(4px);
        }
        #advent-lottery .pot-dots {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
        }
        #advent-lottery .pot-dots span {
            width: 28%;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffd16b, #ff7f7f 65%, #5a3f7c 100%);
            opacity: 0.15;
            transform: scale(0.7);
        }
        #advent-lottery.is-drawing .pot-dots span {
            animation: lottoBounce 0.9s ease-in-out infinite;
            animation-delay: var(--d);
            opacity: 0.5;
        }
        #advent-lottery .pot-glow {
            position: absolute;
            inset: 28% 14% 14% 14%;
            background: radial-gradient(circle at 50% 35%, rgba(255,255,255,0.25), rgba(255,255,255,0));
            filter: blur(10px);
            pointer-events: none;
        }
        #advent-lottery .pot-aura {
            position: absolute;
            inset: -12%;
            background: radial-gradient(circle at 50% 20%, rgba(255,255,255,0.14), rgba(255,255,255,0));
            filter: blur(22px);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        #advent-lottery.is-drawing .pot-aura {
            opacity: 1;
            animation: auraPulse 1.2s ease-in-out infinite;
        }
        #advent-lottery.is-drawing .advent-pot {
            animation: potShake 0.9s ease-in-out infinite;
        }
        #advent-lottery .pot-label {
            position: absolute;
            bottom: 11%;
            left: 0;
            right: 0;
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
        }
        #advent-lottery .advent-result h3 {
            margin: 0.1rem 0 0.4rem;
            font-size: clamp(1.4rem, 2.8vw, 1.9rem);
        }
        #advent-lottery .advent-rule-text {
            margin-bottom: 0.9rem;
            font-size: 1rem;
            opacity: 0.92;
        }
        #advent-lottery button.button {
            min-width: 180px;
        }
        #advent-lottery .muted {
            opacity: 0.8;
            margin: 0;
        }
        @keyframes lottoBounce {
            0%, 100% { transform: translateY(0) scale(0.82); }
            50% { transform: translateY(-14%) scale(1); opacity: 0.7; }
        }
        @keyframes potGlow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @keyframes auraPulse {
            0%, 100% { opacity: 0.4; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        @keyframes potShake {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-2%) rotate(-1deg); }
            50% { transform: translateY(1%) rotate(1deg); }
            75% { transform: translateY(-1%) rotate(-0.5deg); }
        }
        @keyframes lidWobble {
            0%, 100% { transform: rotate(0deg); }
            30% { transform: rotate(-6deg); }
            60% { transform: rotate(6deg); }
        }
    </style>
    <script>
    (function(){
        var dataUrl = 'assets/data/adventcup_rules.json';
        var drawBtn = document.getElementById('advent-draw-btn');
        var statusEl = document.getElementById('advent-draw-status');
        var titleEl = document.getElementById('advent-draw-title');
        var textEl = document.getElementById('advent-draw-text');
        var lottery = document.getElementById('advent-lottery');
        var rulesCache = null;

        function sanitizeWeight(value) {
            var num = parseInt(value, 10);
            if (isNaN(num) || num < 1) { return 1; }
            return num;
        }

        function pickWeightedRule(rules) {
            var valid = rules.filter(function(rule){ return rule && typeof rule.description === 'string'; });
            if (!valid.length) { return null; }
            var total = valid.reduce(function(sum, rule){
                return sum + sanitizeWeight(rule.weight);
            }, 0);
            var ticket = Math.random() * total;
            for (var i = 0; i < valid.length; i++) {
                ticket -= sanitizeWeight(valid[i].weight);
                if (ticket <= 0) { return valid[i]; }
            }
            return valid[valid.length - 1] || null;
        }

        function endDraw(chosen) {
            if (lottery) { lottery.classList.remove('is-drawing'); }
            if (drawBtn) { drawBtn.disabled = false; }
            if (chosen) {
                statusEl.textContent = 'Gezogene Regel';
                titleEl.textContent = chosen.title || 'Regel';
                textEl.textContent = chosen.description || '';
            } else {
                statusEl.textContent = 'Keine Regel gefunden';
                titleEl.textContent = 'Bitte Datei prüfen';
                textEl.textContent = 'assets/data/adventcup_rules.json';
            }
        }

        function drawRule() {
            if (!drawBtn || !statusEl || !titleEl || !textEl || !lottery) { return; }
            drawBtn.disabled = true;
            lottery.classList.add('is-drawing');
            statusEl.textContent = 'Lostopf mischt...';
            titleEl.textContent = '???';
            textEl.textContent = '...';

            var finalizeDraw = function(chosen){
                setTimeout(function(){ endDraw(chosen); }, 900);
            };

            var onError = function(message){
                if (lottery) { lottery.classList.remove('is-drawing'); }
                if (drawBtn) { drawBtn.disabled = false; }
                statusEl.textContent = 'Konnte Regeln nicht laden';
                titleEl.textContent = 'assets/data/adventcup_rules.json';
                textEl.textContent = message || 'Bitte Datei prüfen.';
            };

            var useRules = function(rules){
                finalizeDraw(pickWeightedRule(rules));
            };

            if (rulesCache) {
                useRules(rulesCache);
                return;
            }

            fetch(dataUrl, { cache: 'no-store' })
                .then(function(response){
                    if (!response.ok) { throw new Error('HTTP ' + response.status); }
                    return response.json();
                })
                .then(function(data){
                    rulesCache = Array.isArray(data) ? data : [];
                    useRules(rulesCache);
                })
                .catch(function(err){
                    onError(err && err.message ? err.message : 'Unbekannter Fehler');
                });
        }

        if (drawBtn) {
            drawBtn.addEventListener('click', drawRule);
        }
    })();
    </script>
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

    //if($loggedInValue){ //Wieder einkommentieren wenn login wieder soll
    if(TRUE){
        if($turnier_phase_ID == 3 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13){
            echo"<a href='#anmelden' class='button primary'>Team anmelden</a>";
            cmsPrintSection($websiteId, $siteID, $TurnierID, 19, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ANMELDEFRIST
        }else if($turnier_phase_ID == 12){ //WARTELISTE
            echo "<p><i>Hinweis: Der Anmeldezeitraum ist leider schon beendet. Es gibt aber eine Warteliste. Du kannst dein Team also trotzdem noch anmelden, wir k�nnen nur nicht versprechen dass wir noch Kapazit�t haben.</i></p>";
            echo"<a href='#anmelden' class='button primary'>Team anmelden (Warteliste)</a>";
        }else{
            echo"<a href='#anmelden' class='button disabled'>Team anmelden</a>";
        }
    
        cmsPrintSection($websiteId, $siteID, $TurnierID, 2, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); // ALS PARAMETER SECTION ID überGEBEN (F�r CMS) 
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
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>                 
</article>
<!-- SPIELER INFO - LOGIN -->                       
<article id="spielerinfo_login">
    <!--<a href="#teams" class="button">Zurück zu den Teams</a></br></br>-->
    <?php 
    $spielerId = isset($_GET['spielerId']) ? $_GET['spielerId'] : null;
    printSpielerInfoLogin($TurnierID, $conn, $spielerId); 
    ?>
    <!--</br></br><a href="#teams" class="button">Zurück zu den Teams</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELER INFO -->                       
<article id="spielerinfo">
    <!--<a href="#teams" class="button">Zurück zu den Teams</a></br></br>-->
    <?php 
    $spielerId = isset($_GET['spielerId']) ? $_GET['spielerId'] : null;
    printSpielerInfo($TurnierID, $conn, $spielerId); 
    ?>
    <!--</br></br><a href="#teams" class="button">Zurück zu den Teams</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- TEAM INFO -->                       
<article id="teaminfo">
    <!--<a href="#" class="button">Zurück zur Startseite</a></br></br>-->
    <?php 
    $teamId = isset($_GET['teamId']) ? $_GET['teamId'] : null;
    printTeamInfo($TurnierID, $conn, $teamId); 
    ?>
    <!--</br></br><a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN überSICHT -->                       
<article id="spielplan">
    <!-- Lgin check und Spielplan-Anzeige -->
    <?php
    // Login check
    $loggedInValue = False;
    if (isset($_COOKIE['turnier-loggedin'])) {
        $loggedInValue = $_COOKIE['turnier-loggedin'];
    }

    //if($loggedInValue){ //Wieder einkommentieren wenn login wieder soll
    if(TRUE){
        // Check if Excel is been used
        $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
        $resultTurnier = $conn->query($sqlTurnier);
        while ($rowTurnier = $resultTurnier->fetch_assoc()) {
            $use_excel = $rowTurnier['use_excel'];
            $excel_link = $rowTurnier['excel_link'];
        }
    
        if($use_excel==0){
            //cmsPrintSection($websiteId, $siteID, $TurnierID, 10, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);  // ALS PARAMETER SECTION ID überGEBEN (F�r CMS)
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
                <h3><img class="icon" src="images/icon/logo_export_icon/transparent/favicon-96x96.png" alt="Icon"> Losing-Bracket</h3>
                <p class="muted">Im Losing-Bracket geht es für ausgeschiedene Teams weiter - mit Chancen auf eine bessere Endplatzierung und zusätzliche Matches.</p>
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
            echo "<p>Aus Datenschutzgr�nden sind die Personendaten mit einem Passwort gesch�tzt.</p>";
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
    <!--<a href='#' class='button'>Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN GRUPPEN -->
<article id="gruppen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 28, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#teams" class="button">Zurück zu den Teams</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- GRUPPENPHASE - SPIELPLAN -->
<article id="gruppenphase">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 11, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <h1 class="section-header">Gruppenphase</h1>
    <div class='note' style="font-size: 0.8rem;">Hinweis: "3:1" bedeutet nicht, dass vier Spiele gemacht wurden, sondern der Spielstand bezieht sich auf ein Spiel, bei dem das Gewinnerteam 3 Flaschen getrunken hat und das Verliererteam aber trotzdem eine Flasche geleert hat. Würde das Verliererteam keine Flasche leeren, wäre der Spielstand "3:0".</div>
    <div class="ko-phase-cta ko-phase-cta--single">
        <a href="#punktetabelle" class="button primary ko-phase-btn ko-phase-btn--points">
            <span class="ko-btn-label">Punktetabelle</span>
            <span class="ko-btn-sub">Gruppenphase</span>
        </a>
    </div>
    <br/><br/>
    <?php  printSpielplanGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <!--<a href="#spielplan" class="button">Zurück zur übersicht</a>  -->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>                          
</article>
<!-- Punktetabelle -->
<article id="punktetabelle">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 12, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->                      
    <!--<a href="#gruppenphase" class="button">Zurück zum Spielplan</a>-->
    <h1>Punktetabelle der Gruppenphase</h1>
    <?php printPunktetabelleGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>  
</article>
<!-- KO-Phase -->
<article id="kophase">
    <h2>KnockOut-Phase</h2>
    <div class="ko-phase-cta">
        <a href="#turnierbaum" class="button primary ko-phase-btn ko-phase-btn--tree">
            <span class="ko-btn-label">Turnierbaum</span>
            <span class="ko-btn-sub">Alle KO-Matches</span>
        </a>
        <a href="#rangliste" class="button primary ko-phase-btn ko-phase-btn--rank">
            <span class="ko-btn-label">Rangliste</span>
            <span class="ko-btn-sub">Live-Positionen</span>
        </a>
    </div>
    <br/><br/>
    <?php //cmsPrintSection( $websiteId, $siteID, $TurnierID, 13, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <?php printKO_PhaseTabellen($TurnierID, $conn, $LoggedInWithBackstageOrHigher, $gameEditMode, $expertenmodus, $test_turnier_id, $istAdminOderCoAdmin, $bn, $pw); ?>
    <!--<a href="#spielplan" class="button">Zurück zur übersicht</a>-->
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
    <!--<a href="#spielplan" class="button">Zurück zur Übersicht</a>-->
    <div class="ko-phase-cta">
        <a href="#rangliste" class="button primary ko-phase-btn ko-phase-btn--rank">
            <span class="ko-btn-label">Rangliste</span>
            <span class="ko-btn-sub">Losing-Bracket</span>
        </a>
    </div>
    <p></br></p> <!-- Abst??nde unten damit Button auf Handys nicht von Cookiewarnung Oberdeckt wird -->
    <p></br></p>  
</article>
<!-- Turnierbaum -->
<article id="turnierbaum">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 29, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->   
    <h1>Turnierbaum der KO-Phase <img src="images/icon/sterni1.png" width="40" height="40" border="10" alt="Home"></h1>
    <div class="note">Hier könnt ihr nachverfolgen, wie sich die verschiedenen Matches ergeben. In einem Kästchen steht immer das Gewinnerteam eines Matches und in der Spalte rechts daneben das Gewinnerteam der nächsten Stufe.</div>
    <?php printTurnierbaum($TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus); ?>
    
    <!--<a href="#kophase" class="button">Zurück zur KO-Phase</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>  
</article>
<!-- IMPRESSUM -->
<article id="impressum">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 14, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- datenschutzerkl�rung -->
<article id="datenschutzerklaerung">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 17, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 15, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- ZEITPLAN -->
<article id="zeitplan">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 20, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="#info" class="button">Zurück</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- FAQ -->
<article id="faq">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 21, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="#info" class="button">Zurück</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- PLATZHALTER -->
<article id="platzhalter">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 16, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <!--<a href="/" class="button">Zurück zur Startseite</a>-->
    <h5><br/></h5>  
</article>

<!-- schiedsrichter*innen -->
<article id="schiedsrichterinnen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 24, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#info" class="button">Zurück</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="blankiball_simulator">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 30, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- Merch -->
<article id="merch">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 34, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<article id="telefonjoker">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 33, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####--> 
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<?php
    // Bilder aus dem Galerie-Ordner dynamisch laden
    $galleryBaseDir = 'images/galerie';
    $galleryDir = __DIR__ . '/images/galerie';
    $galleryImages = [];
    $allowedGalleryExt = ['jpg','jpeg','png','gif','webp','JPG','JPEG','PNG','GIF','WEBP'];
    if (is_dir($galleryDir)) {
        $files = array_filter(scandir($galleryDir), function($file) use ($galleryDir, $allowedGalleryExt) {
            if ($file === '.' || $file === '..') { return false; }
            if (is_dir($galleryDir . '/' . $file)) { return false; }
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            return $ext !== '' && in_array($ext, $allowedGalleryExt, true);
        });
        natcasesort($files);
        foreach ($files as $file) {
            $galleryImages[] = [
                'full' => $galleryBaseDir . '/' . $file,
                'thumb' => is_file($galleryDir . '/thumbs/' . $file) ? $galleryBaseDir . '/thumbs/' . $file : $galleryBaseDir . '/' . $file,
            ];
        }
    }
?>
<style>
    /* Galerie-Navigation mit eigenen Buttons */
    #galerie .rg-image-wrapper {
        position: relative;
    }
    #galerie .rg-image-nav {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        pointer-events: none;
    }
    #galerie .rg-image-nav a {
        position: relative;
        width: 56px;
        height: 56px;
        background: rgba(0, 0, 0, 0.45) url('images/galerie_buttons/nav.png') no-repeat left center;
        background-size: 200% 100%;
        border-radius: 50%;
        text-indent: -9999px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.12);
        transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.2s ease;
        pointer-events: auto;
        margin: 0 6px;
    }
    #galerie .rg-image-nav a.rg-image-nav-prev {
        background-position: left center;
    }
    #galerie .rg-image-nav a.rg-image-nav-next {
        background-position: right center;
    }
    #galerie .rg-image-nav a:hover {
        transform: scale(1.05);
        background-color: rgba(0, 0, 0, 0.65);
        box-shadow: 0 14px 26px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.18);
    }
    /* Thumbnails-Navigation mit eigenen Buttons */
    #galerie .es-nav span {
        background-image: url('images/galerie_buttons/nav_thumbs.png');
        width: 18px;
        height: 32px;
    }
</style>
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
                                <?php if (!empty($galleryImages)) { ?>
                                <?php foreach ($galleryImages as $img) { ?>
                                <li><a href="#"><img src="<?php echo htmlspecialchars($img['thumb'], ENT_QUOTES, 'UTF-8'); ?>" data-large="<?php echo htmlspecialchars($img['full'], ENT_QUOTES, 'UTF-8'); ?>" alt="Galeriebild" /></a></li>
                                <?php } ?>
                                <?php } else { ?>
                                <li><span>Aktuell keine Bilder vorhanden.</span></li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                    <!-- End Elastislide Carousel Thumbnail Viewer -->
                </div><!-- rg-thumbs -->
            </div><!-- rg-gallery -->
            <p class="sub"></p>
        </div><!-- content -->
    </div><!-- container -->
    
    <!--<a href="#info" class="button">Zurück</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="history">
    <h1>Vergangene Turniere</h1>
    <p>Wähle ein Turnier aus der folgenden Liste aus oder klicke unten auf die alte Website.</p>
    <?php history_auswahl($history, $TurnierName); ?>
    <p>Hier geht's zur alten Website (2017-2020)</p>
    <a href="https://2018-20.blankiball.de" class="button primary">Alte Website (2017-2020)</a>
    <p></br></p>
    <!--<a href="#" class="button">Zurück zur Startseite</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="history_info">
    <div style='background-color:#7700FF;'>
        <div style='color:white; text-align: center;'>
            </br>
            <h1>History</h1>
            <p> Du befindest dich in der History-Ansicht! </p>
            <!--Alle Informationen, die Teams und Spiele betreffen, wurden vom gew�nschten Turnier geladen. 
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
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- BEGEGNUNG VERWALTEN -->
<article id="begegnung_verwalten">
    <?php printEditGames($TurnierID, $test_turnier_id); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
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
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="sieger_innen_treppe">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="rangliste">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 23, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- BULLEREI KOMMT -->
<article id='bullerei_kommt'>
    <div style='text-align: center'> 
        <?php printBullereiKommt($conn, $websiteId, $TurnierID) ?>
        <!--<a href='#' class='button'>Zurück</a>-->
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
    <?php if (isset($_SESSION['flash_error_contact']) && $_SESSION['flash_error_contact']) { 
        echo '<div style="margin:10px 0;padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;">'. htmlspecialchars($_SESSION['flash_error_contact']) .'</div>'; 
        unset($_SESSION['flash_error_contact']);
    } ?>
    <p>Falls du Dinge hast, die du uns gerne mitteilen möchtest oder zum Beispiel dein Team wieder abmelden wollen solltest, ist hier der perfekte Ort dafür. Falls du dein Team abmelden möchtest, schreib bitte dein Teampasswort dazu.</p>
    <!-- Alt: Kontaktformular (auskommentiert, um Spam zu vermeiden)
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
            <?php 
                // Neues Bild-Captcha einbinden
                require_once 'website_functionalities/captcha_blanki.php';
                CaptchaBlanki::render('contact');
            ?>
        </div>
        
        <ul class="actions">
            <li><input type="submit" value="Nachricht senden" class="primary"/></li>
            <input type="hidden" name="action" value="send_message"/>
            <li><input type="reset" value="Abbrechen" /></li>
        </ul>
    </form>
    -->

    <?php 
        // Captcha vor E-Mail-Anzeige
        require_once 'website_functionalities/captcha_blanki.php';
        CaptchaBlanki::render('contact');
    ?>
    <div style="margin-top:20px;">
        <button id="show-mail" class="button primary" disabled>E-Mail anzeigen</button>
        <span id="mail-link" style="margin-left:10px;"></span>
        <div id="mail-error" style="margin-top:8px;color:#c0392b;"></div>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('show-mail');
            var span = document.getElementById('mail-link');
            var err = document.getElementById('mail-error');
            var captcha = document.querySelector('#kontakt .captcha-blanki');

            function captchaPassed() {
                if (!captcha) return false;
                if (captcha.dataset && captcha.dataset.passed === '1') return true;
                var passInput = captcha.querySelector('input[name=cb_pass]');
                return passInput && passInput.value === '1';
            }

            function setBtnState() {
                if (!btn) return;
                var ok = captchaPassed();
                btn.disabled = !ok;
                if (!ok) {
                    span.innerHTML = '';
                    if (err) { err.textContent = 'Bitte zuerst das Captcha best\u00e4tigen.'; }
                } else if (err) {
                    err.textContent = '';
                }
            }

            function revealMail() {
                fetch('website_functionalities/reveal_contact_email.php', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('captcha_required');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (!data || !data.ok || !data.email) {
                        throw new Error('missing_email');
                    }
                    var a = document.createElement('a');
                    a.href = 'mailto:' + data.email;
                    a.textContent = data.email;
                    span.innerHTML = '';
                    span.appendChild(a);
                    btn.style.display = 'none';
                    if (err) { err.textContent = ''; }
                })
                .catch(function() {
                    if (err) { err.textContent = 'Bitte zuerst das Captcha best\u00e4tigen.'; }
                });
            }

            if (btn) {
                btn.addEventListener('click', function(ev) {
                    if (!captchaPassed()) {
                        ev.preventDefault();
                        setBtnState();
                        return;
                    }
                    revealMail();
                });
            }

            if (captcha) {
                // Beobachte Captcha-Status (dataset / hidden input) und schalte Button frei
                var observer = new MutationObserver(setBtnState);
                observer.observe(captcha, { attributes: true, attributeFilter: ['data-passed', 'class'] });
                setInterval(setBtnState, 800); // Fallback, falls weder Mutation noch Events feuern
            } else {
                if (btn) btn.disabled = true;
            }
        })();
    </script>
</article>

<!-- ANMELDEN -->
<article id="kontakt_success">
    <p></br></p>
    <h2>Vielen Dank für deine Nachricht!</h2>
    <p>Wir werden dir sobald wie möglich eine Antwort schicken.</p>
    <a href="/" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
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
            <li><a href="#info" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>                 
</article>


<!-- LOGIN - für WORDPRESS -->
<article id="login">
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
    <h2>Login (CMS &amp; Backstage)</h2>
    <?php
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='/' method='POST'>";
    }else{ //Testturniere
        echo "<form action='/?test_turnier_id=$test_turnier_id' method='POST'>";
    }
    ?>
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

    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 18, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    <a href='#rangliste' class='button primary'>Rangliste</a>
    <a id="bookmark-this" href="#" title="Bookmark This Page">Bookmark This Page</a>

    
    
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<?php if ($LoggedInWithBackstageOrHigher) { ?>
<!-- ###################################################################################################################################################################################################################################### -->
<!-- ################################################################################################## BACKSTAGE (ehemals backstage.php) ############################################################################################## -->
<!-- ###################################################################################################################################################################################################################################### -->

<!-- ########  Daten bearbeiten  ######### -->
<article id="backstage_daten_bearbeiten">
    <div style='text-align: center'>
        <h2>Settings</h2>
        <div class='admin-menu-wrap'>
            <?php if ($rechteFlags['teams'] || $istAdminOderCoAdmin) { ?>
            <a href='#backstage_teams_bearbeiten' class='admin-menu-button'>Teams bearbeiten</a>
            <?php } ?>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_begegnungen_bearbeiten' class='admin-menu-button'>Begegnungen bearbeiten</a>
            <?php } ?>
            <?php if ($rollenInfo !== null && $rollenInfo['ist_admin']) { ?>
            <a href='#backstage_turnier_phase' class='admin-menu-button'>Turnierphase</a>
            <?php } ?>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_turnier_settings' class='admin-menu-button'>Turnier Settings</a>
            <a href='#backstage_nutzermanagement' class='admin-menu-button'>Nutzermanagement</a>
            <?php } ?>
        </div>
        <h5><br/></h5>
        <a href='#' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<article id="backstage_verlauf">
    <div style='text-align: center'>
        <h2>Verlauf</h2>
        <div class='admin-menu-wrap'>
            <a href='#backstage_traffic' class='admin-menu-button'>Traffic</a>
            <a href='#backstage_letzte_aenderung' class='admin-menu-button'>DB-Verlauf</a>
        </div>
        <h5><br/></h5>
        <a href='#backstage_info' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ########  Begegnungen bearbeiten  ######### -->
<article id="backstage_begegnungen_bearbeiten">
    <h1>Begegnungen bearbeiten</h1>
    <?php if (!$istAdminOderCoAdmin) { ?>
    <p>Keine ausreichende Berechtigung. Nur Admin und Co-Admin dürfen Begegnungen anlegen oder sperren.</p>
    <?php } else { ?>
    <h2 class='major'>Hinzufügen</h2>
    <p>Legt eine neue Begegnung manuell an (z.B. Freundschaftsspiel oder Nachtrag). Sie bekommt automatisch den Status <b>"Green Card"</b> und wird dadurch von der automatischen Spielplan-Berechnung nie wieder überschrieben oder verworfen.</p>
    <form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBBegegnungHinzufuegen()'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
        <div class='field'>
            <label for='demo-category'>Team 1 (Heimteam):</label>
            <select name='team1' required>
                <option value=''>-</option>
                <?php
                $sqlTeamBegegnungHinzufuegen = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY name';
                $resultTeamBegegnungHinzufuegen = $conn->query($sqlTeamBegegnungHinzufuegen);
                while ($rowTeamBegegnungHinzufuegen = $resultTeamBegegnungHinzufuegen->fetch_assoc()) {
                    $TeamName = $rowTeamBegegnungHinzufuegen['name'];
                    $TeamKuerzel = $rowTeamBegegnungHinzufuegen['kuerzel'];
                    $TeamId = $rowTeamBegegnungHinzufuegen['id'];
                    echo "<option value=$TeamId>$TeamName ($TeamKuerzel)</option>";
                }
                ?>
            </select>
            <h5><br/></h5>
            <label for='demo-category'>Team 2 (Auswärtsteam):</label>
            <select name='team2' required>
                <option value=''>-</option>
                <?php
                $sqlTeamBegegnungHinzufuegen2 = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY name';
                $resultTeamBegegnungHinzufuegen2 = $conn->query($sqlTeamBegegnungHinzufuegen2);
                while ($rowTeamBegegnungHinzufuegen2 = $resultTeamBegegnungHinzufuegen2->fetch_assoc()) {
                    $TeamName = $rowTeamBegegnungHinzufuegen2['name'];
                    $TeamKuerzel = $rowTeamBegegnungHinzufuegen2['kuerzel'];
                    $TeamId = $rowTeamBegegnungHinzufuegen2['id'];
                    echo "<option value=$TeamId>$TeamName ($TeamKuerzel)</option>";
                }
                ?>
            </select>
            <h5><br/></h5>
            <label for='demo-category'>Phase:</label>
            <select name='ko_finallevel' required>
                <option value='0'>Gruppenphase</option>
                <?php
                $sqlKoLevelBegegnungHinzufuegen = 'SELECT * FROM `Turnier_KO_Finallevel` ORDER BY id DESC';
                $resultKoLevelBegegnungHinzufuegen = $conn->query($sqlKoLevelBegegnungHinzufuegen);
                while ($rowKoLevelBegegnungHinzufuegen = $resultKoLevelBegegnungHinzufuegen->fetch_assoc()) {
                    $koId = $rowKoLevelBegegnungHinzufuegen['id'];
                    $koName = $rowKoLevelBegegnungHinzufuegen['name'];
                    echo "<option value=$koId>$koName</option>";
                }
                ?>
                <option value='20'>Losing Bracket</option>
            </select>
            <h5><br/></h5>
            <label for='demo-category'>Bracket-Position (nur bei K.-o.-Phase nötig, bei Gruppenphase/Losing Bracket bitte leer lassen):</label>
            <input type='number' name='ko_turnierbaumposition' min='1' class='Eingabe' placeholder='z.B. 1' style='color: white'>
            <p><i>Die Position bestimmt den Platz im Turnierbaum dieser K.-o.-Runde. Bei falscher Position kann der Turnierbaum falsch angezeigt werden - im Zweifel vorher im "Turnierbaum" auf der Startseite nachsehen, welche Positionen in der gewählten Runde schon belegt sind.</i></p>
        </div>
        <script type='text/javascript'>
            function checkAGBBegegnungHinzufuegen() {
                if (document.getElementById('demo-human-begegnung-hinzufuegen').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen!');
                return false;
            }
        </script>
        <div>
            <div class='field half'>
                <input type='checkbox' id='demo-human-begegnung-hinzufuegen' name='demo-human-begegnung-hinzufuegen' unchecked>
                <label for='demo-human-begegnung-hinzufuegen'>Ich habe geprüft, dass Teams und Bracket-Position stimmen.</label>
                <h5><br/></h5>
            </div>
        </div>
        <ul class='actions'>
            <li><input name='action' type='submit' value='Begegnung_Hinzufuegen' class='primary' /></li>
            <li><input name='action' type='reset' value='Abbrechen' /></li>
        </ul>
    </form>
    <h5><br/></h5>
    <h2 class='major'>Sperren / Löschen</h2>
    <p>Sperrt eine bestehende Begegnung (Status "gesperrt"). Gesperrte Begegnungen werden von der automatischen Spielplan-Berechnung nie wieder angefasst oder neu angelegt - genau dafür ist diese Funktion gedacht, wenn die Website versehentlich eine falsche Begegnung erzeugt hat. Für eingeloggte Personen mit ausreichender Berechtigung werden gesperrte Begegnungen danach weiterhin (ausgegraut) in der KO-Phase angezeigt, damit nachvollziehbar bleibt, was gesperrt wurde.</p>
    <form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBBegegnungSperren()'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
        <div class='field'>
            <label for='demo-category'>Begegnung wählen:</label>
            <select name='begegnungIdSperren' required>
                <option value=''>-</option>
                <?php
                $sqlBegegnung = 'SELECT * FROM `Turnier_Begegnung` WHERE status <> 6 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY ko_turnierbaumposition ASC, id ASC';
                $resultBegegnung = $conn->query($sqlBegegnung);
                while ($rowBegegnung = $resultBegegnung->fetch_assoc()) {
                    $begegnungID = $rowBegegnung['id'];
                    $ko_finallevel = $rowBegegnung['ko_finallevel'];
                    //HEIMTEAM
                    $fk_heimteam = $rowBegegnung['fk_heimteam'];
                    $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = '. $fk_heimteam .'';
                    $resultTeam = $conn->query($sqlTeam);
                    while ($rowTeam = $resultTeam->fetch_assoc()) {
                        $team1 = $rowTeam['name'];
                        $team1_kuerzel = $rowTeam['kuerzel'];
                    }
                    //AUSWÄRTSTEAM
                    $fk_auswaertsteam = $rowBegegnung['fk_auswaertsteam'];
                    $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = '. $fk_auswaertsteam .'';
                    $resultTeam = $conn->query($sqlTeam);
                    while ($rowTeam = $resultTeam->fetch_assoc()) {
                        $team2 = $rowTeam['name'];
                        $team2_kuerzel = $rowTeam['kuerzel'];
                    }
                    echo "<option value=$begegnungID>#$begegnungID | $ko_finallevel | $team1 ($team1_kuerzel) - $team2 ($team2_kuerzel)</option>";
                }
                ?>
            </select>
        </div>
        <script type='text/javascript'>
            function checkAGBBegegnungSperren() {
                if (document.getElementById('demo-human-begegnung-sperren').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen!');
                return false;
            }
        </script>
        <div>
            <div class='field half'>
                <input type='checkbox' id='demo-human-begegnung-sperren' name='demo-human-begegnung-sperren' unchecked>
                <label for='demo-human-begegnung-sperren'>Ich habe die richtige Begegnung ausgewählt.</label>
                <h5><br/></h5>
            </div>
        </div>
        <ul class='actions'>
            <li><input name='action' type='submit' value='Begegnung_Sperren' class='primary' /></li>
            <li><input name='action' type='reset' value='Abbrechen' /></li>
        </ul>
    </form>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########  PLATZHALTER  ######### -->
<!-- ABMELDEN -->
<article id="backstage_abmelden">
    <?php if (!($rechteFlags['teams'] || $istAdminOderCoAdmin)) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        printTeamAbmelden($conn, $TurnierID, $bn, $pw);
    } ?>
    <p></br></p>
    <p></br></p>
</article>

<!-- ########################## -->
<!-- ########  Telefonnummern  ######### -->
<!-- ########################## -->
<article id="backstage_tel">
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Telefonnummern</h1>
    <h3>Hier eine Übersicht aller Telefonnumern, um alle in eine Whatsapp-Gruppe hinzuzufügen.</h3>
    <h5><br /></h5>
    <form action='website_functionalities/vcard.php' method='POST'>
        <button id='btn_login_Absenden' class='button primary' value='Absenden' type='submit'>Kontakte aufs Handy importieren</button>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
    </form>
    <h5><br /></h5>
    <p>Bitte sensibel mit den Daten umgehen! Haben bisher noch nicht mal eine Datenschutzerklärung und keine Lust auf Stress^^</p>
    <h5><br /></h5>
    <?php
    $sqlTelefon = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .') ORDER BY ID DESC';
    $resultTelefon = $conn->query($sqlTelefon);
    while ($rowTelefon = $resultTelefon->fetch_assoc()) {
        $spielername = $rowTelefon['name'];
        $telefonnummer = $rowTelefon['telefonnummer'];
        $teamID = $rowTelefon['fk_team'];
        $timestamp = $rowTelefon['timestamp'];
        $sqlTeamname = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = '. $teamID .'';
        $resultTeamname = $conn->query($sqlTeamname);
        while ($rowTeamname = $resultTeamname->fetch_assoc()) {
            $teamname = $rowTeamname['name'];
        }
        echo "<p><b>$spielername</b> ( Telefonnummer: <b>$telefonnummer</b> | Team: <b>$teamname</b> | Spieler registriert seit: $timestamp )</p>";
    }
    ?>
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  ER-DIAGRAMM  ######### -->
<!-- ########################## -->
<article id="backstage_er_diagram">
    <a href='#backstage_info' class='button'>Zurück</a>
    </br></br>
    <h2>Unsere Datenbank als ER-Diagramm</h2>
    <p>Hinweis: Einige Attribut-Namen haben sich mittlerweile geändert, es sind neue dazugekommen und einige wurden entfernt. Aber die Grundstruktur stimmt noch.</p>
    <span class='image main'><img src='images/er_diagram.jpg' alt='' /></span>
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  INFO zur dbupdate  ######### -->
<!-- ########################## -->
<article id="backstage_info_zur_dbupdate">
    <h2>Automatische Berechnungen</h2>
    <p>In die Website ist ein Berechnungs-Script integriert, das bei jeder Ausführung der Website einmal durchläuft und alle Datensätze aktualisiert. Hier gibt es einen kurzen Überblick, welche Daten dieses Script verändert.</p>
    <p>...</p>
    <p>...</p>
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  Teams bearbeiten  ######### -->
<!-- ########################## -->
<article id="backstage_teams_bearbeiten">
    <div style='text-align: center'>
        <h2>Teams bearbeiten</h2>
        <?php if (!($rechteFlags['teams'] || $istAdminOderCoAdmin)) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else { ?>
        <div class='admin-menu-wrap'>
            <a href='#backstage_abmelden' class='admin-menu-button'>Team abmelden</a>
            <a href='#backstage_changeteam' class='admin-menu-button'>Sonstiges (Gruppe, Bearbeitungsrechte)</a>
        </div>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ########################## -->
<!-- ########  INFO  ######### -->
<!-- ########################## -->
<article id="backstage_info">
    <div style='text-align: center'>
        <h2>Infos</h2>
        <div class='admin-menu-wrap'>
            <a href='#backstage_tel' class='admin-menu-button'>Telefonnummern</a>
            <a href='#backstage_teampasswort' class='admin-menu-button'>Team-Passwörter</a>
            <a href='#backstage_warteliste' class='admin-menu-button'>Warteliste</a>
            <a href='#backstage_er_diagram' class='admin-menu-button'>ER-Diagramm</a>
            <a href='#backstage_verlauf' class='admin-menu-button'>Verlauf</a>
        </div>
        <h5><br/></h5>
        <a href='#' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ########################## -->
<!-- ########  WARTELISTE  ######### -->
<!-- ########################## -->
<article id="backstage_warteliste">
    <h2>Warteliste</h2>
    <?php
    $sqlWarteliste = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_warteliste IN (SELECT id FROM `Turnier_Warteliste` WHERE fk_turnier = '. $TurnierID .')';
    $resultWarteliste = $conn->query($sqlWarteliste);
    $zeahler = 1;
    while ($rowWarteliste = $resultWarteliste->fetch_assoc()) {
        $a=$rowWarteliste["name"];
        $teamId = $rowWarteliste["id"];
        $b=printKuerzelWithLink($conn, $teamId);
        $ausgabeString = "";
        $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";
        $sqlSpieler = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team = ' . $rowWarteliste["id"] . ' ORDER BY ID';
        $resultSpieler = $conn->query($sqlSpieler);
        while ($rowSpieler = $resultSpieler->fetch_assoc()) {
            $x=$rowSpieler["name"];
            $ausgabeString .=  " $x ";
            $ausgabeString .=  "&#x007C;";
        }
        $zeahler++;
        $ausgabeString = substr($ausgabeString, 0, -8);
        echo "<li>$ausgabeString</li>";
    }
    ?>
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  TEAM-PASSWORT  ######### -->
<!-- ########################## -->
<article id="backstage_teampasswort">
    <h2>Team-Passwörter</h2>
    <?php
    $sqlPasswort = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .'';
    $resultPasswort = $conn->query($sqlPasswort);
    $zeahler = 1;
    while ($rowPasswort = $resultPasswort->fetch_assoc()) {
        $a=$rowPasswort["name"];
        $teamId = $rowPasswort["id"];
        $passwort = $rowPasswort["password"];
        $b=printKuerzelWithLink($conn, $teamId);
        $ausgabeString = "";
        $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";
        $zeahler++;
        echo "<li>$ausgabeString | Passwort: $passwort</li>";
    }
    ?>
    <h5><br /></h5>
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  TURNIER-PHASE  ######### -->
<!-- ########################## -->
<article id="backstage_turnier_phase">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php if (!($rollenInfo !== null && $rollenInfo['ist_admin'])) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <h1>Turnier-Phase</h1>
    <form action='website_datachange/edit_variables.php' method='POST' onSubmit='return checkAGB2()'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
        <div class='field'>
            <h3>Bitte den Abschnitt komplett lesen, bevor du die Turnierphase änderst!</h3>
            <p>Im Backend wird bei jedem Aufruf der Website ein php-Script ausgeführt, was die gesamte Datenbank durchgeht und überprüft, ob etwas geupdated werden muss. Das sind Dinge wie ein Team, was noch keine Gruppe bekommen hat, einer Gruppe zuordnen oder das Geiwnnerteam einer Finalstufe in die nächste Finalstufe weiterleiten.</p>
            <p>Durch dieses Script entstehen aber einige Gefahren. Es könnte zum Beispiel passieren, dass sich während dem Halbfinale noch ein Team registiert und sich dadurch der gesamte Turnierbaum verschiebt. Wenn eine bestimmte Zahl von Teams überschritten wird, entscheidet die Datenbank auch, zum Beispiel die Anzahl der Gruppen zu ändern, dabei werden alle Teams neu in Gruppen zusammengewürfelt. Aus diesem Grund gibt es auf dieser Seite hier einen 'Schalter', der nur bestimmte Funktionen des php-Scripts zulässt.</p>
            <p>Hier erhälst du einen kurzen Überblick, was die einzelnen Positionen des 'Schalters' bedeuten.</p>
            <h3></h3>
            <?php
            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                $turnier_phase_name = $rowTurnierPhase['name'];
                $turnier_phase_description = $rowTurnierPhase['description'];
                echo "<label for='demo-category'><h3>'$turnier_phase_name'</h3></label>";
                echo "<p>$turnier_phase_description</p>";
            }
            //Aktuelle Turnierphase herausfinden - erstmal ID
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
            }
            //Jetzt Name dazu finden
            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` WHERE id = '. $turnier_phase_ID .' ORDER BY logical_order';
            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                $turnier_phase_name = $rowTurnierPhase['name'];
            }
            echo "<label for='demo-category'>Aktuelle Turnierphase: <h2 style='color: green'><i>$turnier_phase_name</i></h2></label>
            <label for='demo-category'>Neue Turnierphase auswählen:</label>
            <select name='Phase' id='phase'>
                <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'>-</option>";
            $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
            $resultTurnierPhase = $conn->query($sqlTurnierPhase);
            while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                $turnier_phase = $rowTurnierPhase['name'];
                $turnier_phase_ID = $rowTurnierPhase['id'];
                echo "<option value=$turnier_phase_ID>$turnier_phase</option>";
            }
            echo "</select>";
            ?>
        </div>
        <script type='text/javascript'>
            function checkAGB2() {
                if (document.getElementById('demo-human-registergame').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen!');
                return false;
            }
        </script>
        <div>
            <div class='field half'>
                <input type='checkbox' id='demo-human-registergame' name='demo-human-registergame' unchecked>
                <label for='demo-human-registergame'>Ich habe die Regeln der Datenbank gelesen und verstanden.</label>
                <h5><br/></h5>
            </div>
        </div>
        <ul class='actions'>
            <li><input name='action' type='submit' value='Tunierphase ändern' class='primary' /></li>
            <li><input name='action' type='reset' value='Abbrechen' /></li>
        </ul>
    </form>
    <h5><br /></h5>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  TURNIER SETTINGS  ######### -->
<!-- ########################## -->
<article id="backstage_turnier_settings">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php if (!$istAdminOderCoAdmin) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <h1>Turnier Settings</h1>
    <p>Grundeinstellungen für das aktuelle Turnier. Nur für Admin und Co-Admin. Jede Einstellung wirkt einzeln und sofort, sobald das jeweilige Häkchen "bestätigen" gesetzt wird - du bist ja schon eingeloggt, ein erneutes Login ist nicht nötig.</p>
    <?php
    $sqlTurnierSettings = 'SELECT * FROM `Turnier_Main` WHERE id = ' . $TurnierID . ' ORDER BY id';
    $resultTurnierSettings = $conn->query($sqlTurnierSettings);
    $rowTurnierSettings = $resultTurnierSettings->fetch_assoc();
    $curAnzahlGruppen = (int)$rowTurnierSettings['anzahl_gruppen'];
    $curStartKoFinallevel = (int)$rowTurnierSettings['start_ko_finallevel'];
    $curEinzugKoManuellAnlegen = (int)$rowTurnierSettings['einzug_ko_manuell_anlegen'];
    $bnAttr = htmlspecialchars($bn, ENT_QUOTES);
    $pwAttr = htmlspecialchars($pw, ENT_QUOTES);
    ?>

    <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
        <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
        <input type='hidden' name='action' value='Turnier_Settings_AnzahlGruppen_Aendern'/>
        <label>Anzahl Gruppen</label>
        <input type='number' name='anzahl_gruppen' min='1' value='<?php echo $curAnzahlGruppen; ?>' class='Eingabe ts-input'>
        <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> bestätigen</label>
    </form>
    <p class='ts-hint'><i>Bestimmt, in wie viele Gruppen die Teams in der Gruppenphase aufgeteilt werden.</i></p>

    <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
        <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
        <input type='hidden' name='action' value='Turnier_Settings_StartKoFinallevel_Aendern'/>
        <label>Start-Finalstufe (K.-o.-Phase)</label>
        <select name='start_ko_finallevel' class='ts-input'>
            <?php
            $sqlKoLevelSettings = 'SELECT * FROM `Turnier_KO_Finallevel` ORDER BY id DESC';
            $resultKoLevelSettings = $conn->query($sqlKoLevelSettings);
            while ($rowKoLevelSettings = $resultKoLevelSettings->fetch_assoc()) {
                $koId = $rowKoLevelSettings['id'];
                $koName = $rowKoLevelSettings['name'];
                $sel = ($koId == $curStartKoFinallevel) ? "selected" : "";
                echo "<option value=$koId $sel>$koName</option>";
            }
            ?>
        </select>
        <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> bestätigen</label>
    </form>
    <p class='ts-hint'><i>Legt fest, mit welcher Finalstufe die K.-o.-Phase beginnt (z.B. Achtelfinale, Viertelfinale, ...) - abhängig von der Teamanzahl.</i></p>

    <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
        <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
        <input type='hidden' name='action' value='Turnier_Settings_EinzugKoManuell_Aendern'/>
        <label>Einzug K.-o.-Phase manuell anlegen</label>
        <input type='checkbox' name='einzug_ko_manuell_anlegen' value='1' <?php echo ($curEinzugKoManuellAnlegen == 1) ? "checked" : ""; ?>>
        <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> bestätigen</label>
    </form>
    <p class='ts-hint'><i>Wenn aktiviert, berechnet die Website die ersten K.-o.-Paarungen nicht automatisch aus den Gruppenplatzierungen, sondern erwartet, dass diese manuell (z.B. über "Begegnungen bearbeiten") angelegt werden.</i></p>

    <style>
        .ts-row { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; margin: 0.6rem 0 0.2rem; }
        .ts-row label:first-of-type { min-width: 220px; }
        .ts-input { width: auto; min-width: 140px; }
        .ts-hint { margin: 0 0 0.8rem; opacity: 0.85; }
    </style>
    <h5><br /></h5>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  NUTZERMANAGEMENT  ######### -->
<!-- ########################## -->
<article id="backstage_nutzermanagement">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Nutzermanagement</h1>
    <?php if (!$istAdminOderCoAdmin) { ?>
        <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        $darfNeueAdmins = $rechteFlags['neue_admins'];
        $darfNeueCoAdmins = $rechteFlags['neue_co_admins'];
        $darfRestlicheRollenVergeben = $rechteFlags['restliche_rollen_vergeben'];
        function nmDarfRolleVergeben($rId, $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben) {
            if ($rId == 1) { return $darfNeueAdmins; }
            if ($rId == 2) { return $darfNeueCoAdmins; }
            return $darfRestlicheRollenVergeben;
        }

        // Alle Rollen (für Übersicht + Badge-Namen)
        $rollenNamenById = [];
        $rollenListeFuerUebersicht = [];
        $sqlRollen = 'SELECT * FROM System_Benutzer_in_Rolle ORDER BY hierarchie_ebene';
        $resultRollen = $conn->query($sqlRollen);
        while ($rowRolle = $resultRollen->fetch_assoc()) {
            $rollenNamenById[(int)$rowRolle['id']] = $rowRolle['name'];
            $rollenListeFuerUebersicht[] = $rowRolle;
        }

        // Alle Nutzer mit all ihren Rollen (ausschließlich über die Relation-Tabelle zugewiesen) sammeln
        $alleNutzerMitRollen = [];
        $sqlAlleNutzer = 'SELECT * FROM System_Benutzer_in ORDER BY Benutzername';
        $resultAlleNutzer = $conn->query($sqlAlleNutzer);
        while ($rowNutzer = $resultAlleNutzer->fetch_assoc()) {
            $nutzerId = (int)$rowNutzer['id'];
            $rolleIds = [];
            try {
                $sqlRel = 'SELECT fk_rolle FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ' . $nutzerId;
                $resultRel = $conn->query($sqlRel);
                while ($rowRel = $resultRel->fetch_assoc()) { $rolleIds[] = (int)$rowRel['fk_rolle']; }
            } catch (Throwable $e) { /* Relation-Tabelle (noch) nicht vorhanden */ }
            $rolleIds = array_values(array_unique($rolleIds));
            sort($rolleIds);
            $alleNutzerMitRollen[] = [
                'id' => $nutzerId,
                'bn' => $rowNutzer['Benutzername'],
                'pw' => $rowNutzer['Passwort'],
                'rolle_ids' => $rolleIds,
                'macht' => count($rolleIds) > 0 ? min($rolleIds) : PHP_INT_MAX,
            ];
        }
        usort($alleNutzerMitRollen, function($a, $b) { return $a['macht'] <=> $b['macht']; });

        $bnAttrNm = htmlspecialchars($bn, ENT_QUOTES);
        $pwAttrNm = htmlspecialchars($pw, ENT_QUOTES);
        $loginAlsAction = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
    ?>
    <style>
        .nm-rollen-tabelle { width: 100%; margin-bottom: 1.2rem; font-size: 0.82rem; }
        .nm-userlist { margin-bottom: 1rem; }
        .nm-user { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; padding: 0.25rem 0.1rem; border-bottom: 1px solid rgba(139, 92, 246, 0.15); font-size: 0.8rem; line-height: 1.1; }
        .nm-user b { min-width: 110px; }
        .nm-pw { opacity: 0.6; font-size: 0.7rem; }
        .nm-badge { display: inline-flex; align-items: center; gap: 0.2rem; background: rgba(139, 92, 246, 0.18); border: 1px solid var(--admin-accent); border-radius: 10px; padding: 0.05rem 0.45rem; font-size: 0.68rem; white-space: nowrap; }
        .nm-badge button { border: none; background: none; color: var(--admin-accent-light); cursor: pointer; font-size: 0.75rem; padding: 0; line-height: 1; }
        .nm-login-als, .nm-addrole-form { display: inline-flex; gap: 0.25rem; align-items: center; margin: 0; }
        .nm-login-als button { padding: 0.12rem 0.4rem; font-size: 0.68rem; }
        .nm-addrole-form select, .nm-addrole-form button, .nm-newuser-form input, .nm-newuser-form select, .nm-newuser-form button {
            padding: 0.12rem 0.35rem; font-size: 0.7rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.06); color: #fff;
        }
        .nm-addrole-form button, .nm-newuser-form button { background: var(--admin-accent-deep); border-color: var(--admin-accent); cursor: pointer; }
        .nm-newuser-form { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; margin: 0.6rem 0 1.5rem; }
    </style>

    <h2>Rollen</h2>
    <table class='withBorderCollapse nm-rollen-tabelle'>
        <thead><tr><th>Rolle</th><th>Beschreibung</th></tr></thead>
        <tbody>
        <?php foreach ($rollenListeFuerUebersicht as $r) {
            echo "<tr><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['beschreibung']) . "</td></tr>";
        } ?>
        </tbody>
    </table>

    <h2>Nutzer</h2>
    <p><i>Sortiert nach Berechtigungsstärke (Admin zuerst). Jeder Nutzer kann mehrere Rollen gleichzeitig haben.</i></p>
    <div class='nm-userlist'>
    <?php foreach ($alleNutzerMitRollen as $nutzer) { ?>
        <div class='nm-user'>
            <b><?php echo htmlspecialchars($nutzer['bn']); ?></b>
            <span class='nm-pw'>pw: <?php echo htmlspecialchars($nutzer['pw']); ?></span>
            <?php foreach ($nutzer['rolle_ids'] as $rid) {
                $rname = $rollenNamenById[$rid] ?? ('Rolle ' . $rid);
                echo "<span class='nm-badge'>" . htmlspecialchars($rname);
                if (nmDarfRolleVergeben($rid, $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben) && count($nutzer['rolle_ids']) > 1) {
                    echo "<form action='website_datachange/edit_account.php' method='POST' style='display:inline;margin:0;' onsubmit=\"return confirm('Rolle wirklich entfernen?');\">
                        <input type='hidden' name='action' value='Rolle_Entfernen'>
                        <input type='hidden' name='admin_bn' value='$bnAttrNm'>
                        <input type='hidden' name='admin_pw' value='$pwAttrNm'>
                        <input type='hidden' name='ziel_benutzer_id' value='{$nutzer['id']}'>
                        <input type='hidden' name='entferne_rolle' value='$rid'>
                        <button type='submit' title='Rolle entfernen'>&times;</button>
                    </form>";
                }
                echo "</span>";
            } ?>
            <form action='<?php echo $loginAlsAction; ?>' method='POST' class='nm-login-als'>
                <input type='hidden' name='bn' value='<?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?>'>
                <input type='hidden' name='pw' value='<?php echo htmlspecialchars($nutzer['pw'], ENT_QUOTES); ?>'>
                <button type='submit' class='admin-menu-button' style='min-width:auto;padding:0.12rem 0.4rem;font-size:0.68rem;'>Login als User</button>
            </form>
            <?php
            $verfuegbareRollen = [];
            foreach ($rollenListeFuerUebersicht as $r) {
                $rid = (int)$r['id'];
                if (in_array($rid, $nutzer['rolle_ids'], true)) { continue; }
                if (!nmDarfRolleVergeben($rid, $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) { continue; }
                $verfuegbareRollen[] = $r;
            }
            if (count($verfuegbareRollen) > 0) {
            ?>
            <form action='website_datachange/edit_account.php' method='POST' class='nm-addrole-form'>
                <input type='hidden' name='action' value='Rolle_Hinzufuegen'>
                <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                <select name='neue_rolle'>
                    <?php foreach ($verfuegbareRollen as $r) {
                        echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['name']) . "</option>";
                    } ?>
                </select>
                <button type='submit'>+</button>
            </form>
            <?php } ?>
        </div>
    <?php } ?>
    </div>

    <h2>Neuen Nutzer anlegen</h2>
    <form action='website_datachange/edit_account.php' method='POST' class='nm-newuser-form'>
        <input type='hidden' name='action' value='admin_erstellt_nutzer'/>
        <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
        <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
        <input type='text' name='neuer_bn' placeholder='Benutzername' required>
        <input type='text' name='neuer_pw' placeholder='Passwort' required>
        <select name='neue_rolle' required>
            <?php
            foreach ($rollenListeFuerUebersicht as $r) {
                $rId = (int)$r['id'];
                if (nmDarfRolleVergeben($rId, $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) {
                    echo "<option value='$rId'>" . htmlspecialchars($r['name']) . "</option>";
                }
            }
            ?>
        </select>
        <button type='submit'>Anlegen</button>
    </form>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  Letzte DB-Änderungen  ######### -->
<!-- ########################## -->
<article id="backstage_letzte_aenderung">
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
    <h2>Letzte DB-Änderungen</h2>
    <p>Hier werden alle Datenbankänderungen dokumentiert, egal ob es um Löschung, Änderung oder Einfügen geht. Wenn ein Team ständig versucht, Dinge zu bearbeiten, die es nicht bearbeiten soll, siehst du das hier und kannst dem Team die Rechte wegnehmen. Die Änderungen sind in SQL formuliert. Falls du nicht weißt, wie SQL funktioniert, klicke einfach <a href='https://studyflix.de/informatik/structured-query-language-606'>hier</a></p>
    <?php if (!isset($_POST['load_db_verlauf'])) {
        $ladeAction = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
        echo "
        <form action='$ladeAction' method='POST'>
            <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES) . "'>
            <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES) . "'>
            <input type='hidden' name='load_db_verlauf' value='1'>
            <button type='submit' class='admin-menu-button'>DB-Verlauf jetzt laden</button>
        </form>
        <p><i>Wird nicht automatisch geladen, da die Abfrage bei großen Turnieren spürbar dauern kann.</i></p>
        ";
    } else {
        // Nur die letzten 500 Einträge, um die Website nicht wieder spürbar zu verlangsamen
        $sqlSystem_Data_DB_Verlauf = 'SELECT * FROM `System_Data_DB_Verlauf` ORDER BY ID desc LIMIT 500';
        $resultSystem_Data_DB_Verlauf = $conn->query($sqlSystem_Data_DB_Verlauf);
        while ($rowSystem_Data_DB_Verlauf = $resultSystem_Data_DB_Verlauf->fetch_assoc()) {
            $data_db_verlauf_timestamp = $rowSystem_Data_DB_Verlauf['timestamp'];
            $data_db_verlauf_who = $rowSystem_Data_DB_Verlauf['fk_who'];
            $data_db_verlauf_content = $rowSystem_Data_DB_Verlauf['content'];
            echo "<hr>";
            echo "<p><b>" . htmlspecialchars($data_db_verlauf_who) . ":</b> " . htmlspecialchars($data_db_verlauf_content) . " ($data_db_verlauf_timestamp)</p>";
        }
    } ?>
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  Traffic  ######### -->
<!-- ########################## -->
<article id="backstage_traffic">
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
    <h2>Website-Traffic</h2>
    <p>Hier werden Website-Funktionalitäten getrackt.</p>
    <?php if (!isset($_POST['load_traffic'])) {
        $ladeAction = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
        echo "
        <form action='$ladeAction' method='POST'>
            <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES) . "'>
            <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES) . "'>
            <input type='hidden' name='load_traffic' value='1'>
            <button type='submit' class='admin-menu-button'>Traffic jetzt laden</button>
        </form>
        <p><i>Wird nicht automatisch geladen, da die Abfrage bei großen Turnieren spürbar dauern kann.</i></p>
        ";
    } else {
        // Kategorie-Name per JOIN statt pro Zeile einzeln nachzuschlagen (das war vermutlich die
        // eigentliche Ursache der früheren Langsamkeit) + nur die letzten 500 Einträge
        $sql = 'SELECT t.*, k.name AS traffic_kategorie FROM `System_Traffic` t
                LEFT JOIN `System_Traffic_Kategorien` k ON k.id = t.fk_kategorie
                ORDER BY t.id DESC LIMIT 500';
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $traffic_timestamp = $row['timestamp'];
            $traffic_who = $row['fk_who'];
            $traffic_kategorie = $row['traffic_kategorie'];
            $traffic_text = $row['text'];
            echo "<hr>";
            echo "<p><b>" . htmlspecialchars($traffic_kategorie) . "</b> " . htmlspecialchars($traffic_who) . " " . htmlspecialchars($traffic_text) . " ($traffic_timestamp)</p>";
        }
    } ?>
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  CHANGETEAM  ######### -->
<!-- ########################## -->
<article id="backstage_changeteam">
    <div id='LogIn2'>
    <h2>Teams bearbeiten</h2>
    <?php if (!($rechteFlags['teams'] || $istAdminOderCoAdmin)) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <p>Hier kannst du jedes Attribut der Teams ändern, also zum Beispiel Gruppe oder auch Bearbeitungsrechte für die Website.</p>
    <?php printGroupsAsTable($TurnierID, $conn, $LoggedInWithBackstageOrHigher, 0, 0); ?>
    <form action='website_datachange/edit_teams.php' method='POST' onSubmit='return checkAGBchangeTeam()'>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'>
        <div class='field'>
            <label for='demo-category'>Team wählen</label>
            <select name='team' id='teams_waehlen' required>
                <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'><i>Team wählen</i></option>
                <?php
                $sqlTeam = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY id';
                $resultTeam = $conn->query($sqlTeam);
                $zaehler = 1;
                while ($rowTeam = $resultTeam->fetch_assoc()) {
                    $teamName = $rowTeam['name'];
                    $teamKuerzel = $rowTeam['kuerzel'];
                    $teamId = $rowTeam['id'];
                    echo "<option value=$teamId>$zaehler. $teamKuerzel | $teamName</option>";
                    $zaehler++;
                }
                ?>
            </select>
            <h5><br/></h5>
            <select name='gruppe' id='gruppe_waehlen'>
                <option value='auffangbeckenfueralledienichtcheckendassmanhierwasauswählenmuss'><i>Gruppe wählen</i></option>
                <?php
                $sqlGruppe = 'SELECT * FROM `Turnier_Gruppe` WHERE fk_turnier = ' . $TurnierID . ' ORDER BY id';
                $resultGruppe = $conn->query($sqlGruppe);
                $zaehler = 1;
                while ($rowGruppe = $resultGruppe->fetch_assoc()) {
                    $gruppeName = $rowGruppe['name'];
                    $gruppeId = $rowGruppe['id'];
                    echo "<option value=$gruppeId>$zaehler. $gruppeName</option>";
                    $zaehler++;
                }
                ?>
            </select>
            <p>Nur auswählen wenn Gruppe geändert werden soll.</p>
        </div>
        <h5><br/></h5>
        <script type='text/javascript'>
            function checkAGBchangeTeam() {
                if (document.getElementById('demo-human-changeteam').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen!');
                return false;
            }
        </script>
        <div>
            <div class='field half'>
                <input type='checkbox' id='demo-human-changeteam' name='demo-human-changeteam' unchecked>
                <label for='demo-human-changeteam'>Ergebnisse nicht gelogen auf Ehre.</label>
                <h5><br/></h5>
            </div>
        </div>
        <p><button id='btn_login2' name='action' value='change_group' type='submit'>Gruppe ändern</button></p>
        <p><button id='btn_login2' name='action' value='rechte_weg' type='submit'>Bearbeitungsrechte wegnehmen</button></p>
        <p><button id='btn_login2' name='action' value='rechte_geben' type='submit'>Bearbeitungsrechte zurückgeben</button></p>
    </form>
    <p></br></p>
    <p></br></p>
    <?php } ?>
    </div>
</article>
<?php } ?>

<!-- ###################################################################################################################################################################################################################################### -->
<!-- ######################################################################################## SEITEN NACH WEBSITE_DATACHANGE ################################################################################################################################ -->
<!-- ###################################################################################################################################################################################################################################### -->

<!-- vielendankfuerdeineanmeldung -->
<article id="vielendankfuerdeineanmeldung">
    <div style='text-align: center'>  
        </br>
        <h1>Vielen Dank für deine Anmeldung!</h1>
        <p>Deine Anmeldung wird jetzt bearbeitet und bald kannst du dein Team in der Team-Liste sehen.</a></p>

        
        <?php
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $teilnahmebeitrag = $rowTurnier['teilnahmebeitrag'];
            }
            if (is_string($teilnahmebeitrag)) {
                $teilnahmebeitrag = str_replace(',', '.', $teilnahmebeitrag);
            }
            $teilnahmebeitragValue = (is_numeric($teilnahmebeitrag)) ? (float)$teilnahmebeitrag : 0.0;
            if ($teilnahmebeitragValue > 0) {
                if (floor($teilnahmebeitragValue) == $teilnahmebeitragValue) {
                    $teilnahmebeitragText = number_format($teilnahmebeitragValue, 0, ',', '.');
                } else {
                    $teilnahmebeitragText = rtrim(rtrim(number_format($teilnahmebeitragValue, 2, ',', '.'), '0'), ',');
                }
                echo "<h3><a href='https://paypal.me/blankiball?country.x=DE&locale.x=de_DE'>&#128176; Teilnahmebeitrag &#128176;</a></h3>";
                echo "<p><b>Nicht vergessen, die " . $teilnahmebeitragText . "&nbsp;&euro; Teilnahmegeb&uuml;hr pro Team per Paypal an @blankiball zu bezahlen! (Verwendungszweck: Euer Teamname)</b> Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website, Sticker und der Rest flie&szlig;t in Bier f&uuml;rs Turnier.</p>";
                echo "<a class='button' style='background-color: pink; color: black' href='https://paypal.me/blankiball?country.x=DE&locale.x=de_DE'>Direkt zu Paypal</a>";
            }
        ?>


        </br></br></br>
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

        
        
    </div>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
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
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
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
            /*echo"<h3><a href=$link_solibeitrag>??Unterst�tze uns??</a></h3>";
            echo"
            <p>Gerne kannst du uns mit einem Solibeitrag unterst�tzen. Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website und das Grillevent am letzten Tag.</p>                  
            <a class='button' style='background-color: pink; color: black' href='https://paypal.me/blankiball?country.x=DE&locale.x=de_DE'>Zum Solibeitrag</a> ";
            */
            $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
            $resultTurnier = $conn->query($sqlTurnier);
            while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                $teilnahmebeitrag = $rowTurnier['teilnahmebeitrag'];
            }
            if (is_string($teilnahmebeitrag)) {
                $teilnahmebeitrag = str_replace(',', '.', $teilnahmebeitrag);
            }
            $teilnahmebeitragValue = (is_numeric($teilnahmebeitrag)) ? (float)$teilnahmebeitrag : 0.0;
            if ($teilnahmebeitragValue > 0) {
                if (floor($teilnahmebeitragValue) == $teilnahmebeitragValue) {
                    $teilnahmebeitragText = number_format($teilnahmebeitragValue, 0, ',', '.');
                } else {
                    $teilnahmebeitragText = rtrim(rtrim(number_format($teilnahmebeitragValue, 2, ',', '.'), '0'), ',');
                }
                echo "<h3><a href='" . $link_solibeitrag . "'>&#128176; Teilnahmebeitrag &#128176;</a></h3>";
                echo "<p><b>Nicht vergessen, die " . $teilnahmebeitragText . "&nbsp;&euro; Teilnahmegeb&uuml;hr pro Team per Paypal an @blankiball zu bezahlen! (Verwendungszweck: Euer Teamname)</b> Das Geld stecken wir zu 100% ins Turnier, beispielsweise in die Preise, die Website, Sticker und der Rest flie&szlig;t in Bier f&uuml;rs Turnier.</p>";
                echo "<a class='button' style='background-color: pink; color: black' href='https://paypal.me/blankiball?country.x=DE&locale.x=de_DE'>Direkt zu Paypal</a>";
            }
            
            
            echo "</br></br></br>
            <h3><img src='images/icon/whatsapp.png' width='20' height='20' border='5' alt='Home'> Komm in die Gruppe</h3>
            <p>Tritt jetzt der Blankiball-Whatsapp-Gruppe bei um alle Turnier-Infos rechtzeitig mitzubekommen!</p> <!-- (... oder der Telegram-Gruppe, falls du kein Whatsapp hast oder Whatsapp kacke findest)-->
            <ul class='actions stacked'>
                <li><a class='button' style='background-color: green' href=$link_whatsapp_info>Offizielle Whatsapp Gruppe</a></li>
                <!--<li><a class='button' style='background-color: green' href=$link_whatsapp_chat>Chat-Gruppe</a></li>-->
                <!--<li><a class='button' style='background-color: blue' href=$link_telegram>Telegram-Gruppe</a></li>-->
                </br>
                <li><a class='button' href='#'>Zurück zur Startseite</a></li>
            </ul>
            ";
            
        ?>
        
        
    </div>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- logincheck_failure -->
<article id="logincheck_failure">
    <h1>Login fehlgeschlagen</h1>
    <p>Entweder du hast das Kürzel/Passwort falschgeschrieben oder der Anmeldezeitraum ist abgelaufen und du wurdest jetzt in die Warteliste eingefügt. Falls der Anmeldezeitraum noch läuft, versuche entweder noch einmal dein Team anzumelden oder wende dich an <a href="#kontakt">die Orga</a></p>
    <a class="button" href='#'>Zurück zur Startseite</a>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- edit_games_success -->
<article id="edit_games_success">
    <h1>Danke für deinen Eintrag!</h1>
    <p>Dein Eintrag sollte direkt auf der Website sichtbar sein. Falls du Fragen oder Probleme hast, wende dich an <a href="#kontakt">die Orga</a>!</p>
    <a class="button" href='#spielplan'>Zum Spielplan</a>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>		

<!-- edit_games_failure -->
<article id="edit_games_failure">
    <h1>Ups, da ist wohl etwas schiefgelaufen!</h1>
    <p>Vielleicht war dein Passwort falsch, vielleicht hast du nicht die nötigen Rechte. Vielleicht hat Hermann auch einen Fehler gemacht. Falls du Fragen oder Probleme hast, wende dich an <a href="#kontakt">die Orga</a>!</p>
    <a class="button" href='#spielplan'>Zum Spielplan</a>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
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
    <?php  cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
                    <!--<div><b><p>Folge uns auf Instagram, um alle aktuellen Infos und Updates zu bekommen:</p></b>
                    <b><p style="font-size: 30px"><a style="color: white" href="https://www.instagram.com/blankiball_official/?hl=de/"><img src="images/icon/insta.png" width="30" height="30" border="0" alt="Home"> @blankiball_official</a></p></b><!--<h3>📢Offizieller Start:</h3>
                    <p>t.b.a.<br/> -->
                    <!--Freitag (16.12.22) - 18:00 Uhr / -->
                    <!--Treffpunkt: <a href="#map">Blankensteinpark</a><p>-->
                    <!--<h3>📢Anmeldezeitraum:</h3>
                    <p>bis zum 15.12.22<br/><p>-->
                    <!--Montag (06.09.) - 16:00 Uhr--><br/>
                    <!--<a href="#history" class="button primary">Vergangene Turniere</a>
                    <br/><br/><br/><h3><a href="#merch">👘Offizieller Merch</a></h3>
                    <h3><a href="https://www.seedshirt.de/shop/blankiball22">👘Offizieller Merch</a></h3>
                    <h3><a href="https://www.shirtee.com/en/store/blankiballmerch">👘Offizieller Merch</a></h3>
                    <p>upgrade deinen Style und supporte das Turnier</p>
                    <img src="images/Sonstiges/Merch/front-organic-basic-hoodie-f8f8f8-558x.png" alt=""  style="width:10rem;"/>
                    <br/><br/><h3>
                    <a href="https://paypal.me/blankiball?country.x=DE&locale.x=de_DE">💓Spende fürs Turnier</a>
                    </h3>
                    <p>
                    finanziere krassere Preise und noch mehr Bier
                    </p></div>

                         Lädt Song runter: style="display: none" autostart='true' <section><embed name='Songtitel' src='assets/audio/kein_bier_mehr_da.opus' border='0' width='152' height='10' style="color: black"  Delay='0' VOLUME='100' loop='true' controls='smallconsole'> </section>  
                        <div><br/><br/>
                    <img src="images/Sonstiges/blankiball_simulator.jpg" alt=""  style="width:20rem;"/>
                    <br/>
                    <a href="#blankiball_simulator" class="button primary">Blankiball-Simulator</a>
                    <br/><br/>

                    <img src="images/Sonstiges/the_one_logo_weinglas_mit_schriftzug.png" alt=""  style="width:20rem;"/>
                    <br/>
                    <h4>Die eine Trinkspielapp, die alle anderen ersetzt</h4>
                    <a href="https://www.instagram.com/app.theone/" class="button primary">zur App</a>
                    <br/><br/><h3><br/>
                    <a style="color: white;font-size:15px;" href="https://open.spotify.com/user/11129583931/playlist/3K13BWkhzAVwHdRM2F6P8Z">Der offizielle S<img src="images/icon/spoti.png" width="15" height="15" border="5" alt="Home">undtrack zum Turnier<br/></a></h3><h4><br/>
                    <img src="images/icon/insta.png" width="20" height="20" border="0" alt="Home">
                    <br/>
                    <a style="color: white" href="https://www.instagram.com/blankiball_official/?hl=de/">@blankiball_official</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/blankiball_memes/?hl=de/">@blankiball_memes</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/blankiball_simulator/?hl=de/">@blankiball_simulator</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/explore/tags/blankiball/">#blankiball</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/app.theone/">@app.theone - Trinkspielapp</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/roehrlitrinkhalme/?hl=de/">@roehrlitrinkhalme</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/sternburg.brauerei/?hl=de/">@sternburg.official</a>
                    <br />
                    <a style="color: white" href="https://www.instagram.com/gretarthouse/?hl=de/">@gretarthouse</a></h4><NULL><br/>
                    
                    <a href="#kontakt" class="button">Kontakt & Feedback</a>
                    <br/><br/
                    <a href="https://www.youtube.com/watch?v=DLzxrzFCyOs" class="button">Secret Stuff</a></NULL><NULL><br/><br/>
                    <h4>Für den Notfall</h4>
                    <audio id="audio_with_controls" controls>
                            <source src="assets/audio/kein_bier_mehr_da.mp3" type="audio/mp3" />
                            Ihr Browser kann dieses Tondokument nicht wiedergeben.<br>
                            Es enth�lt eine Auff�hrung der Europahymne. 
                            Sie k�nnen es unter <a href="#">Link-Addresse</a> abrufen.
                    </audio></NULL><p><hr></p><p><a href="#bullerei_kommt" class="button">BK</a></p><p class="copyright">Bei Fragen, nutze das <a href="#kontakt">Kontaktformular</a></p class="copyright"><p class="copyright">© Blankiball <a href="#impressum">Impressum</a></p class="copyright"><p class="copyright"><br/>
                    <a href="#login">Backstage</a></p class="copyright"></div>-->
    <?php  cmsPrintSection($websiteId, $siteID, $TurnierID, 7, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID OberGEBEN (F�r CMS) #####-->
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
<script src="assets/js/captcha_blanki.js"></script>

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

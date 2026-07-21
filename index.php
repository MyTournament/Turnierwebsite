<?php
// ================================================================================================
// SESSION-BASIERTE LOGIN-PERSISTENZ FÜR CMS/BACKSTAGE (Admin/Co-Admin/Autor*in/etc. bleiben eingeloggt)
// ================================================================================================
// Vorher war der Login rein POST-Feld-basiert (bn/pw nur im jeweiligen Formular) und ging nach
// JEDEM Absenden verloren - man musste sich nach jeder Aktion neu einloggen. Jetzt werden bn/pw
// nach erfolgreichem Login zusätzlich in der Session gemerkt (siehe weiter unten beim eigentlichen
// Login-Block) und von dort als Fallback gelesen, wenn kein POST-Feld gesetzt ist. "?logout=1"
// löscht die Session gezielt wieder.
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
		<?php
		// Cache-Busting: main.css wurde diese Session sehr oft geändert, aber der <link> hatte keine
		// Versionierung - Browser (und ggf. zwischengeschaltete Proxies/CDNs) konnten dadurch beliebig
		// lange eine veraltete, gecachte Kopie ausliefern (Symptom: alte Trennlinien/Rahmen bleiben
		// sichtbar, neue Regeln fehlen komplett). filemtime() haengt automatisch einen Zeitstempel an,
		// der sich bei jeder Änderung der Datei von selbst aktualisiert - kein manuelles Hochzählen
		// einer Versionsnummer nötig.
		$mainCssVersion = @filemtime(__DIR__ . '/assets/css/main.css') ?: time();
		?>
		<link rel="stylesheet" href="assets/css/main.css?v=<?php echo $mainCssVersion; ?>" />
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
            /* ================================================================================
               DESIGN-ÜBERARBEITUNG: KO-Navigation (Turnierbaum / Rangliste / Punktetabelle)
               ================================================================================
               Vorher: laute Neon-Farbverläufe mit Glanz-Effekt, die als eigener, vom restlichen
               (bewusst ruhigen, monochromen) Grundtheme losgelöster Stil wirkten. Jetzt: gleiche
               dezente Karten-Optik wie .phase-card (main.css), nur mit einer schmalen farbigen
               Akzentlinie links, damit die drei Ziele weiterhin unterscheidbar bleiben. */
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
                gap: 0.9rem;
                min-height: 64px;
                padding: 0.85rem 1.15rem;
                border-radius: 12px;
                font-weight: 500;
                line-height: 1.3;
                white-space: normal;
                color: #ffffff !important;
                background: rgba(255,255,255,0.06);
                border: 1px solid rgba(255,255,255,0.14);
                border-left: 4px solid var(--ko-btn-accent, rgba(255,255,255,0.4));
                transition: background-color 0.15s ease-in-out, transform 0.15s ease;
            }
            .ko-phase-btn,
            .ko-phase-btn:visited,
            .ko-phase-btn .ko-btn-label,
            .ko-phase-btn .ko-btn-sub {
                color: #ffffff !important;
            }
            .ko-phase-btn .ko-btn-label {
                font-size: 0.95rem;
                letter-spacing: 0.03em;
                text-transform: none;
                display: block;
            }
            .ko-phase-btn .ko-btn-sub {
                font-size: 0.78rem;
                letter-spacing: 0.01em;
                opacity: 0.75;
                display: block;
            }
            .ko-phase-btn--tree { --ko-btn-accent: #5fb0ff; }
            .ko-phase-btn--rank { --ko-btn-accent: #c98bd6; }
            .ko-phase-btn--points { --ko-btn-accent: #59c9a5; }
            .ko-phase-btn:hover {
                background: rgba(255,255,255,0.11);
                transform: translateY(-1px);
            }
            .ko-phase-btn:active {
                transform: translateY(0);
                background: rgba(255,255,255,0.15);
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

    // ============================================================================================
    // GEMEINSAMES LOGIN-FELD FÜR CMS & BACKSTAGE (früher zwei getrennte Logins/Seiten)
    // ============================================================================================
    // Backstage.php wurde komplett in index.php gemergt; ein einziges bn/pw-Feld entscheidet über
    // Zugriff auf CMS-Bearbeitung UND Backstage, je nachdem welche Rollen-Flags der Account hat.
    // Fallback auf die Session, damit der Login nach einem Redirect (z.B. nach dem Speichern in Edit Data) erhalten bleibt
    $bn = $_POST["bn"] !== null ? $_POST["bn"] : (isset($_SESSION['admin_bn']) ? $_SESSION['admin_bn'] : null);
    $pw = $_POST["pw"] !== null ? $_POST["pw"] : (isset($_SESSION['admin_pw']) ? $_SESSION['admin_pw'] : null);

    include_once 'website_datachange/login_interface.php';
    $rollenInfo = ($bn !== null && $pw !== null) ? getUserRollenInfo($conn, $bn, $pw) : null;
    $rechteFlags = $rollenInfo['flags'] ?? array_fill_keys(['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'], false);

    // ========================================================================================
    // RECHTE-AUDIT (KRITISCHER FIX): AB HIER NUR NOCH GRANULARE FLAGS STATT ADMIN/CO-ADMIN-SHORTCUT
    // ========================================================================================
    // Vorher hing z.B. der Zugriff auf CMS/Backstage/Teams pauschal auch an "ist Admin/Co-Admin",
    // unabhängig vom tatsächlichen Rechte-Flag der Person. Dadurch konnte z.B. ein Admin ohne
    // Autor*in-Rolle trotzdem CMS-Inhalte bearbeiten. Jetzt gilt strikt: jedes Recht hängt nur noch
    // am jeweiligen "rechte_*"-Flag aus System_Benutzer_in_Rolle. Admin/Co-Admin funktionieren
    // trotzdem weiterhin wie gewohnt, weil ihre Rollen in der DB ohnehin (fast) alle Flags auf 1
    // stehen haben - das ist aber jetzt eine Eigenschaft der Rolle, kein Programm-Shortcut mehr.
    // $istAdminOderCoAdmin bleibt als Variable bestehen, weil es für die wenigen Funktionen, die
    // explizit (und bewusst) Admin/Co-Admin-only bleiben sollen (Begegnung anlegen/sperren), noch
    // gebraucht wird - siehe die jeweiligen Kommentare weiter unten.
    $istAdminOderCoAdmin = $rollenInfo !== null && ($rollenInfo['ist_admin'] || $rollenInfo['ist_co_admin']);
    // Strikt "echter" Admin (nicht Co-Admin) - für die wenigen Funktionen, die explizit nur dem
    // Hauptadmin vorbehalten bleiben sollen (Verlauf/Traffic/DB-Verlauf, Passwort anzeigen/ändern).
    $istEchterAdmin = $rollenInfo !== null && $rollenInfo['ist_admin'];
    $LoggedInWithCMSorHigher = $rollenInfo !== null && $rechteFlags['cms'];
    // Backstage-Bereich (Lila Balken, Settings, Infos/Verlauf, alle backstage_*-Artikel):
    // ausschließlich über das "backstage"-Flag. Wer dieses Flag nicht hat (z.B. Schiedsrichter*in,
    // die nur "alle_spiele" hat), kann sich zwar für's Spiele-Bearbeiten authentifizieren, sieht
    // aber nie den Backstage-Bereich - genau wie explizit gewünscht.
    $LoggedInWithBackstageOrHigher = $rollenInfo !== null && $rechteFlags['backstage'];
    // Session-Persistenz: bewusst an CMS ODER Backstage gekoppelt (nicht nur Backstage alleine),
    // sonst würde ein reiner Autor*in-Login (nur cms-Flag, kein backstage-Flag) nach jedem
    // Content-Speichern die Session verlieren und müsste sich ständig neu einloggen.
    if ($LoggedInWithCMSorHigher || $LoggedInWithBackstageOrHigher) {
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
        // ========================================================================================
        // FIXIERTE VIOLETTE ADMIN-LEISTE (neu eingeführt: "logged-in"-Erkennungsfarbe fürs ganze Backstage)
        // ========================================================================================
        // --admin-accent* wird auch von .admin-menu-button, .admin-toggle und dem Attribut-Selektor
        // #main article[id^='backstage_'] weiter unten genutzt, damit ALLE Backstage-Bereiche
        // konsistent violett markiert sind. Die color:#ffffff !important bei "#admin-bar .button" ist
        // ein gezielter Fix: die theme-eigene .button.primary-Regel setzt schwarze Schrift
        // (für weisse Buttons gedacht), was im violetten Admin-Bar-Kontext unlesbar war - die
        // ID-Selektor-Spezifität von #admin-bar gewinnt hier bewusst gegen die Klassen-Regel.
        // Die farbigen Rahmen sind nur zusammen mit der Farb-Legende sinnvoll interpretierbar - wer
        // die Legende nicht sehen darf (alles außer Admin/Co-Admin), soll deshalb auch keine
        // unterscheidbaren Rahmenfarben sehen, sondern den neutralen Standard-Rahmen wie vor Einführung
        // des Farbsystems. Fällt hier bewusst auf PHP-Ebene, nicht per CSS "display:none" o.ä., damit
        // Nicht-Admin/Co-Admin die Farbwerte gar nicht erst im Seitenquelltext bekommen.
        $adminBorderNeutral = 'rgba(255,255,255,0.15)';
        $adminBorderTeamsWert = $istAdminOderCoAdmin ? '#22c55e' : $adminBorderNeutral;
        $adminBorderStandardWert = $istAdminOderCoAdmin ? '#3b82f6' : $adminBorderNeutral;
        $adminBorderCoadminWert = $istAdminOderCoAdmin ? '#f59e0b' : $adminBorderNeutral;
        $adminBorderAdminonlyWert = $istAdminOderCoAdmin ? '#ef4444' : $adminBorderNeutral;
        $adminBorderCmsWert = $istAdminOderCoAdmin ? '#ec4899' : $adminBorderNeutral;
        echo "
        <style>
            :root {
                --admin-accent: #8b5cf6; --admin-accent-deep: #6d28d9; --admin-accent-light: #ddd6fe;
                /* Alle Backstage-Buttons tragen denselben Lila-Verlauf als Hintergrund - WER eine
                   Funktion sehen darf, zeigt stattdessen ein farbiger RAHMEN um den Button (siehe
                   .admin-menu-button--teams/--coadmin/--adminonly weiter unten). Vier deutlich
                   unterscheidbare, zum Lila passende Rahmenfarben: Grün (Teams-Recht reicht), Blau
                   (Standard-Einzelrecht), Bernstein (Admin+Co-Admin), Rot (nur echte Admins) - Grün
                   und Blau liegen bewusst weiter auseinander als vorher Türkis/Blau, damit man sie
                   auf den ersten Blick unterscheiden kann. Werte kommen aus PHP: nur Admin/Co-Admin
                   bekommen die echten Farben, alle anderen den neutralen Standard-Rahmen. */
                --admin-border-teams: $adminBorderTeamsWert;
                --admin-border-standard: $adminBorderStandardWert;
                --admin-border-coadmin: $adminBorderCoadminWert;
                --admin-border-adminonly: $adminBorderAdminonlyWert;
                /* Fünfte Stufe: braucht nur das cms-Flag (Autor*in), kein Backstage-Zugang nötig -
                   deshalb eigene Farbe statt einer der obigen vier, die alle backstage-artige
                   Rechte betreffen. */
                --admin-border-cms: $adminBorderCmsWert;
            }
            #admin-bar { position: fixed; top: 0; left: 0; width: 100%; z-index: 10000; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; padding: 0.5rem 1rem; background: rgba(30, 12, 48, 0.94); border-bottom: 2px solid var(--admin-accent); box-shadow: 0 2px 12px rgba(139, 92, 246, 0.35); box-sizing: border-box; }
            #admin-bar-status { color: var(--admin-accent-light); font-size: 0.8rem; display: flex; align-items: center; gap: 0.6rem; white-space: nowrap; }
            #admin-bar-status i { color: #fff; }
            #admin-bar-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
            #admin-bar-buttons form { margin: 0; display: inline; }
            #admin-bar .button { margin: 0; padding: 0.45rem 0.9rem; font-size: 0.8rem; white-space: nowrap; background: var(--admin-accent-deep); color: #ffffff !important; font-weight: 300 !important; }
            /* CMS-Button: eigene Farbstufe (siehe Farb-Legende in Settings/Infos) */
            #admin-bar .button--cms { border: 2px solid var(--admin-border-cms); }
            /* Settings-/Infos-Button: beide hängen nur am backstage-Flag, dieselbe Zielgruppe wie die
               grüne Stufe (Moderator*in, Backstage-Zugang, Co-Admin, Admin) */
            #admin-bar .button--teams { border: 2px solid var(--admin-border-teams); }
            #wrapper { padding-top: 64px; }
            .admin-menu-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; max-width: 640px; margin: 1rem auto; }
            .admin-menu-button { display: inline-block; min-width: 190px; margin: 0; padding: 0.5rem 1rem; font-size: 0.85rem; line-height: 1.2; border-radius: 6px; background: linear-gradient(135deg, var(--admin-accent-deep), var(--admin-accent)); border: 2px solid var(--admin-border-standard); color: #f5f2ff !important; text-transform: none; letter-spacing: 0.02em; text-align: center; text-decoration: none; }
            .admin-menu-button:hover { background: linear-gradient(135deg, var(--admin-accent), #a78bfa); }
            /* Alle drei Rechte-Stufen nutzen denselben Lila-Hintergrund wie der Standard-Button -
               einziger Unterschied ist die Rahmenfarbe (siehe :root weiter oben). */
            .admin-menu-button--coadmin { border-color: var(--admin-border-coadmin); }
            .admin-menu-button--adminonly { border-color: var(--admin-border-adminonly); }
            .admin-menu-button--teams { border-color: var(--admin-border-teams); }
            /* Farb-Legende auf der Settings-Übersicht (nur für Admin/Co-Admin sichtbar) */
            .admin-legende { max-width: 640px; margin: 1.5rem auto 0; padding: 0.8rem 1rem; border-radius: 8px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); font-size: 0.78rem; text-align: left; }
            .admin-legende h4 { margin: 0 0 0.5rem; font-size: 0.85rem; text-align: center; }
            .admin-legende-zeile { display: flex; align-items: flex-start; gap: 0.6rem; margin: 0.8rem 0; line-height: 1.5; }
            .admin-legende-zeile .admin-legende-swatch { margin-top: 0.15rem; }
            .admin-legende-swatch { display: inline-block; width: 1.1rem; height: 1.1rem; border-radius: 4px; flex-shrink: 0; background: rgba(255,255,255,0.06); border: 3px solid var(--admin-border-standard); }
            .admin-legende-swatch--teams { border-color: var(--admin-border-teams); }
            .admin-legende-swatch--coadmin { border-color: var(--admin-border-coadmin); }
            .admin-legende-swatch--adminonly { border-color: var(--admin-border-adminonly); }
            .admin-legende-swatch--cms { border-color: var(--admin-border-cms); }
            /* Technisch weiterhin eine Checkbox (onchange sendet das Formular ab), sieht jetzt aber
               bewusst wie ein echter, kompakter Button aus - nicht wie ein Häkchen zum Ankreuzen.
               Die Checkbox selbst wird komplett unsichtbar gemacht (aber bleibt klickbar/fokussierbar);
               das <label> drumherum trägt die eigentliche Button-Optik. Ein Klick irgendwo auf das
               Label toggelt laut HTML-Spec automatisch die verschachtelte Checkbox mit. */
            .admin-toggle { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; padding: 0.35rem 0.85rem; border-radius: 6px; background: var(--admin-accent-deep); border: 1px solid var(--admin-accent); color: #ffffff; font-size: 0.75rem; cursor: pointer; white-space: nowrap; transition: background-color 0.15s ease-in-out; }
            .admin-toggle::before { content: '✓'; }
            .admin-toggle:hover { background: var(--admin-accent); }
            .admin-toggle input[type='checkbox'] { position: absolute; opacity: 0; width: 0; height: 0; margin: 0; pointer-events: none; }
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
                // Bewusst .button OHNE .primary (wie Settings/Infos) - .primary bringt eigene
                // Schriftschnitt-Regeln aus dem Grundtheme mit, die hier für einen einheitlichen
                // Look in der Admin-Leiste nicht gewünscht sind.
                echo "<button type='submit' class='button button--cms'>CMS verlassen</button>";
            } else {
                echo "<input type='hidden' name='edit_content_mode' value='True'>
                <button type='submit' class='button button--cms'>CMS</button>";
            }
            echo "</form>";
        }
        // Infos/Verlauf ist Teil des Backstage-Bereichs -> ausschließlich backstage-Flag, kein Admin/Co-Admin-Shortcut
        $hatInfosVerlaufZugang = $rechteFlags['backstage'];
        if ($LoggedInWithBackstageOrHigher) {
            echo "<a href='#backstage_daten_bearbeiten' class='button button--teams'>Settings</a>";
        }
        if ($hatInfosVerlaufZugang) {
            echo "<a href='#backstage_info' class='button button--teams'>Infos</a>";
        }
        echo "
            </div>
        </div>
        ";
    }

    // ================================================================================================
    // ERFOLGSMELDUNGEN NACH BACKSTAGE-AKTIONEN (Session-Flash-Message, ueberlebt den Redirect)
    // ================================================================================================
    // Wird von edit_variables.php/edit_teams.php/edit_games.php per $_SESSION['flash_success'] gesetzt,
    // bevor dorthin weitergeleitet wird. Steht bewusst AUSSERHALB jedes <article>, damit sie auf
    // jedem Tab/Anker sichtbar ist (die Hash-Navigation blendet nur <article>-Elemente ein/aus).
    if (isset($_SESSION['flash_success']) && $_SESSION['flash_success']) {
        echo "<div style='max-width:640px;margin:1rem auto 0;padding:0.7rem 1rem;border-radius:8px;background:rgba(46,204,113,0.15);border:1px solid #2ecc71;color:#eafff2;text-align:center;font-size:0.9rem;'>&check; " . htmlspecialchars($_SESSION['flash_success']) . "</div>";
        unset($_SESSION['flash_success']);
    }
?>

<!-- ================================================================================================
     LADE-OVERLAY FÜR LANGSAME AKTIONEN MIT VIELEN DATENBANK-ÄNDERUNGEN
     ================================================================================================
     Betrifft alle Aktionen, die viele Datenbank-Zeilen anlegen oder eine db_update()-Neuberechnung
     direkt auslösen (Teams generieren, Zufällige Spiele eintragen, Gruppen für Gruppenphase
     generieren, Gruppeneinteilung losen) und dadurch spürbar dauern können. Damit man nicht denkt,
     der Klick sei "nicht angekommen" (und z.B. die Seite neu lädt oder mehrfach klickt), zeigt
     zeigeLadeHinweisUndSenden() sofort beim Absenden ein Overlay mit der ausdrücklichen Bitte, die
     Seite NICHT neu zu laden, bevor das Formular abgeschickt wird. -->
<div id='ladehinweis-overlay' style='display:none; position:fixed; inset:0; background:rgba(20,10,35,0.85); z-index:100000; align-items:center; justify-content:center; flex-direction:column; color:#fff; text-align:center; padding:2rem;'>
    <div style='font-size:1.4rem; margin-bottom:0.6rem;'>⏳ Einen Moment bitte ...</div>
    <div style='font-size:0.9rem; opacity:0.85;'>Das kann je nach Anzahl kurz dauern.</div>
    <div style='font-size:0.9rem; opacity:0.85; margin-top:0.4rem;'><b>Bitte die Seite jetzt nicht neu laden</b> - einfach abwarten, bis es fertig ist.</div>
</div>
<script>
    function zeigeLadeHinweisUndSenden(form) {
        var overlay = document.getElementById('ladehinweis-overlay');
        if (overlay) { overlay.style.display = 'flex'; }
        form.submit();
    }
</script>

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
        <img src="images/sterni_logo/logo_sterni.png" class="site-logo" alt="Home">
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
            // Datum + Titel jetzt in einer Zeile (Datum als kleines Label davor statt als eigene
            // Ueberschrift darueber), Untertitel darunter bewusst NICHT in Grossbuchstaben (das kam
            // vorher automatisch von der generischen "#header .content p"-Regel).
            echo"<div class='hero-heading'><span class='hero-date'>$anzeige_datum</span><h1>$anzeige_titel</h1></div>";
            echo"<p class='hero-subtitle'>$anzeige_subtitel</p>";
            ?>
            <?php /* cmsPrintSection($websiteId, $siteID, $TurnierID, 8, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); */ ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
        </div>
    </div>
    <!--<button onclick="insert_traffic($conn, 1, 'anonym', 1 , ' hat sich die Regeln angesehen')"> Click2 </button>-->
    <!-- Icons bewusst als schlichte, einfarbige Inline-SVGs statt der frueheren bunten Emojis -
         passt sich per currentColor der Textfarbe an und fuegt sich damit ins ansonsten monochrome
         Grundtheme der Website ein. -->
    <nav>
        <ul>
            <li><a href="#info">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.6" r="0.6" fill="currentColor" stroke="none"/></svg>
                <span>Info</span>
            </a></li>
            <li><a href="#regeln" onclick="insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich die Regeln angesehen');">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5C4 4.67 4.67 4 5.5 4H12v16H5.5A1.5 1.5 0 0 1 4 18.5v-13z"/><path d="M20 5.5c0-.83-.67-1.5-1.5-1.5H12v16h6.5c.83 0 1.5-.67 1.5-1.5v-13z"/></svg>
                <span>Regeln</span>
            </a></li>
            <li><a href='#teams'>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.3 14.3c2.6.4 4.7 2.5 5.2 5.2"/></svg>
                <span>Teams</span>
            </a></li>
            <?php
            //Aktuelle Turnierphase herausfinden - erstmal ID
                $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                $resultTurnier = $conn->query($sqlTurnier);
                while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                    $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                    $schnee = $rowTurnier['schnee'];
                }

                //SPIELPLAN
                $spielplanIstAktiv = ($turnier_phase_ID == 4 || $turnier_phase_ID == 5 || $turnier_phase_ID == 7 || $turnier_phase_ID == 9 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13);
                $spielplanKlasse = $spielplanIstAktiv ? '' : " class='disabled'";
                echo "<li><a href='#spielplan'$spielplanKlasse>
                    <svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'><rect x='3.5' y='5' width='17' height='15' rx='2'/><line x1='3.5' y1='9.5' x2='20.5' y2='9.5'/><line x1='7.5' y1='3' x2='7.5' y2='6.5'/><line x1='16.5' y1='3' x2='16.5' y2='6.5'/></svg>
                    <span>Spielplan</span>
                </a></li>";
            ?>
            <li><a href="https://www.paypal.com/paypalme/blankiball?country.x=DE&locale.x=de_DE">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20.5S3.5 15.4 3.5 9.2C3.5 6.3 5.8 4 8.6 4c1.5 0 2.9.7 3.4 2 .5-1.3 1.9-2 3.4-2 2.8 0 5.1 2.3 5.1 5.2 0 6.2-8.5 11.3-8.5 11.3z"/></svg>
                <span>Spenden</span>
            </a></li>
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
            changeContent($conn, $TurnierID, $cid, $ccontent, $cstyle, $cfunc, $corder, $bn, $pw);
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
    <?php if (isset($_POST['contentID'])) { addContent($_POST['contentID'], $TurnierID, $bn, $pw); } ?>
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
    <div class="cms-card">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 1, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    </div>
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
        echo "<div class='cms-card'>";
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
        echo "</div>";
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
              <div class="phase-card phase-card--gruppen">
                <h3><img class="icon" src="images/icon/sterni1.png" alt="Icon"> Gruppenphase</h3>
                <p class="muted">Alle Teams werden in Gruppen eingeteilt und spielen dort im Modus Jede*r gegen Jede*n. Die besten Teams jeder Gruppe ziehen in die KO-Phase ein.</p>
                <a href="#gruppenphase" class="button primary">Zur Gruppenphase</a>
              </div>
              <div class="phase-card phase-card--ko">
                <h3><img class="icon" src="images/icon/sterni2.png" alt="Icon"> KO-Phase</h3>
                <p class="muted">In der KO-Phase entscheidet jedes Spiel: Sieg bedeutet Weiterkommen - eine Niederlage das Ausscheiden. Verfolge den Weg durch den Turnierbaum.</p>
                <a href="#kophase" class="button primary">Zur KO-Phase</a>
              </div>
              <div class="phase-card phase-card--losing">
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
        <a href="#punktetabelle" class="ko-phase-btn ko-phase-btn--points">
            <span class="ko-btn-label">Punktetabelle</span>
            <span class="ko-btn-sub">Gruppenphase</span>
        </a>
    </div>
    <br/><br/>
    <?php  printSpielplanGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id, $rechteFlags['alle_spiele'], $bn, $pw); ?>
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
        <a href="#turnierbaum" class="ko-phase-btn ko-phase-btn--tree">
            <span class="ko-btn-label">Turnierbaum</span>
            <span class="ko-btn-sub">Alle KO-Matches</span>
        </a>
        <a href="#rangliste" class="ko-phase-btn ko-phase-btn--rank">
            <span class="ko-btn-label">Rangliste</span>
            <span class="ko-btn-sub">Live-Positionen</span>
        </a>
    </div>
    <br/><br/>
    <?php //cmsPrintSection( $websiteId, $siteID, $TurnierID, 13, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> 
    <?php printKO_PhaseTabellen($TurnierID, $conn, $LoggedInWithBackstageOrHigher, $gameEditMode, $expertenmodus, $test_turnier_id, $rechteFlags['turnier_settings'], $bn, $pw); ?>
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
        <a href="#rangliste" class="ko-phase-btn ko-phase-btn--rank">
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
    <div class="cms-card">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 15, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->
    </div>
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
    <!-- ================================================================================================
         LOGIN-EINSTIEGSSEITE (Ziel des "Backstage"-Links im Footer) - KOMPAKT, ABER MIT LUFT ZWISCHEN
         DEN VIER BEREICHEN (Testmodus / Login / Registrieren / Anzahl Websitebesuche)
         ================================================================================================
         Nicht mehr die alten "<p></br></p>"-Doppel-Abstandshalter, aber auch nicht komplett ohne Luft -
         jeder Bereich ist ein .login-section-Block mit moderatem margin-bottom, und Dropdown/Button
         innerhalb eines Formulars haben ueber .login-form-row einen kleinen eigenen Abstand.
         "Anzahl Websitebesuche" ist an den Schluss gerueckt (unwichtig fuer den eigentlichen Login-
         Zweck). Pausenraum-Link, das CMS-Inhalte-Paket direkt danach (Section 18), Rangliste- und
         Bookmark-Link sind auf Wunsch auskommentiert - "Registrieren" bleibt bewusst aktiv. -->
    <style>
        .login-section { margin-bottom: 1.6rem; }
        .login-form-row { margin-bottom: 0.6rem; }
    </style>
    <div class='login-section'>
        <h2>Testmodus</h2>
        <p>Der Testmodus ist dafür da, alle Funktionen der Website auszuprobieren. Der Testmodus läuft mit einem Test-Turnier mit ausgedachten Teams.</p>
        <form method='post' action='#'>
            <div class='login-form-row'>
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
            </div>
            <button name='content' class='button primary'>Testmodus starten</button>
        </form>
    </div>

    <?php if (isset($_SESSION['flash_success_register_account']) && $_SESSION['flash_success_register_account']) { ?>
        <div class='login-section' style="padding:10px;border:1px solid #27ae60;border-radius:6px;background:#ecf9f0;color:#27ae60;">
            <?php echo htmlspecialchars($_SESSION['flash_success_register_account'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_success_register_account']); ?>
        </div>
    <?php } ?>
    <?php if (isset($_SESSION['flash_error_register_account']) && $_SESSION['flash_error_register_account']) { ?>
        <div class='login-section' style="padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;">
            <?php echo htmlspecialchars($_SESSION['flash_error_register_account'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error_register_account']); ?>
        </div>
    <?php } ?>

    <div class='login-section' id="LogIn">
        <h2>Login (CMS &amp; Backstage)</h2>
        <?php
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='/' method='POST'>";
        }else{ //Testturniere
            echo "<form action='/?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        ?>
        <div class='login-form-row'>
            <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
            <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
        </div>
        <!--<input type="submit" value="Absenden" style="color: black"/> -->
        <button value="Anmelden" type="submit">Anmelden</button>
        </form>
    </div>

    <div class='login-section'>
        <h2>Registrieren</h2>
        <p>Noch keinen Account? Hier kannst du einen erstellen. Sag danach einfach Richard Bescheid, damit er dich freischalten kann.</p>
        <a href='#register_account' class='button primary'>Registrieren</a>
    </div>

    <!-- Auf Wunsch auskommentiert: Pausenraum-Link, CMS-Inhalte-Paket (Section 18), Rangliste- und Bookmark-Link.
         Der PHP-Aufruf ist bewusst NICHT nur in einen HTML-Kommentar gepackt (PHP-Tags werden auch
         innerhalb von HTML-Kommentaren weiterhin ausgefuehrt), sondern per PHP-Kommentar deaktiviert. -->
    <!-- <a href="#pausenraum">?? Pausenraum</a> -->
    <?php /* cmsPrintSection($websiteId, $siteID, $TurnierID, 18, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); */ ?>
    <!-- <a href='#rangliste' class='button primary'>Rangliste</a> -->
    <!-- <a id="bookmark-this" href="#" title="Bookmark This Page">Bookmark This Page</a> -->

    <div class='login-section'>
        <h2>Anzahl Websitebesuche</h2>
        <?php echo"<p>$anzahlWebsiteBesuche</p>"; ?>
    </div>

    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- ################################################################################################ -->
<!-- ###  ACCOUNT REGISTRIEREN - eigenstaendige Selbstregistrierung, erreichbar ueber den          ### -->
<!-- ###  "Registrieren"-Button auf der #login-Seite. Neue Accounts bekommen bewusst NOCH KEINE     ### -->
<!-- ###  Rolle (nicht mal "Benutzer*in") - ein Admin/Co-Admin muss sie im Nutzermanagement erst    ### -->
<!-- ###  freischalten. Bot-Schutz ueber dasselbe Blankensteinpark-Bild-Captcha wie bei der          ### -->
<!-- ###  Team-Anmeldung (CaptchaBlanki), aber mit eigenem formKey "user_register" statt "register", ### -->
<!-- ###  damit sich die beiden unabhaengigen Captcha-Ablaeufe nicht gegenseitig ueberschreiben.     ### -->
<!-- ################################################################################################ -->
<article id="register_account">
    <a href='#login' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Account registrieren</h1>
    <p>Nach der Registrierung hat dein Account noch <b>keinerlei Rechte</b> - sag danach einfach Richard Bescheid, damit er dich im Nutzermanagement freischalten kann.</p>
    <?php
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $regCaptchaFeedback = [
            'shouldShow' => false,
            'message' => null,
            'remaining' => 3,
            'ok' => false,
            'reloadNotice' => null,
        ];
        if (!empty($_SESSION['captcha_attempted_user_register'])) {
            $regCaptchaFeedback['remaining'] = isset($_SESSION['captcha_remaining_user_register'])
                ? (int)$_SESSION['captcha_remaining_user_register']
                : 3;
            $regCaptchaFeedback['message'] = isset($_SESSION['flash_error_user_register'])
                ? $_SESSION['flash_error_user_register']
                : null;
            $regCaptchaFeedback['ok'] = ($regCaptchaFeedback['message'] && stripos($regCaptchaFeedback['message'], 'best') !== false);
            $regCaptchaFeedback['shouldShow'] = $regCaptchaFeedback['ok'] || $regCaptchaFeedback['remaining'] <= 2;
            if ($regCaptchaFeedback['message'] && stripos($regCaptchaFeedback['message'], 'Captcha 3x fehlgeschlagen') !== false) {
                $regCaptchaFeedback['reloadNotice'] = $regCaptchaFeedback['message'];
                $regCaptchaFeedback['shouldShow'] = true;
            }
            unset($_SESSION['captcha_attempted_user_register'], $_SESSION['captcha_remaining_user_register']);
        }
        $regPrevBn = isset($_SESSION['register_account_form_data']['reg_bn']) ? $_SESSION['register_account_form_data']['reg_bn'] : '';
        unset($_SESSION['register_account_form_data']);
    ?>
    <?php if (!empty($regCaptchaFeedback['reloadNotice'])) { ?>
        <div class="cb-status-global" style="margin:10px 0;padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;">
            <?php echo htmlspecialchars($regCaptchaFeedback['reloadNotice'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>
    <?php if ($regCaptchaFeedback['shouldShow']) {
        $regAttemptLabel = ($regCaptchaFeedback['remaining'] === 1) ? 'Versuch' : 'Versuche';
        $regBorderColor = $regCaptchaFeedback['ok'] ? '#27ae60' : '#c0392b';
        $regBgColor = $regCaptchaFeedback['ok'] ? '#ecf9f0' : '#ffeaea';
        $regTextColor = $regCaptchaFeedback['ok'] ? '#27ae60' : '#c0392b';
        echo '<div class="cb-status-global" style="margin:10px 0;padding:10px;border:1px solid '. $regBorderColor .';border-radius:6px;background:'. $regBgColor .';color:'. $regTextColor .';">';
        if ($regCaptchaFeedback['message']) {
            echo htmlspecialchars($regCaptchaFeedback['message'], ENT_QUOTES, 'UTF-8');
            if (stripos($regCaptchaFeedback['message'], 'Verbleibende Versuch') === false) {
                echo '<br \>Verbleibende '. $regAttemptLabel .': '. $regCaptchaFeedback['remaining'];
            }
        } else {
            echo 'Verbleibende '. $regAttemptLabel .': '. $regCaptchaFeedback['remaining'];
        }
        echo '</div>';
    } ?>
    <?php
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='website_datachange/edit_account.php' method='POST' onSubmit='return checkRegisterAccount(event)'>";
        }else{ //Testturniere
            echo "<form action='website_datachange/edit_account.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkRegisterAccount(event)'>";
        }
    ?>
        <input type="text" id="reg_bn" name="reg_bn" class="Eingabe" placeholder="Gewünschter Benutzername &#9733;" style="color: white" maxlength="40" required autocomplete="username" value="<?php echo htmlspecialchars($regPrevBn, ENT_QUOTES, 'UTF-8'); ?>"><br/>
        <input type="password" id="reg_pw" name="reg_pw" class="Eingabe" placeholder="Passwort wählen &#9733;" style="color: white" required autocomplete="new-password"><br/>
        <input type="password" id="reg_pw2" name="reg_pw2" class="Eingabe" placeholder="Passwort wiederholen &#9733;" style="color: white" required autocomplete="new-password"><br/>
        <div class='field half' style='margin-top:0.4rem;'>
            <input type='checkbox' id='reg_pw_zeigen' onclick="var t = this.checked ? 'text' : 'password'; document.getElementById('reg_pw').type = t; document.getElementById('reg_pw2').type = t;">
            <label for='reg_pw_zeigen'>Passwort anzeigen</label>
        </div>
        <h5><br/></h5>
        <?php
            require_once __DIR__ . '/website_functionalities/captcha_blanki.php';
            echo '<div id="register-account-captcha"></div>';
            CaptchaBlanki::render('user_register');
            $regCaptchaPassed = CaptchaBlanki::passed('user_register');
        ?>
        <h5><br/></h5>
        <script type="text/javascript">
            function checkRegisterAccount(evt) {
                try {
                    var submitter = evt && (evt.submitter || document.activeElement);
                    if (submitter && submitter.name === 'cb_action' && submitter.value === 'check') {
                        return true;
                    }
                } catch (e) {}
                var pw1 = document.getElementById('reg_pw');
                var pw2 = document.getElementById('reg_pw2');
                if (pw1 && pw2 && pw1.value !== pw2.value) {
                    alert('Die beiden Passwörter stimmen nicht überein.');
                    return false;
                }
                return true;
            }
        </script>
        <input type='hidden' name='action' value='register'/>
        <?php $regSubmitDisabledAttr = $regCaptchaPassed ? '' : ' disabled'; ?>
        <p><button value="Registrieren" type="submit"<?php echo $regSubmitDisabledAttr; ?>>Registrieren</button></p>
    </form>
    <p></br></p>
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
        <style>
            .admin-menu-list { display: flex; flex-direction: column; gap: 0.5rem; max-width: 420px; margin: 1rem auto; }
            .admin-menu-list a.admin-menu-button { display: flex; align-items: center; gap: 0.7rem; text-align: left; min-width: 0; }
            .admin-menu-list .amn-num { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--admin-accent-deep); border: 1px solid var(--admin-accent); flex-shrink: 0; font-size: 0.75rem; }
            /* Testmodus-Variante der Menü-Buttons: gleiche dunkelblaue Farbe wie die Testmodus-Leiste,
               damit auf einen Blick klar ist, dass diese Funktion nur im Testmodus existiert/wirkt. */
            .admin-menu-list a.admin-menu-button-testmodus { background: linear-gradient(135deg, #123a5c, #1e5c8f); }
            .admin-menu-list .amn-num-testmodus { background: #123a5c; border-color: #1e5c8f; }
            .settings-testmodus-hinweis { max-width: 420px; margin: 0.8rem auto; padding: 0.6rem 0.9rem; border-radius: 8px; background: #123a5c; border: 1px solid #1e5c8f; color: #ffffff; font-size: 0.85rem; text-align: left; }
        </style>
        <?php if ($test_turnier_id != 0) { ?>
        <div class='settings-testmodus-hinweis'>Du befindest dich im <b>Testmodus</b>. Alle Änderungen, die du hier vornimmst, betreffen ausschließlich dieses Testturnier - nicht das echte, laufende Turnier.</div>
        <?php } ?>
        <?php
        // ====================================================================================
        // RECHTE-AUDIT: SETTINGS-MENÜ NUR NOCH ÜBER DIE JEWEILIGEN EINZELNEN FLAGS SICHTBAR
        // ====================================================================================
        // "Neues Turnier anlegen", "Turnier Settings" und "Turnierphase" gehören laut Nutzer
        // alle zusammen zum turnier_settings-Recht. "Teams bearbeiten" braucht das teams-Flag.
        // "Begegnungen bearbeiten" bleibt bewusst Admin/Co-Admin-only (explizite frühere Vorgabe,
        // es gibt dafür kein eigenes Flag). "Nutzermanagement" ist sichtbar, sobald irgendein
        // Rollen-Vergabe-Recht vorhanden ist (neue_admins/neue_co_admins/restliche_rollen_vergeben) -
        // innerhalb der Seite wird dann ohnehin nur das gezeigt, wofür man selbst berechtigt ist.
        // "Teams generieren" ist NUR im Testmodus sichtbar (dunkelblau statt violett) - siehe
        // backstage_teams_generieren weiter unten, das Backend prüft zusätzlich unabhängig, dass
        // wirklich ein Testturnier (type=2) bearbeitet wird.
        $amnZaehler = 1;
        $hatIrgendeinRollenVergabeRecht = $rechteFlags['neue_admins'] || $rechteFlags['neue_co_admins'] || $rechteFlags['restliche_rollen_vergeben'];
        ?>
        <div class='admin-menu-list'>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_neues_turnier' class='admin-menu-button admin-menu-button--coadmin'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Neues Turnier anlegen</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_turnier_settings' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Turnier Settings</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_turnier_phase' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Turnierphase</a>
            <?php } ?>
            <?php if ($test_turnier_id != 0 && $rechteFlags['teams']) { ?>
            <a href='#backstage_teams_generieren' class='admin-menu-button admin-menu-button-testmodus'><span class='amn-num amn-num-testmodus'><?php echo $amnZaehler++; ?></span> Teams generieren</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_teams_bearbeiten' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Teams bearbeiten</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_gruppen_generieren' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Gruppen für Gruppenphase generieren</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_teams_gruppen_einsortieren' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Teams in Gruppen einsortieren</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_gruppeneinteilung_losen' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Gruppeneinteilung losen</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_ko_einzug_modus' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Einzug ins KO-System</a>
            <?php } ?>
            <?php if ($rechteFlags['turnier_settings']) { ?>
            <a href='#backstage_begegnungen_bearbeiten' class='admin-menu-button'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Begegnungen bearbeiten</a>
            <?php } ?>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_nutzermanagement' class='admin-menu-button admin-menu-button--coadmin'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Nutzermanagement</a>
            <?php } ?>
        </div>
        <?php if ($istAdminOderCoAdmin) { ?>
        <div class='admin-legende'>
            <h4>Farb-Legende</h4>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--cms'></span>
                <div>
                    <b>Pinker Rahmen</b> (nur beim CMS-Button oben in der Admin-Leiste, nicht im Settings-/Infos-Menü)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Autor*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--teams'></span>
                <div>
                    <b>Grüner Rahmen</b> (auch beim Settings- und Infos-Button oben in der Admin-Leiste)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Moderator*in, Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch'></span>
                <div>
                    <b>Blauer Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Moderator*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--coadmin'></span>
                <div>
                    <b>Bernsteinfarbener Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--adminonly'></span>
                <div>
                    <b>Roter Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Co-Admin, Autor*in, Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
        </div>
        <?php } ?>
        <h5><br/></h5>
        <a href='#' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<article id="backstage_verlauf">
    <div style='text-align: center'>
        <h2>Verlauf</h2>
        <?php // RECHTE-AUDIT: Traffic/DB-Verlauf enthalten sensible Daten (wer hat was geaendert,
        // welche Seiten wurden aufgerufen) - das ist bewusst NUR echten Admins vorbehalten (nicht
        // schon Co-Admin), nicht ab dem allgemeinen "backstage"-Flag. ?>
        <?php if (!$istEchterAdmin) { ?>
        <p>Keine ausreichende Berechtigung. Nur Admins dürfen den Verlauf einsehen.</p>
        <?php } else { ?>
        <div class='admin-menu-wrap'>
            <a href='#backstage_traffic' class='admin-menu-button admin-menu-button--adminonly'>Traffic</a>
            <a href='#backstage_letzte_aenderung' class='admin-menu-button admin-menu-button--adminonly'>DB-Verlauf</a>
        </div>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_info' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ########  Begegnungen bearbeiten  ######### -->
<article id="backstage_begegnungen_bearbeiten">
    <h1>Begegnungen bearbeiten</h1>
    <?php // RECHTE-AUDIT: nicht mehr Admin/Co-Admin-only, sondern wie die übrigen Turnier-Settings
    // (Turnierphase, Gruppen generieren, ...) am turnier_settings-Flag - damit können z.B. auch
    // Leute mit der Rolle "Backstage-Zugang" Begegnungen anlegen/sperren. ?>
    <?php if (!$rechteFlags['turnier_settings']) { ?>
    <p>Keine ausreichende Berechtigung. Begegnungen anlegen oder sperren erfordert die Turnier-Settings-Berechtigung.</p>
    <?php } else { ?>
    <!-- ============================================================================================
         BEGEGNUNGEN BEARBEITEN - NEU DESIGNT: ZWEI KLAR GETRENNTE, KOMPAKTE BEREICHE
         ============================================================================================
         Vorher liefen "Hinzufügen" und "Sperren/Löschen" optisch ineinander (nur eine Zwischen-
         Überschrift), die Submit-Buttons zeigten den rohen action-Namen (z.B. "Begegnung_Hinzufuegen")
         statt eines lesbaren Labels. Jetzt: eigene Kästen pro Bereich, dicke Trennlinie dazwischen,
         kompaktere Abstände, und die Buttons haben ein eigenes hidden action-Feld + ein sprechendes,
         sichtbares Label. -->
    <style>
        .bb-section { border: 1px solid rgba(139, 92, 246, 0.28); border-radius: 8px; padding: 0.9rem 1.1rem; margin-bottom: 1rem; text-align: left; }
        .bb-section h2 { margin: 0 0 0.4rem 0; }
        .bb-section .field { margin-bottom: 0.6rem; }
        .bb-section label { margin-bottom: 0.15rem; }
        .bb-trennlinie { border: none; border-top: 3px solid var(--admin-accent); margin: 1.2rem 0; }
    </style>

    <div class='bb-section'>
        <h2 class='major'>Begegnung hinzufügen</h2>
        <p>Legt eine neue Begegnung manuell an (z.B. Freundschaftsspiel oder Nachtrag). Sie bekommt automatisch den Status <b>„Green Card"</b> und wird dadurch von der automatischen Spielplan-Berechnung nie wieder überschrieben oder verworfen.</p>
        <form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBBegegnungHinzufuegen()'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
            <input type='hidden' name='action' value='Begegnung_Hinzufuegen'/>
            <div class='field'>
                <label for='demo-category'>Team 1 (Heimteam)</label>
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
            </div>
            <div class='field'>
                <label for='demo-category'>Team 2 (Auswärtsteam)</label>
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
            </div>
            <div class='field'>
                <label for='demo-category'>Phase</label>
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
            </div>
            <div class='field'>
                <label for='demo-category'>Bracket-Position <i>(nur bei K.-o.-Phase nötig, bei Gruppenphase/Losing Bracket bitte leer lassen)</i></label>
                <input type='number' name='ko_turnierbaumposition' min='1' class='Eingabe' placeholder='z.B. 1' style='color: white'>
                <p style='font-size:0.8rem;opacity:0.75;'>Die Position bestimmt den Platz im Turnierbaum dieser K.-o.-Runde. Bei falscher Position kann der Turnierbaum falsch angezeigt werden - im Zweifel vorher im „Turnierbaum" auf der Startseite nachsehen, welche Positionen in der gewählten Runde schon belegt sind.</p>
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
            <div class='field'>
                <input type='checkbox' id='demo-human-begegnung-hinzufuegen' name='demo-human-begegnung-hinzufuegen' unchecked>
                <label for='demo-human-begegnung-hinzufuegen'>Ich habe geprüft, dass Teams und Bracket-Position stimmen.</label>
            </div>
            <ul class='actions'>
                <li><input type='submit' value='Begegnung anlegen' class='primary' /></li>
                <li><input type='reset' value='Abbrechen' /></li>
            </ul>
        </form>
    </div>

    <hr class='bb-trennlinie'>

    <div class='bb-section'>
        <h2 class='major'>Begegnung sperren</h2>
        <p>Sperrt eine bestehende Begegnung (Status „gesperrt"). Gesperrte Begegnungen werden von der automatischen Spielplan-Berechnung nie wieder angefasst oder neu angelegt - genau dafür ist diese Funktion gedacht, wenn die Website versehentlich eine falsche Begegnung erzeugt hat. Für eingeloggte Personen mit ausreichender Berechtigung werden gesperrte Begegnungen danach weiterhin (ausgegraut) in der KO-Phase angezeigt, damit nachvollziehbar bleibt, was gesperrt wurde.</p>
        <form action='website_datachange/edit_games.php' method='POST' onSubmit='return checkAGBBegegnungSperren()'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
            <input type='hidden' name='action' value='Begegnung_Sperren'/>
            <div class='field'>
                <label for='demo-category'>Begegnung wählen</label>
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
            <div class='field'>
                <input type='checkbox' id='demo-human-begegnung-sperren' name='demo-human-begegnung-sperren' unchecked>
                <label for='demo-human-begegnung-sperren'>Ich habe die richtige Begegnung ausgewählt.</label>
            </div>
            <ul class='actions'>
                <li><input type='submit' value='Begegnung sperren' class='primary' /></li>
                <li><input type='reset' value='Abbrechen' /></li>
            </ul>
        </form>
    </div>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  Telefonnummern  ######### -->
<!-- ########################## -->
<article id="backstage_tel">
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Telefonnummern</h1>
    <?php // RECHTE-AUDIT: personenbezogene Daten (Telefonnummern) - war bisher ungeschuetzt per
    // direktem Hash-Link erreichbar. Jetzt am teams-Flag (wie Moderator*in). ?>
    <?php if (!$rechteFlags['teams']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <h3>Hier eine Übersicht aller Telefonnumern, um alle in eine Whatsapp-Gruppe hinzuzufügen.</h3>
    <h5><br /></h5>
    <form action='website_functionalities/vcard.php' method='POST'>
        <button id='btn_login_Absenden' class='button primary' value='Absenden' type='submit'>Kontakte aufs Handy importieren</button>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
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
    <?php } ?>
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
    <?php // RECHTE-AUDIT: keine personenbezogenen Daten, aber trotzdem interne Struktur - war
    // bisher ungeschuetzt per direktem Hash-Link erreichbar. Jetzt am backstage-Flag. ?>
    <?php if (!$rechteFlags['backstage']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <p>Hinweis: Einige Attribut-Namen haben sich mittlerweile geändert, es sind neue dazugekommen und einige wurden entfernt. Aber die Grundstruktur stimmt noch.</p>
    <span class='image main'><img src='images/er_diagram.jpg' alt='' /></span>
    <?php } ?>
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
        <?php if (!$rechteFlags['teams']) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else {
            // ================================================================================================
            // TEAMS BEARBEITEN - KOMPAKTE ÜBERSICHT (Teamname/Spielernamen bearbeiten ist auf eine eigene,
            // pro Team aufrufbare Detailseite ausgelagert, siehe backstage_team_bearbeiten_detail weiter unten)
            // ================================================================================================
            // Pro Zeile nur noch: Teamname + Spielernamen als reiner Text (wie auf der öffentlichen
            // Teamübersicht), ein "Bearbeiten"-Button zur Detailseite, Gruppe ändern (jetzt mit eigenem
            // "bestätigen"-Häkchen statt sofortigem onchange-Submit), Bearbeitungsrechte (Checkbox-Toggle)
            // und Abmelden (mit JS-confirm() als zweitem Bestätigungsschritt).
            $tbBnAttr = htmlspecialchars($bn, ENT_QUOTES);
            $tbPwAttr = htmlspecialchars($pw, ENT_QUOTES);

            $tbGruppen = [];
            $sqlTbGruppen = 'SELECT * FROM `Turnier_Gruppe` WHERE fk_turnier = ' . (int)$TurnierID . ' ORDER BY id';
            $resultTbGruppen = $conn->query($sqlTbGruppen);
            while ($rowTbGruppe = $resultTbGruppen->fetch_assoc()) { $tbGruppen[] = $rowTbGruppe; }
        ?>
        <style>
            .tb-compact-row { padding: 0.5rem 0.2rem; border-bottom: 1px solid rgba(139, 92, 246, 0.15); text-align: left; font-size: 0.82rem; }
            .tb-compact-main { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap; }
            .tb-compact-info { flex: 1 1 240px; min-width: 160px; }
            .tb-compact-info b { font-weight: 700; }
            .tb-compact-info .tb-spieler { opacity: 0.75; }
            .tb-compact-controls { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-top: 0.45rem; }
            .tb-compact-controls select { padding: 0.2rem 0.4rem; font-size: 0.78rem; min-width: 7rem; }
            .tb-compact-controls .button { padding: 0.2rem 0.55rem; font-size: 0.72rem; min-width: auto; }
            .tb-abmelden-btn { background: #7a2020; border-color: #a33; }
        </style>
        <?php
            $sqlTbTeams = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . (int)$TurnierID . ' ORDER BY id';
            $resultTbTeams = $conn->query($sqlTbTeams);
            while ($rowTbTeam = $resultTbTeams->fetch_assoc()) {
                $tId = (int)$rowTbTeam['id'];
                $tName = $rowTbTeam['name'];
                $tKuerzel = $rowTbTeam['kuerzel'];
                $tGruppeId = (int)$rowTbTeam['fk_gruppe'];
                $tBearbeitungsrechte = (int)$rowTbTeam['bearbeitungsrechte'];
                $tbEditUrl = ($test_turnier_id == 0) ? "?team_edit_id=$tId" : "?test_turnier_id=$test_turnier_id&team_edit_id=$tId";

                $tbSpielerNamen = [];
                $sqlTbSpielerListe = 'SELECT name FROM `Turnier_Spieler_in` WHERE fk_team = ' . $tId . ' ORDER BY id';
                $resultTbSpielerListe = $conn->query($sqlTbSpielerListe);
                while ($rowTbSpielerListe = $resultTbSpielerListe->fetch_assoc()) { $tbSpielerNamen[] = $rowTbSpielerListe['name']; }

                echo "<div class='tb-compact-row'>";

                // --- Zeile 1: Teamname/Spieler (fett) + Bearbeiten-Button in derselben Zeile ---
                echo "<div class='tb-compact-main'>";
                echo "<div class='tb-compact-info'><b>" . htmlspecialchars($tKuerzel) . " " . htmlspecialchars($tName) . "</b> &mdash; <span class='tb-spieler'>" . htmlspecialchars(implode(', ', $tbSpielerNamen)) . "</span></div>";
                echo "<a href='" . htmlspecialchars($tbEditUrl, ENT_QUOTES) . "#backstage_team_bearbeiten_detail' class='button'>Bearbeiten</a>";
                echo "</div>";

                // --- Zeile 2: Gruppe/Bearbeitungsrechte/Abmelden ---
                echo "<div class='tb-compact-controls'>";

                // --- Gruppe ändern (jetzt mit eigenem bestätigen-Häkchen statt sofortigem Submit) ---
                echo "
                <form action='website_datachange/edit_teams.php' method='POST' style='display:inline-flex;align-items:center;gap:0.3rem;margin:0;'>
                    <input type='hidden' name='TurnierID' value='" . (int)$TurnierID . "'>
                    <input type='hidden' name='bn' value='$tbBnAttr'>
                    <input type='hidden' name='pw' value='$tbPwAttr'>
                    <input type='hidden' name='action' value='change_group'>
                    <input type='hidden' name='team' value='$tId'>
                    <select name='gruppe'>";
                foreach ($tbGruppen as $tbGruppe) {
                    $gSel = ((int)$tbGruppe['id'] === $tGruppeId) ? "selected" : "";
                    echo "<option value='" . (int)$tbGruppe['id'] . "' $gSel>" . htmlspecialchars($tbGruppe['name']) . "</option>";
                }
                echo "
                    </select>
                    <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
                </form>";

                // --- Bearbeitungsrechte (ein Checkbox-Toggle statt zwei separater Buttons) ---
                $rechteCheckedAttr = ($tBearbeitungsrechte === 1) ? "checked" : "";
                echo "
                <form action='website_datachange/edit_teams.php' method='POST' style='display:inline-flex;align-items:center;gap:0.3rem;margin:0;'>
                    <input type='hidden' name='TurnierID' value='" . (int)$TurnierID . "'>
                    <input type='hidden' name='bn' value='$tbBnAttr'>
                    <input type='hidden' name='pw' value='$tbPwAttr'>
                    <input type='hidden' name='team' value='$tId'>
                    <input type='hidden' name='action' id='tb_rechte_action_$tId' value='rechte_geben'>
                    <input type='checkbox' id='tb_rechte_cb_$tId' $rechteCheckedAttr onchange=\"document.getElementById('tb_rechte_action_$tId').value = this.checked ? 'rechte_geben' : 'rechte_weg'; this.form.submit();\">
                    <label for='tb_rechte_cb_$tId'>Rechte</label>
                </form>";

                // --- Abmelden (zweiter Bestätigungsschritt per JS-confirm) ---
                echo "
                <form action='website_datachange/edit_teams.php' method='POST' style='display:inline;margin:0;' onsubmit=\"return confirm('Team " . htmlspecialchars($tKuerzel, ENT_QUOTES) . " wirklich abmelden? Das kann nicht rückgängig gemacht werden.');\">
                    <input type='hidden' name='TurnierID' value='" . (int)$TurnierID . "'>
                    <input type='hidden' name='bn' value='$tbBnAttr'>
                    <input type='hidden' name='pw' value='$tbPwAttr'>
                    <input type='hidden' name='action' value='Abmelden'>
                    <input type='hidden' name='Team_zum_abmelden' value='$tId'>
                    <button type='submit' class='button tb-abmelden-btn'>Abmelden</button>
                </form>";

                echo "</div></div>"; // .tb-compact-controls, .tb-compact-row
            }
        } ?>
        <h5><br/></h5>
        <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ################################################################################################ -->
<!-- ###  TEAM BEARBEITEN - DETAILSEITE (Teamname + Spielernamen im Freitext, je einzeln bestätigt)  ### -->
<!-- ################################################################################################ -->
<!-- Wird über "?team_edit_id=<id>#backstage_team_bearbeiten_detail" von der kompakten Übersicht aus
     aufgerufen (gleiches Prinzip wie z.B. "?spielerId=<id>#spielerinfo"). Da index.php bei jedem
     Aufruf komplett neu gerendert wird, reicht der GET-Parameter, um zu wissen, welches Team gemeint ist. -->
<article id="backstage_team_bearbeiten_detail">
    <div style='text-align: center'>
        <h2>Team bearbeiten</h2>
        <?php if (!$rechteFlags['teams']) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else {
            $tdId = isset($_GET['team_edit_id']) ? (int)$_GET['team_edit_id'] : 0;
            $sqlTd = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = ' . $tdId . ' AND fk_turnier = ' . (int)$TurnierID;
            $resultTd = $conn->query($sqlTd);
            $rowTd = $resultTd ? $resultTd->fetch_assoc() : null;
            if ($rowTd === null) { ?>
            <p><i>Team nicht gefunden.</i></p>
            <?php } else {
                $tdBnAttr = htmlspecialchars($bn, ENT_QUOTES);
                $tdPwAttr = htmlspecialchars($pw, ENT_QUOTES);
            ?>
            <style>
                .td-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin: 0.5rem 0; text-align: left; max-width: 480px; margin-left: auto; margin-right: auto; }
                .td-row label.td-label { min-width: 90px; font-size: 0.85rem; opacity: 0.85; }
                .td-row input[type='text'] { min-width: 200px; }
            </style>
            <p><b><?php echo htmlspecialchars($rowTd['kuerzel']); ?></b></p>

            <form action='website_datachange/edit_teams.php' method='POST' class='td-row'>
                <input type='hidden' name='TurnierID' value='<?php echo (int)$TurnierID; ?>'>
                <input type='hidden' name='bn' value='<?php echo $tdBnAttr; ?>'>
                <input type='hidden' name='pw' value='<?php echo $tdPwAttr; ?>'>
                <input type='hidden' name='action' value='Team_Name_Aendern'>
                <input type='hidden' name='team' value='<?php echo $tdId; ?>'>
                <label class='td-label'>Teamname:</label>
                <input type='text' name='neuer_teamname' value='<?php echo htmlspecialchars($rowTd['name'], ENT_QUOTES); ?>'>
                <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
            </form>

            <?php
            $sqlTdSpieler = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team = ' . $tdId . ' ORDER BY id';
            $resultTdSpieler = $conn->query($sqlTdSpieler);
            while ($rowTdSpieler = $resultTdSpieler->fetch_assoc()) {
                $tdSpielerId = (int)$rowTdSpieler['id'];
                echo "
                <form action='website_datachange/edit_teams.php' method='POST' class='td-row'>
                    <input type='hidden' name='TurnierID' value='" . (int)$TurnierID . "'>
                    <input type='hidden' name='bn' value='$tdBnAttr'>
                    <input type='hidden' name='pw' value='$tdPwAttr'>
                    <input type='hidden' name='action' value='Spieler_Name_Aendern'>
                    <input type='hidden' name='spieler' value='$tdSpielerId'>
                    <label class='td-label'>Spieler*in:</label>
                    <input type='text' name='neuer_spielername' value='" . htmlspecialchars($rowTdSpieler['name'], ENT_QUOTES) . "'>
                    <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
                </form>";
            }
            } ?>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_teams_bearbeiten' class='button'>Zurück zur Teamliste</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ################################################################################################ -->
<!-- ###  TEAMS IN GRUPPEN EINSORTIEREN (isolierte, vereinfachte Funktion nur fürs Gruppen-Zuordnen) ### -->
<!-- ################################################################################################ -->
<!-- Bewusst kein Drag&Drop: HTML5-Drag&Drop funktioniert auf Touch-Geräten (Handy-Browser) nicht
     zuverlässig ohne zusätzliche JS-Bibliothek. Stattdessen eine simple Liste mit einem Dropdown pro
     Team, alle Änderungen werden über EIN gemeinsames Formular erst ganz am Ende auf einmal
     abgeschickt (kein Auto-Submit pro Zeile wie bei der kompakten Teamliste). -->
<article id="backstage_teams_gruppen_einsortieren">
    <div style='text-align: center'>
        <h2>Teams in Gruppen einsortieren</h2>
        <?php if (!$rechteFlags['teams']) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else {
            $tgGruppen = [];
            $sqlTgGruppen = 'SELECT * FROM `Turnier_Gruppe` WHERE fk_turnier = ' . (int)$TurnierID . ' ORDER BY id';
            $resultTgGruppen = $conn->query($sqlTgGruppen);
            while ($rowTgGruppe = $resultTgGruppen->fetch_assoc()) { $tgGruppen[] = $rowTgGruppe; }

            if (count($tgGruppen) === 0) { ?>
            <p>Es gibt noch keine Gruppen für dieses Turnier. Bitte zuerst die Turnierphase anpassen - die Gruppen werden automatisch angelegt, sobald das Turnier in der passenden Phase ist.</p>
            <?php } else { ?>
            <p>Ordne jedem Team über das Dropdown eine Gruppe zu und bestätige ganz unten einmal gesammelt für alle Teams.</p>
            <style>
                .tg-team-row { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap; padding: 0.4rem 0.2rem; border-bottom: 1px solid rgba(139, 92, 246, 0.15); text-align: left; font-size: 0.85rem; max-width: 480px; margin: 0 auto; }
                .tg-team-row select { min-width: 9rem; padding: 0.25rem 0.4rem; }
            </style>
            <form action='website_datachange/edit_teams.php' method='POST'>
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
                <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
                <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
                <input type='hidden' name='action' value='Teams_Gruppen_Batch_Aendern'/>
                <?php
                $sqlTgTeams = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND fk_turnier = ' . (int)$TurnierID . ' ORDER BY id';
                $resultTgTeams = $conn->query($sqlTgTeams);
                while ($rowTgTeam = $resultTgTeams->fetch_assoc()) {
                    $tgId = (int)$rowTgTeam['id'];
                    $tgGruppeId = (int)$rowTgTeam['fk_gruppe'];
                    echo "<div class='tg-team-row'>";
                    echo "<span><b>" . htmlspecialchars($rowTgTeam['kuerzel']) . "</b> " . htmlspecialchars($rowTgTeam['name']) . "</span>";
                    echo "<select name='gruppe[$tgId]'>";
                    echo "<option value=''>- keine Gruppe -</option>";
                    foreach ($tgGruppen as $tgGruppe) {
                        $sel = ((int)$tgGruppe['id'] === $tgGruppeId) ? "selected" : "";
                        echo "<option value='" . (int)$tgGruppe['id'] . "' $sel>" . htmlspecialchars($tgGruppe['name']) . "</option>";
                    }
                    echo "</select>";
                    echo "</div>";
                }
                ?>
                <h5><br/></h5>
                <ul class='actions'>
                    <li><input type='submit' value='Bestätigen' class='primary'/></li>
                </ul>
            </form>
            <?php } ?>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ################################################################################################ -->
<!-- ###  GRUPPEN FÜR GRUPPENPHASE GENERIEREN (kapselt Turnierphase 4 als sauberen Einzel-Button)   ### -->
<!-- ################################################################################################ -->
<!-- Turnierphase 4 ("Gruppengröße neu bestimmen & Erstellen/Löschen") existierte vorher nur als
     Umweg über die allgemeine Turnierphasen-Auswahl - man musste manuell dorthin wechseln, die Seite
     neu laden (damit db_update() einmal mit Phase 4 läuft) und danach die Phase wieder zurückändern.
     Dieser Button kapselt genau diesen Ablauf in einem einzigen Request (siehe edit_variables.php,
     Aktion Gruppen_Fuer_Gruppenphase_Generieren): anzahl_gruppen setzen -> Phase auf 4 -> db_update()
     DIREKT serverseitig aufrufen (entspricht dem Reload, ohne dass ein Zwischenzustand für den Nutzer
     sichtbar wird) -> Phase auf die gewählte Folge-Phase (Standard: 13, "Turnier läuft/Anmeldung
     noch möglich") setzen. -->
<article id="backstage_gruppen_generieren">
    <div style='text-align: center'>
        <h2>Gruppen für Gruppenphase generieren</h2>
        <?php if (!$rechteFlags['turnier_settings']) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else {
            $ggRow = $conn->query('SELECT anzahl_gruppen FROM Turnier_Main WHERE id = ' . (int)$TurnierID)->fetch_assoc();
            $ggAktuelleAnzahl = (int)$ggRow['anzahl_gruppen'];

            $ggPhasen = [];
            $resultGgPhasen = $conn->query('SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order');
            while ($rowGgPhase = $resultGgPhasen->fetch_assoc()) { $ggPhasen[] = $rowGgPhase; }
            $ggFolgePhaseName = '?';
            foreach ($ggPhasen as $p) { if ((int)$p['id'] === 13) { $ggFolgePhaseName = $p['name']; } }
        ?>
        <p>Legt die Gruppen für dieses Turnier neu an (bzw. passt sie an), indem kurzzeitig die Turnierphase "Gruppengröße neu bestimmen &amp; Erstellen/Löschen" durchlaufen wird. Anschließend wechselt das Turnier automatisch weiter zur unten gewählten Turnierphase (Standard: "<?php echo htmlspecialchars($ggFolgePhaseName); ?>").</p>
        <div class='ts-setting'>
            <span class='ts-setting-label'>Anzahl Gruppen</span>
            <span class='ts-hint'>Aktuell in Turnier Settings hinterlegt: <?php echo $ggAktuelleAnzahl; ?></span>
            <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
                <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
                <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
                <input type='hidden' name='action' value='Gruppen_Fuer_Gruppenphase_Generieren'/>
                <input type='number' name='anzahl_gruppen' min='1' value='<?php echo $ggAktuelleAnzahl; ?>' class='Eingabe ts-input'>
                <label for='gg_danach_phase' style='margin-left:0.8rem;'>Danach Turnierphase:</label>
                <select name='danach_turnierphase' id='gg_danach_phase' class='ts-input'>
                    <?php foreach ($ggPhasen as $p) {
                        $sel = ((int)$p['id'] === 13) ? "selected" : "";
                        echo "<option value='" . (int)$p['id'] . "' $sel>" . htmlspecialchars($p['name']) . "</option>";
                    } ?>
                </select>
                <label class='admin-toggle'><input type='checkbox' onchange='zeigeLadeHinweisUndSenden(this.form)'> <span>bestätigen</span></label>
            </form>
        </div>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ################################################################################################ -->
<!-- ###  GRUPPENEINTEILUNG LOSEN (kapselt Turnierphase 5 als sauberen Button)                     ### -->
<!-- ################################################################################################ -->
<!-- Analog zu "Gruppen für Gruppenphase generieren", aber für Turnierphase 5 ("Gruppeneinteilung" -
     würfelt Teams ohne Gruppe gleichmäßig auf die vorhandenen Gruppen). Jetzt auch für echte, laufende
     Turniere nutzbar (nicht mehr auf den Testmodus beschränkt) - gated nur noch über das
     turnier_settings-Flag, wie "Gruppen für Gruppenphase generieren" auch. -->
<article id="backstage_gruppeneinteilung_losen">
    <div style='text-align: center'>
        <h2>Gruppeneinteilung losen</h2>
        <?php if (!$rechteFlags['turnier_settings']) { ?>
        <p>Keine ausreichende Berechtigung.</p>
        <?php } else {
            $glPhasen = [];
            $resultGlPhasen = $conn->query('SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order');
            while ($rowGlPhase = $resultGlPhasen->fetch_assoc()) { $glPhasen[] = $rowGlPhase; }
            $glFolgePhaseName = '?';
            foreach ($glPhasen as $p) { if ((int)$p['id'] === 13) { $glFolgePhaseName = $p['name']; } }
        ?>
        <p>Würfelt alle Teams ohne Gruppe gleichmäßig auf die vorhandenen Gruppen, indem kurzzeitig die Turnierphase "Gruppeneinteilung" durchlaufen wird. Anschließend wechselt das Turnier automatisch weiter zur unten gewählten Turnierphase (Standard: "<?php echo htmlspecialchars($glFolgePhaseName); ?>").</p>
        <div class='ts-setting'>
            <span class='ts-setting-label'>Danach Turnierphase</span>
            <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
                <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
                <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
                <input type='hidden' name='action' value='Gruppeneinteilung_Losen'/>
                <select name='danach_turnierphase' class='ts-input'>
                    <?php foreach ($glPhasen as $p) {
                        $sel = ((int)$p['id'] === 13) ? "selected" : "";
                        echo "<option value='" . (int)$p['id'] . "' $sel>" . htmlspecialchars($p['name']) . "</option>";
                    } ?>
                </select>
                <label class='admin-toggle'><input type='checkbox' onchange='zeigeLadeHinweisUndSenden(this.form)'> <span>bestätigen</span></label>
            </form>
        </div>
        <?php } ?>
        <h5><br/></h5>
        <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
        <h5><br /></h5>
    </div>
</article>

<!-- ########################## -->
<!-- ########  EINZUG INS KO-SYSTEM  ######### -->
<!-- ########################## -->
<article id="backstage_ko_einzug_modus">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Einzug ins KO-System</h1>
    <?php if (!$rechteFlags['turnier_settings']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        $keSqlSettings = 'SELECT * FROM `Turnier_Main` WHERE id = ' . $TurnierID;
        $keRowSettings = $conn->query($keSqlSettings)->fetch_assoc();
        $keAnzahlGruppen = (int)$keRowSettings['anzahl_gruppen'];
        $keStartKoFinallevel = (int)$keRowSettings['start_ko_finallevel'];
        $keAktuellerModus = (int)($keRowSettings['fk_ko_einzug_modus'] ?? 1);
        if ($keAktuellerModus <= 0) { $keAktuellerModus = 1; }
        $keBnAttr = htmlspecialchars($bn, ENT_QUOTES);
        $kePwAttr = htmlspecialchars($pw, ENT_QUOTES);
    ?>
    <p>Legt fest, nach welchem Schema die Gruppenplatzierungen auf die ersten K.-o.-Begegnungen verteilt werden. Wirkt nur, solange "Einzug K.-o.-Phase manuell anlegen" in den Turnier Settings <b>nicht</b> aktiviert ist - ist der Schalter aktiviert, wird stattdessen alles manuell über "Begegnungen bearbeiten" angelegt und diese Auswahl hier komplett ignoriert.</p>
    <div style='background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); border-radius:8px; padding:0.7rem 1rem; margin:0.8rem 0 1.2rem; font-size:0.85rem;'>
        <b>Aktuell eingestellt:</b> <?php echo $keAnzahlGruppen; ?> Gruppen, Start-K.-o.-Finalstufe „<?php
            $keFinallevelName = '?';
            $resKeFl = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . (int)$keStartKoFinallevel);
            if ($resKeFl && ($rowKeFl = $resKeFl->fetch_assoc())) { $keFinallevelName = $rowKeFl['name']; }
            echo htmlspecialchars($keFinallevelName);
        ?>“. <i>Nur hier zur Info - ändern kannst du das in den <a href='#backstage_turnier_settings'>Turnier Settings</a>.</i>
    </div>
    <?php
        $keModi = [];
        $resKeModi = $conn->query('SELECT * FROM Turnier_KO_Einzug_Modus ORDER BY sortierung ASC, id ASC');
        while ($resKeModi && ($rowKeModus = $resKeModi->fetch_assoc())) { $keModi[] = $rowKeModus; }
        foreach ($keModi as $modus) {
            $keKompat = koEinzugModusKompatibel($modus, $keAnzahlGruppen, $keStartKoFinallevel);
            $keIstAktuell = ((int)$modus['id'] === $keAktuellerModus);
            $keRandFarbe = $keIstAktuell ? 'var(--admin-accent)' : 'rgba(255,255,255,0.15)';
    ?>
        <div style='text-align:left; border:2px solid <?php echo $keRandFarbe; ?>; border-radius:8px; padding:0.8rem 1rem; margin-bottom:0.8rem;'>
            <h3 style='margin:0 0 0.3rem;'>
                <?php echo htmlspecialchars($modus['name']); ?>
                <?php if ($keIstAktuell) { ?><span style='font-size:0.7rem; opacity:0.8;'>(aktuell ausgewählt)</span><?php } ?>
            </h3>
            <p style='margin:0 0 0.5rem; font-size:0.85rem;'><?php echo htmlspecialchars($modus['beschreibung']); ?></p>
            <?php if ($keKompat['ok']) { ?>
            <p style='margin:0 0 0.5rem; font-size:0.8rem; color:#2ecc71;'>&check; Passt zur aktuellen Konfiguration (<?php echo htmlspecialchars($keKompat['grund']); ?>)</p>
            <?php } else { ?>
            <p style='margin:0 0 0.5rem; font-size:0.8rem; color:#e74c3c;'>&#9888; Aktuell nicht wählbar: <?php echo htmlspecialchars($keKompat['grund']); ?></p>
            <?php } ?>
            <?php if (!$keIstAktuell) { ?>
            <form action='website_datachange/edit_variables.php' method='POST' style='margin:0;'>
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
                <input type='hidden' name='bn' value='<?php echo $keBnAttr; ?>'/>
                <input type='hidden' name='pw' value='<?php echo $kePwAttr; ?>'/>
                <input type='hidden' name='action' value='Turnier_Settings_Feld_Aendern'/>
                <input type='hidden' name='feld' value='fk_ko_einzug_modus'/>
                <input type='hidden' name='wert' value='<?php echo (int)$modus['id']; ?>'/>
                <button type='submit' class='admin-menu-button' style='min-width:auto;'>Diesen Modus auswählen</button>
            </form>
            <?php } ?>
        </div>
    <?php } ?>
    <?php } ?>
    <h5><br/></h5>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  INFO  ######### -->
<!-- ########################## -->
<article id="backstage_info">
    <div style='text-align: center'>
        <h2>Infos</h2>
        <div class='admin-menu-wrap'>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_tel' class='admin-menu-button admin-menu-button--teams'>Telefonnummern</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_teampasswort' class='admin-menu-button admin-menu-button--teams'>Team-Passwörter</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_warteliste' class='admin-menu-button admin-menu-button--teams'>Warteliste</a>
            <?php } ?>
            <?php if ($rechteFlags['backstage']) { ?>
            <a href='#backstage_er_diagram' class='admin-menu-button admin-menu-button--teams'>ER-Diagramm</a>
            <?php } ?>
            <?php if ($istEchterAdmin) { ?>
            <a href='#backstage_verlauf' class='admin-menu-button admin-menu-button--adminonly'>Verlauf</a>
            <?php } ?>
        </div>
        <?php if ($istAdminOderCoAdmin) { ?>
        <div class='admin-legende'>
            <h4>Farb-Legende</h4>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--cms'></span>
                <div>
                    <b>Pinker Rahmen</b> (nur beim CMS-Button oben in der Admin-Leiste, nicht im Settings-/Infos-Menü)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Autor*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--teams'></span>
                <div>
                    <b>Grüner Rahmen</b> (auch beim Settings- und Infos-Button oben in der Admin-Leiste)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Moderator*in, Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch'></span>
                <div>
                    <b>Blauer Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Moderator*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--coadmin'></span>
                <div>
                    <b>Bernsteinfarbener Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--adminonly'></span>
                <div>
                    <b>Roter Rahmen</b><br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Co-Admin, Autor*in, Moderator*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
        </div>
        <?php } ?>
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
    <?php // RECHTE-AUDIT: personenbezogene Daten (Teilnehmer*innen-Namen) - war bisher ungeschuetzt
    // per direktem Hash-Link erreichbar. Jetzt am teams-Flag (wie Moderator*in). ?>
    <?php if (!$rechteFlags['teams']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
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
    <?php } ?>
    <a href='#backstage_info' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  TEAM-PASSWORT  ######### -->
<!-- ########################## -->
<article id="backstage_teampasswort">
    <h2>Team-Passwörter</h2>
    <?php // RECHTE-AUDIT: Team-Passwörter sind besonders sensibel - war bisher ungeschuetzt per
    // direktem Hash-Link erreichbar. Jetzt am teams-Flag (wie Moderator*in). ?>
    <?php if (!$rechteFlags['teams']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
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
    <?php } ?>
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
    <?php // Turnierphase gehört inhaltlich zu Turnier Settings, daher gleiches Flag wie dort ?>
    <?php if (!$rechteFlags['turnier_settings']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        // ============================================================================================
        // TURNIERPHASE - NEU DESIGNT: KURZE WARNUNG OBEN, SCHALTER IN DER MITTE, ERKLÄRUNG DARUNTER
        // ============================================================================================
        // Vorher stand ein sehr langer Fließtext VOR dem eigentlichen Dropdown - jetzt kommt zuerst nur
        // eine kurze, auffällige Warnung, direkt danach der eigentliche Schalter (im gleichen Kartenstil
        // wie bei "Turnier Settings", inkl. eigenem "bestätigen"-Häkchen statt separatem Submit-Button),
        // und erst darunter eine kompakte, pro Phase verständliche Erklärung. Die Erklärungstexte sind
        // hier bewusst hart codiert (nicht mehr 1:1 aus Turnier_Setting_Phasen.description übernommen),
        // weil sie anhand des tatsächlichen Verhaltens in database/db_update.php geschrieben wurden -
        // fällt eine Phase-ID hier nicht in die Liste, wird als Rückfallebene die DB-Beschreibung genutzt.
        $tpPhasenErklaerung = [
            1  => 'Es passiert nichts automatisch. Teams können sich noch nicht anmelden.',
            3  => 'Teams können sich anmelden. Sobald die maximale Teamanzahl erreicht ist, wechselt das Turnier automatisch zur Warteliste.',
            12 => 'Neu angemeldete Teams landen auf der Warteliste. Es passiert sonst nichts automatisch.',
            4  => 'Die Anzahl der Gruppen wird automatisch an die Teamanzahl angepasst - fehlende Gruppen werden angelegt, überzählige gelöscht. Nicht mehr über dieses Dropdown wählbar, dafür gibt es den eigenen Button "Gruppen für Gruppenphase generieren" in den Settings.',
            5  => 'Teams ohne Gruppe werden automatisch gleichmäßig auf die vorhandenen Gruppen verteilt. Nicht mehr über dieses Dropdown wählbar, dafür gibt es den eigenen Button "Gruppeneinteilung losen" in den Settings.',
            7  => 'Das Turnier läuft: Ergebnisse werden verarbeitet, Sieger*innen rücken automatisch in die nächste K.-o.-Runde nach.',
            13 => 'Wie "Turnier läuft", zusätzlich werden neu angemeldete Teams automatisch einer Gruppe zugeteilt (Nachmeldungen).',
            9  => 'Automatische Berechnungen sind deaktiviert - das Turnier ist abgeschlossen.',
            11 => 'Debug-Modus: führt ALLE Schritte der anderen Phasen gleichzeitig aus. Nur zum Testen, nicht im laufenden Betrieb verwenden!',
        ];

        $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
        $resultTurnier = $conn->query($sqlTurnier);
        $rowTurnier = $resultTurnier->fetch_assoc();
        $turnier_phase_ID_aktuell = $rowTurnier['fk_turnier_phase'];

        $sqlTurnierPhaseAktuell = 'SELECT * FROM `Turnier_Setting_Phasen` WHERE id = '. (int)$turnier_phase_ID_aktuell;
        $resultTurnierPhaseAktuell = $conn->query($sqlTurnierPhaseAktuell);
        $rowTurnierPhaseAktuell = $resultTurnierPhaseAktuell->fetch_assoc();
        $turnier_phase_name_aktuell = $rowTurnierPhaseAktuell['name'] ?? '?';
    ?>
    <h1>Turnierphase</h1>
    <p style='color:#e74c3c'><b>⚠ Achtung:</b> Die Turnierphase steuert automatische Berechnungen im Hintergrund (z.B. Gruppeneinteilung, Nachrücken im Turnierbaum). Falsch gesetzt kann sie Daten durcheinanderbringen. Eine kurze Erklärung der einzelnen Phasen steht weiter unten - im Zweifel lieber vorher jemanden fragen.</p>

    <div class='ts-setting'>
        <span class='ts-setting-label'>Turnierphase</span>
        <span class='ts-hint'>Aktuell: <b><?php echo htmlspecialchars($turnier_phase_name_aktuell); ?></b></span>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
            <input type='hidden' name='action' value='Tunierphase ändern'/>
            <select name='Phase' class='ts-input'>
                <?php
                // Phase 4 ("Gruppengröße neu bestimmen & Erstellen/Löschen") und 5 ("Gruppeneinteilung")
                // sind hier bewusst NICHT wählbar - dafür gibt es jetzt eigene Buttons in den Settings
                // ("Gruppen für Gruppenphase generieren"/"Gruppeneinteilung losen"). Datenbanktechnisch
                // bleiben beide Phasen weiterhin gültige Werte, nur eben nicht über dieses Dropdown.
                $sqlTurnierPhase = 'SELECT * FROM `Turnier_Setting_Phasen` WHERE id NOT IN (4, 5) ORDER BY logical_order';
                $resultTurnierPhase = $conn->query($sqlTurnierPhase);
                while ($rowTurnierPhase = $resultTurnierPhase->fetch_assoc()) {
                    $selTp = ($rowTurnierPhase['id'] == $turnier_phase_ID_aktuell) ? "selected" : "";
                    echo "<option value='" . $rowTurnierPhase['id'] . "' $selTp>" . htmlspecialchars($rowTurnierPhase['name']) . "</option>";
                }
                ?>
            </select>
            <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
        </form>
    </div>

    <h3>Was bedeuten die einzelnen Phasen?</h3>
    <div style='text-align:left; max-width:640px; margin:0 auto;'>
        <?php
        $sqlTurnierPhaseListe = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
        $resultTurnierPhaseListe = $conn->query($sqlTurnierPhaseListe);
        while ($rowTurnierPhaseListe = $resultTurnierPhaseListe->fetch_assoc()) {
            $tpId = (int)$rowTurnierPhaseListe['id'];
            $tpErklaerung = $tpPhasenErklaerung[$tpId] ?? $rowTurnierPhaseListe['description'];
            echo "<p style='margin:0.4rem 0;'><b>" . htmlspecialchars($rowTurnierPhaseListe['name']) . ":</b> " . htmlspecialchars($tpErklaerung) . "</p>";
        }
        ?>
    </div>
    <h5><br /></h5>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  TEAMS GENERIEREN (nur im Testmodus - legt automatisch N Testteams inkl. Spieler*innen an) ### -->
<!-- ################################################################################################ -->
<!-- Erzeugt Teams mit Kürzel = Passwort (z.B. "T5"/"T5"), damit einzelne Team-Logins beim Testen
     leicht nachvollzogen werden können. Backend (edit_teams.php, Aktion Teams_Generieren) prüft
     zusätzlich unabhängig, dass wirklich ein Testturnier (type=2) bearbeitet wird - diese Funktion
     darf niemals Teams im echten, laufenden Turnier anlegen. -->
<article id="backstage_teams_generieren">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php if ($test_turnier_id == 0 || !$rechteFlags['teams']) { ?>
    <p>Diese Funktion ist nur im Testmodus verfügbar.</p>
    <?php } else { ?>
    <h1>Teams generieren</h1>
    <p>Legt automatisch die gewünschte Anzahl an Testteams für dieses Testturnier an, inklusive je 3 zufällig benannter Spieler*innen. Teamkürzel und Teampasswort sind dabei immer identisch (z.B. Kürzel "T5" &rarr; Passwort "T5"), damit sich einzelne Team-Zugänge beim Testen leicht merken lassen.</p>
    <div class='ts-setting'>
        <span class='ts-setting-label'>Anzahl Testteams</span>
        <form action='website_datachange/edit_teams.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
            <input type='hidden' name='action' value='Teams_Generieren'/>
            <input type='number' name='anzahl_testteams' min='1' max='100' value='10' class='Eingabe ts-input'>
            <label class='admin-toggle'><input type='checkbox' onchange='zeigeLadeHinweisUndSenden(this.form)'> <span>bestätigen</span></label>
        </form>
    </div>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  ZUFÄLLIGE SPIELE EINTRAGEN (nur im Testmodus - von Buttons in Gruppenphase/KO-Phase aus) ### -->
<!-- ################################################################################################ -->
<!-- Wird über "?...&zufall_scope=gruppenphase" bzw. "&zufall_scope=ko&zufall_ko_finallevel=<id>"
     aufgerufen (Buttons dazu in printSpielplanGruppenphase/printKO_PhaseTabellen). Backend
     (edit_games.php, Aktion Zufaellige_Spiele_Eintragen) prüft zusätzlich unabhängig, dass wirklich
     ein Testturnier (type=2) bearbeitet wird - darf niemals Ergebnisse im echten Turnier eintragen. -->
<article id="backstage_zufaellige_spiele">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php
    $zsScope = isset($_GET['zufall_scope']) ? $_GET['zufall_scope'] : '';
    $zsKoFinallevel = isset($_GET['zufall_ko_finallevel']) ? (int)$_GET['zufall_ko_finallevel'] : 0;
    if ($test_turnier_id == 0 || !$rechteFlags['alle_spiele'] || !in_array($zsScope, ['gruppenphase', 'ko'], true)) {
    ?>
    <p>Diese Funktion ist nur im Testmodus verfügbar.</p>
    <?php } else {
        if ($zsScope === 'gruppenphase') {
            $zsLabel = 'Gruppenphase';
            $sqlZsOffene = "SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = 0 AND status NOT IN (3,5,6,7) AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht=0 AND fk_turnier=?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht=0 AND fk_turnier=?)";
            $stmtZsOffene = $conn->prepare($sqlZsOffene);
            $stmtZsOffene->bind_param("ii", $TurnierID, $TurnierID);
        } else {
            $sqlZsName = 'SELECT name FROM Turnier_KO_Finallevel WHERE id = ?';
            $stmtZsName = $conn->prepare($sqlZsName);
            $stmtZsName->bind_param("i", $zsKoFinallevel);
            $stmtZsName->execute();
            $rowZsName = $stmtZsName->get_result()->fetch_assoc();
            $zsLabel = $rowZsName['name'] ?? "Finalstufe $zsKoFinallevel";
            $sqlZsOffene = "SELECT COUNT(*) AS anzahl FROM Turnier_Begegnung WHERE ko_finallevel = ? AND status NOT IN (3,5,6,7) AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht=0 AND fk_turnier=?) AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht=0 AND fk_turnier=?)";
            $stmtZsOffene = $conn->prepare($sqlZsOffene);
            $stmtZsOffene->bind_param("iii", $zsKoFinallevel, $TurnierID, $TurnierID);
        }
        $stmtZsOffene->execute();
        $anzahlOffeneZs = (int)$stmtZsOffene->get_result()->fetch_assoc()['anzahl'];
    ?>
    <h1>Zufällige Spiele eintragen</h1>
    <p><?php echo htmlspecialchars($zsLabel); ?>: aktuell <b><?php echo $anzahlOffeneZs; ?></b> offene Begegnung(en). Wähle, wie viel Prozent davon auf einen Schlag zufällig mit einem Ergebnis eingetragen und finalisiert werden sollen (Ergebnisse liegen wie im echten Spiel je Seite zwischen 0 und 3, es wird jeweils ein klarer Gewinner der gesamten Begegnung sichergestellt).</p>
    <form action='website_datachange/edit_games.php' method='POST'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
        <input type='hidden' name='action' value='Zufaellige_Spiele_Eintragen'/>
        <input type='hidden' name='zufall_scope' value='<?php echo htmlspecialchars($zsScope, ENT_QUOTES); ?>'/>
        <?php if ($zsScope === 'ko') { ?>
        <input type='hidden' name='zufall_ko_finallevel' value='<?php echo $zsKoFinallevel; ?>'/>
        <?php } ?>
        <div class='ts-setting'>
            <span class='ts-setting-label'>Prozent</span>
            <div class='ts-row'>
                <input type='number' name='prozent' min='1' max='100' value='100' class='Eingabe ts-input'> %
            </div>
        </div>
        <div class='ts-setting'>
            <span class='ts-setting-label'>Mehrere Spiele pro Begegnung anlegen</span>
            <span class='ts-hint'>Wenn aktiviert, wird pro ausgewählter Begegnung eine zufällige Anzahl Spiele (zwischen 1 und dem unten gewählten Maximum) statt immer nur genau einem angelegt.</span>
            <div class='ts-row'>
                <input type='checkbox' id='zs_mehrere_spiele' name='mehrere_spiele' value='1' onchange="document.getElementById('zs_max_spiele_row').style.display = this.checked ? '' : 'none';">
                <label for='zs_mehrere_spiele'>aktiviert</label>
            </div>
            <div class='ts-row' id='zs_max_spiele_row' style='display:none;'>
                <label for='zs_max_spiele'>Maximal</label>
                <input type='number' id='zs_max_spiele' name='max_spiele_pro_begegnung' min='2' max='9' value='3' class='Eingabe ts-input'>
                <span>Spiele pro Begegnung</span>
            </div>
        </div>
        <div class='ts-row'>
            <label class='admin-toggle'><input type='checkbox' onchange='zeigeLadeHinweisUndSenden(this.form)'> <span>bestätigen</span></label>
        </div>
    </form>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  NEUES TURNIER ANLEGEN (kopiert das laufende Turnier per generischem SELECT *-Row-Copy)  ### -->
<!-- ################################################################################################ -->
<!-- Realer Typ: altes Turnier wird zu "History" (type=3), Kopie wird das neue aktuelle Turnier.
     Testturnier-Typ: aktuelles Turnier bleibt komplett unangetastet, Kopie landet zusätzlich als
     Testturnier (type=2) und man wird direkt dorthin weitergeleitet. Die eigentliche Kopier-Logik
     (spaltenunabhängiges SELECT * + INSERT) steckt in edit_variables.php, Aktion Turnier_Neu_Anlegen. -->
<article id="backstage_neues_turnier">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php if (!$rechteFlags['turnier_settings']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        $sqlAltesTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = ' . (int)$TurnierID;
        $resultAltesTurnier = $conn->query($sqlAltesTurnier);
        $altesTurnier = $resultAltesTurnier ? $resultAltesTurnier->fetch_assoc() : null;
    ?>
    <h1>Neues Turnier anlegen</h1>
    <p>Legt eine Kopie des aktuell laufenden Turniers ("<?php echo htmlspecialchars($altesTurnier['name'] ?? ''); ?>") als Vorlage an. Bei einem <b>realen Turnier</b> wird das bisherige Turnier automatisch zu "History" und die Kopie zum neuen aktuellen Turnier. Bei einem <b>Testturnier</b> bleibt das aktuelle Turnier komplett unangetastet, die Kopie wird nur als zusätzliches Testturnier angelegt und du wirst direkt dorthin weitergeleitet. Alle hier nicht aufgeführten Einstellungen werden 1:1 von der Vorlage übernommen und können danach über "Turnier Settings" weiter angepasst werden.</p>
    <?php if ($altesTurnier === null) { ?>
        <p><i>Aktuelles Turnier konnte nicht geladen werden.</i></p>
    <?php } else { ?>
    <form action='website_datachange/edit_variables.php' method='POST' onSubmit='return checkAGBNeuesTurnier()'>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
        <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
        <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
        <input type='hidden' name='action' value='Turnier_Neu_Anlegen'/>
        <div class='field'>
            <label for='demo-category'>Turnier-Typ</label>
            <select name='neuer_turnier_type' id='neuer_turnier_type_select' onchange='neuesTurnierTypGeaendert()' required>
                <option value='1'>Reales Turnier (aktuelles Turnier wird zu History)</option>
                <option value='2'>Testturnier (aktuelles Turnier bleibt unangetastet)</option>
            </select>
            <h5><br/></h5>
            <label for='demo-category'>Name (intern)</label>
            <input type='text' name='name' value='<?php echo htmlspecialchars($altesTurnier['name'] ?? ''); ?>' class='Eingabe' style='color: white' required>
            <h5><br/></h5>
            <label for='demo-category'>Anzeige-Titel</label>
            <input type='text' name='anzeige_titel' value='<?php echo htmlspecialchars($altesTurnier['anzeige_titel'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Anzeige-Untertitel</label>
            <input type='text' name='anzeige_subtitel' value='<?php echo htmlspecialchars($altesTurnier['anzeige_subtitel'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Anzeige-Datum (freier Text, z.B. "26.-28. September")</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.1rem 0 0.3rem;'>Wird aktuell bewusst NICHT ausgefüllt, damit nicht jede/r auf der Website sieht, wann genau das Turnier stattfindet.</p>
            <input type='text' name='anzeige_datum' value='<?php echo htmlspecialchars($altesTurnier['anzeige_datum'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Jahr</label>
            <input type='text' name='jahr' value='<?php echo htmlspecialchars($altesTurnier['jahr'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Startdatum</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.1rem 0 0.3rem;'>Erster Turniertag. Wird u.a. genutzt, um vergangene Turniere ("History") in der richtigen Reihenfolge zu sortieren - ansonsten aktuell rein informativ.</p>
            <input type='date' name='startdatum' value='<?php echo htmlspecialchars($altesTurnier['startdatum'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Startzeit</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.1rem 0 0.3rem;'>Uhrzeit des Turnierstarts. Aktuell rein informativ, wird sonst an keiner Stelle automatisch ausgewertet.</p>
            <input type='text' name='startzeit' value='<?php echo htmlspecialchars($altesTurnier['startzeit'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Countdown-Start</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.1rem 0 0.3rem;'>Bestimmt, worauf der Countdown auf der Startseite herunterzählt. Braucht genau dieses Format: "Sep 06, 2025 14:00:00".</p>
            <input type='text' name='countdown_start' value='<?php echo htmlspecialchars($altesTurnier['countdown_start'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Enddatum</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.1rem 0 0.3rem;'>Letzter Turniertag. Aktuell rein informativ, wird sonst an keiner Stelle automatisch ausgewertet.</p>
            <input type='date' name='enddatum' value='<?php echo htmlspecialchars($altesTurnier['enddatum'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Maximale Teamanzahl</label>
            <input type='number' name='max_anzahl_teams' min='0' value='<?php echo (int)($altesTurnier['max_anzahl_teams'] ?? 0); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Teilnahmebeitrag (in Euro)</label>
            <input type='text' name='teilnahmebeitrag' value='<?php echo htmlspecialchars($altesTurnier['teilnahmebeitrag'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Anzeige-Reihenfolge auf der Website (order_on_website)</label>
            <input type='number' name='order_on_website' value='<?php echo (int)($altesTurnier['order_on_website'] ?? 0) + 1; ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Turnierphase (Start des neuen Turniers)</label>
            <select name='fk_turnier_phase'>
                <?php
                $sqlPhaseNeuesTurnier = 'SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order';
                $resultPhaseNeuesTurnier = $conn->query($sqlPhaseNeuesTurnier);
                while ($rowPhaseNeuesTurnier = $resultPhaseNeuesTurnier->fetch_assoc()) {
                    $pId = $rowPhaseNeuesTurnier['id'];
                    $pName = $rowPhaseNeuesTurnier['name'];
                    $sel = ($pId == 1) ? "selected" : ""; // Default: "Noch keine Anmeldung möglich" - frischer Start
                    echo "<option value='$pId' $sel>" . htmlspecialchars($pName) . "</option>";
                }
                ?>
            </select>
            <p><i>Voreingestellt auf einen frischen Start. Kann auch auf die aktuelle Phase des alten Turniers gesetzt werden, falls gewünscht.</i></p>
            <h5><br/></h5>
            <label for='demo-category'>Anzahl Gruppen</label>
            <input type='number' name='anzahl_gruppen' min='1' value='<?php echo (int)($altesTurnier['anzahl_gruppen'] ?? 1); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <label for='demo-category'>Start-Finalstufe (K.-o.-Phase)</label>
            <select name='start_ko_finallevel'>
                <?php
                $sqlKoLevelNeuesTurnier = 'SELECT * FROM `Turnier_KO_Finallevel` ORDER BY id DESC';
                $resultKoLevelNeuesTurnier = $conn->query($sqlKoLevelNeuesTurnier);
                while ($rowKoLevelNeuesTurnier = $resultKoLevelNeuesTurnier->fetch_assoc()) {
                    $koId = $rowKoLevelNeuesTurnier['id'];
                    $koName = $rowKoLevelNeuesTurnier['name'];
                    $sel = ($koId == ($altesTurnier['start_ko_finallevel'] ?? null)) ? "selected" : "";
                    echo "<option value='$koId' $sel>" . htmlspecialchars($koName) . "</option>";
                }
                ?>
            </select>
            <h5><br/></h5>
            <input type='checkbox' id='neu_einzug_ko_manuell_anlegen' name='einzug_ko_manuell_anlegen' value='1' <?php echo (($altesTurnier['einzug_ko_manuell_anlegen'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_einzug_ko_manuell_anlegen'>Einzug in die K.-o.-Phase manuell anlegen</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Wenn aktiviert, berechnet die Website die ersten K.-o.-Paarungen nicht automatisch, sondern erwartet, dass diese manuell (z.B. über "Begegnungen bearbeiten") angelegt werden. Wichtig: es gibt dann zusätzlich noch ein eigenes Häkchen direkt in der K.-o.-Phase ("Gruppenphase beendet / K.-o.-Einzug fertig angelegt"), das erst gesetzt werden muss, damit die Website die manuell angelegten Begegnungen als startklar erkennt.</p>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Hinweis: Der Schalter direkt darunter ("Gruppenphase beendet / K.-o.-Einzug fertig angelegt") wird beim Anlegen dieses neuen Turniers immer automatisch zurückgesetzt, egal was hier angehakt ist - ein neues Turnier hat schließlich noch keine abgeschlossene Gruppenphase.</p>
            <h5><br/></h5>
            <input type='checkbox' id='neu_einzug_ko_fertig' name='einzug_ko_fertig_manuell_angelegt_bzw_gruppenphase_vorbei' value='1'>
            <label for='neu_einzug_ko_fertig'>Gruppenphase beendet / K.-o.-Einzug fertig angelegt (für ein neues Turnier i.d.R. nicht ankreuzen)</label>
            <h5><br/></h5>
            <input type='checkbox' id='neu_nur_oberes_dreieck' name='nurOberesDreieckInGruppenphase' value='1' <?php echo (($altesTurnier['nurOberesDreieckInGruppenphase'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_nur_oberes_dreieck'>Nur oberes Dreieck in Gruppenphase</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Jede Begegnung einer Gruppe wird in der Tabelle normalerweise doppelt angezeigt (einmal oberhalb, einmal unterhalb der Diagonale) - aktiviert zeigt die Tabelle das Ergebnis nur einmal (oberes Dreieck). Kompakter, aber Übersichtlichkeit vs. Kompaktheit: siehe Hinweis beim nächsten Häkchen.</p>
            <h5><br/></h5>
            <input type='checkbox' id='neu_loesche_erste_zeile' name='loescheErsteZeileUndSpalte' value='1' <?php echo (($altesTurnier['loescheErsteZeileUndSpalte'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_loesche_erste_zeile'>Lösche erste Zeile und Spalte (Gruppentabelle)</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Blendet zusätzlich die erste Zeile/Spalte der Gruppentabelle aus (nur sinnvoll zusammen mit "Nur oberes Dreieck", da dort sonst leer). Macht die Tabelle noch kompakter, kann aber verwirren: z.B. sieht eine Gruppe mit 4 Teams dann so aus, als hätte sie nur 3, weil das erste Team nur noch in den Spaltenköpfen der anderen auftaucht, nicht mehr als eigene Zeile/Spalte.</p>
            <h5><br/></h5>
            <input type='checkbox' id='neu_losingbracket_open' name='losingbracket_open_for_ko_losers' value='1' <?php echo (($altesTurnier['losingbracket_open_for_ko_losers'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_losingbracket_open'>Losing Bracket offen für K.-o.-Verlierer</label>
            <h5><br/></h5>
            <input type='checkbox' id='neu_use_excel' name='use_excel' value='1' <?php echo (($altesTurnier['use_excel'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_use_excel'>Excel-Verknüpfung nutzen</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Ersetzt den normalen (automatisch berechneten) Spielplan komplett durch eine eingebettete Excel-Tabelle - der normale Spielplan wird dann gar nicht mehr angezeigt. Nur aktivieren, wenn unten auch wirklich ein gültiger Excel-Link eingetragen wird.</p>
            <h5><br/></h5>
            <label for='demo-category'>Excel-Link</label>
            <input type='text' name='excel_link' value='<?php echo htmlspecialchars($altesTurnier['excel_link'] ?? ''); ?>' class='Eingabe' style='color: white'>
            <h5><br/></h5>
            <input type='checkbox' id='neu_schnee' name='schnee' value='1' <?php echo (($altesTurnier['schnee'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_schnee'>Schnee-Effekt</label>
        </div>
        <script type='text/javascript'>
            function neuesTurnierIstRealesTurnier() {
                return document.getElementById('neuer_turnier_type_select').value === '1';
            }
            function neuesTurnierTypGeaendert() {
                var istReal = neuesTurnierIstRealesTurnier();
                document.getElementById('neues_turnier_history_warnung').style.display = istReal ? '' : 'none';
            }
            function checkAGBNeuesTurnier() {
                // Die History-Bestätigung ist nur nötig/sinnvoll, wenn wirklich ein reales Turnier
                // angelegt wird - bei einem Testturnier bleibt das aktuelle Turnier ja unangetastet.
                if (!neuesTurnierIstRealesTurnier()) {
                    return true;
                }
                if (document.getElementById('demo-human-neues-turnier').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen!');
                return false;
            }
        </script>
        <div id='neues_turnier_history_warnung'>
            <div class='field half'>
                <input type='checkbox' id='demo-human-neues-turnier' name='demo-human-neues-turnier' unchecked>
                <label for='demo-human-neues-turnier'>Mir ist bewusst, dass das aktuelle Turnier dadurch zu "History" wird und dieses hier zum neuen, aktuellen Turnier.</label>
            </div>
        </div>
        <div style='height:2rem;'></div>
        <ul class='actions'>
            <li><input type='submit' value='Kopie anlegen' class='primary' /></li>
            <li><input type='reset' value='Abbrechen' /></li>
        </ul>
    </form>
    <?php } ?>
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
    <?php if (!$rechteFlags['turnier_settings']) { ?>
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

    <style>
        /* Angeglichen an das flache Feld-Layout von "Neues Turnier anlegen" (Label über dem Feld,
           kein umrandeter Kasten mehr pro Einstellung) - der Unterschied ist nur, dass hier jedes
           Feld sein eigenes kleines "bestätigen"-Häkchen behält, weil jedes Feld einzeln absendet. */
        .ts-setting { margin-bottom: 1rem; text-align: left; }
        .ts-setting-label { display: block; margin-bottom: 0.1rem; }
        .ts-hint { display: block; font-size: 0.75rem; opacity: 0.7; margin-bottom: 0.4rem; }
        .ts-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin: 0; }
        .ts-input { min-width: 140px; }
    </style>

    <div class='ts-setting'>
        <span class='ts-setting-label'>Anzahl Gruppen</span>
        <span class='ts-hint'>Bestimmt, in wie viele Gruppen die Teams in der Gruppenphase aufgeteilt werden.</span>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_AnzahlGruppen_Aendern'/>
            <input type='number' name='anzahl_gruppen' min='1' value='<?php echo $curAnzahlGruppen; ?>' class='Eingabe ts-input'>
            <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> bestätigen</label>
        </form>
    </div>

    <div class='ts-setting'>
        <span class='ts-setting-label'>Start-Finalstufe (K.-o.-Phase)</span>
        <span class='ts-hint'>Legt fest, mit welcher Finalstufe die K.-o.-Phase beginnt (z.B. Achtelfinale, Viertelfinale, ...) - abhängig von der Teamanzahl.</span>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_StartKoFinallevel_Aendern'/>
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
    </div>

    <?php
    $curKoEinzugModus = (int)($rowTurnierSettings['fk_ko_einzug_modus'] ?? 1);
    if ($curKoEinzugModus <= 0) { $curKoEinzugModus = 1; }
    $koEinzugModiListe = [];
    $resKoEinzugModiTs = $conn->query('SELECT * FROM Turnier_KO_Einzug_Modus ORDER BY sortierung ASC, id ASC');
    while ($resKoEinzugModiTs && ($rowKoEinzugModiTs = $resKoEinzugModiTs->fetch_assoc())) { $koEinzugModiListe[] = $rowKoEinzugModiTs; }
    $curKoEinzugModusRow = null;
    foreach ($koEinzugModiListe as $r) { if ((int)$r['id'] === $curKoEinzugModus) { $curKoEinzugModusRow = $r; break; } }
    $curKoEinzugKompatibilitaet = $curKoEinzugModusRow ? koEinzugModusKompatibel($curKoEinzugModusRow, $curAnzahlGruppen, $curStartKoFinallevel) : ['ok' => true, 'grund' => ''];
    ?>
    <div class='ts-setting'>
        <span class='ts-setting-label'>Einzug ins KO-System (Paarungsmodus)</span>
        <span class='ts-hint'>Legt fest, nach welchem Schema die Gruppenplatzierungen auf die ersten K.-o.-Begegnungen verteilt werden. Ausführliche Erklärung mit Beispielen: <a href='#backstage_ko_einzug_modus'>eigener Menüpunkt "Einzug ins KO-System"</a> im Settings-Menü.</span>
        <?php if (!$curKoEinzugKompatibilitaet['ok']) { ?>
        <div style='background:rgba(231,76,60,0.15); border:1px solid #e74c3c; border-radius:6px; padding:0.5rem 0.8rem; font-size:0.8rem; margin-bottom:0.4rem;'>
            &#9888; Der aktuell gespeicherte Modus "<?php echo htmlspecialchars($curKoEinzugModusRow['name']); ?>" passt gerade NICHT zur aktuellen Konfiguration: <?php echo htmlspecialchars($curKoEinzugKompatibilitaet['grund']); ?> Solange das so bleibt, werden keine automatischen K.-o.-Begegnungen erzeugt.
        </div>
        <?php } ?>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_Feld_Aendern'/>
            <input type='hidden' name='feld' value='fk_ko_einzug_modus'/>
            <select name='wert' class='ts-input' id='ts_ko_einzug_modus_select' onchange='tsKoEinzugModusPreview()'>
                <?php foreach ($koEinzugModiListe as $r) {
                    $sel = ((int)$r['id'] === $curKoEinzugModus) ? 'selected' : '';
                    echo "<option value='" . (int)$r['id'] . "' $sel>" . htmlspecialchars($r['name']) . "</option>";
                } ?>
            </select>
            <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> bestätigen</label>
        </form>
        <div id='ts_ko_einzug_modus_warnung' style='background:rgba(231,76,60,0.15); border:1px solid #e74c3c; border-radius:6px; padding:0.5rem 0.8rem; font-size:0.8rem; margin-top:0.4rem; display:none;'></div>
        <script>
            var tsKoEinzugModiDaten = <?php echo json_encode(array_map(function($r) {
                return [
                    'name' => $r['name'],
                    'min' => (int)$r['min_anzahl_gruppen'],
                    'max' => $r['max_anzahl_gruppen'] !== null ? (int)$r['max_anzahl_gruppen'] : null,
                    'gerade' => (int)$r['gruppenanzahl_muss_gerade_sein'] === 1,
                    'platzierungen' => $r['platzierungen_pro_gruppe'] !== null ? (int)$r['platzierungen_pro_gruppe'] : null,
                ];
            }, array_combine(array_map(function($r){ return (int)$r['id']; }, $koEinzugModiListe), $koEinzugModiListe))); ?>;
            var tsKoEinzugAnzahlGruppen = <?php echo (int)$curAnzahlGruppen; ?>;
            var tsKoEinzugStartFinallevel = <?php echo (int)$curStartKoFinallevel; ?>;
            function tsKoEinzugModusPreview() {
                var select = document.getElementById('ts_ko_einzug_modus_select');
                var warnung = document.getElementById('ts_ko_einzug_modus_warnung');
                var modus = tsKoEinzugModiDaten[select.value];
                if (!modus) { warnung.style.display = 'none'; return; }
                var anzahlGruppen = tsKoEinzugAnzahlGruppen;
                var totalStartTeams = Math.pow(2, Math.max(1, tsKoEinzugStartFinallevel - 1));
                var grund = null;
                if (anzahlGruppen < modus.min) {
                    grund = 'Braucht mindestens ' + modus.min + ' Gruppen (aktuell: ' + anzahlGruppen + ').';
                } else if (modus.max !== null && anzahlGruppen > modus.max) {
                    grund = 'Erlaubt höchstens ' + modus.max + ' Gruppen (aktuell: ' + anzahlGruppen + ').';
                } else if (modus.gerade && anzahlGruppen % 2 !== 0) {
                    grund = 'Braucht eine gerade Anzahl Gruppen (aktuell: ' + anzahlGruppen + ').';
                } else if (anzahlGruppen <= 0 || totalStartTeams % anzahlGruppen !== 0) {
                    grund = 'Die ' + totalStartTeams + ' Startplätze der gewählten K.-o.-Startstufe lassen sich nicht gleichmäßig auf ' + anzahlGruppen + ' Gruppen aufteilen.';
                } else {
                    var platzierungenProGruppe = totalStartTeams / anzahlGruppen;
                    if (modus.platzierungen !== null && modus.platzierungen !== platzierungenProGruppe) {
                        grund = 'Braucht genau ' + modus.platzierungen + ' Qualifikanten pro Gruppe, aktuell qualifizieren aber ' + platzierungenProGruppe + ' pro Gruppe.';
                    }
                }
                if (grund) {
                    warnung.textContent = '⚠ "' + modus.name + '" ist mit der aktuellen Konfiguration nicht wählbar: ' + grund;
                    warnung.style.display = 'block';
                } else {
                    warnung.style.display = 'none';
                }
            }
        </script>
    </div>

    <div class='ts-setting'>
        <span class='ts-setting-label'>Einzug K.-o.-Phase manuell anlegen</span>
        <span class='ts-hint'>Wenn aktiviert, berechnet die Website die ersten K.-o.-Paarungen nicht automatisch aus den Gruppenplatzierungen, sondern erwartet, dass diese manuell (z.B. über "Begegnungen bearbeiten") angelegt werden. Wichtig: bei aktiviertem Schalter gibt es zusätzlich noch ein eigenes Häkchen direkt in der K.-o.-Phase ("Gruppenphase beendet / K.-o.-Einzug fertig angelegt"), das erst gesetzt werden muss, damit die Website die manuell angelegten Begegnungen als startklar erkennt.</span>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_EinzugKoManuell_Aendern'/>
            <input type='checkbox' id='ts_einzug_ko_manuell_anlegen' name='einzug_ko_manuell_anlegen' value='1' <?php echo ($curEinzugKoManuellAnlegen == 1) ? "checked" : ""; ?>>
            <label for='ts_einzug_ko_manuell_anlegen'>aktiviert</label>
            <label class='admin-toggle'>
                <input type='checkbox' onchange='this.form.submit()'>
                <span>bestätigen</span>
            </label>
        </form>
    </div>

    <?php
    // ============================================================================================
    // TURNIER SETTINGS ERWEITERUNG: ALLE RESTLICHEN FELDER AUS "Neues Turnier anlegen"
    // ============================================================================================
    // Nutzt die generische Backend-Aktion "Turnier_Settings_Feld_Aendern" (edit_variables.php),
    // damit hier nicht für jedes Feld eine eigene Aktion/Funktion nötig ist. Reihenfolge bewusst
    // identisch zu "Neues Turnier anlegen", damit man sich als Nutzer nicht neu orientieren muss.
    function tsTextFeld($label, $hint, $feld, $curValue, $inputType, $TurnierID, $bnAttr, $pwAttr) {
        $valueAttr = htmlspecialchars((string)$curValue, ENT_QUOTES);
        echo "
        <div class='ts-setting'>
            <span class='ts-setting-label'>" . htmlspecialchars($label) . "</span>
            <span class='ts-hint'>" . htmlspecialchars($hint) . "</span>
            <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='bn' value='$bnAttr'/>
                <input type='hidden' name='pw' value='$pwAttr'/>
                <input type='hidden' name='action' value='Turnier_Settings_Feld_Aendern'/>
                <input type='hidden' name='feld' value='$feld'/>
                <input type='$inputType' name='wert' value='$valueAttr' class='Eingabe ts-input'>
                <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
            </form>
        </div>";
    }
    function tsCheckboxFeld($label, $hint, $feld, $curValue, $TurnierID, $bnAttr, $pwAttr) {
        $checkedAttr = ((int)$curValue === 1) ? "checked" : "";
        $idAttr = "ts_feld_" . $feld;
        echo "
        <div class='ts-setting'>
            <span class='ts-setting-label'>" . htmlspecialchars($label) . "</span>
            <span class='ts-hint'>" . htmlspecialchars($hint) . "</span>
            <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='bn' value='$bnAttr'/>
                <input type='hidden' name='pw' value='$pwAttr'/>
                <input type='hidden' name='action' value='Turnier_Settings_Feld_Aendern'/>
                <input type='hidden' name='feld' value='$feld'/>
                <input type='checkbox' id='$idAttr' name='wert' value='1' $checkedAttr>
                <label for='$idAttr'>aktiviert</label>
                <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
            </form>
        </div>";
    }

    tsTextFeld('Name (intern)', 'Interner Name des Turniers.', 'name', $rowTurnierSettings['name'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Anzeige-Titel', 'Titel, wie er auf der Website angezeigt wird.', 'anzeige_titel', $rowTurnierSettings['anzeige_titel'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Anzeige-Untertitel', 'Untertitel auf der Website.', 'anzeige_subtitel', $rowTurnierSettings['anzeige_subtitel'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Anzeige-Datum', 'Freier Text, z.B. "26.-28. September". Wird aktuell bewusst NICHT ausgefüllt, damit nicht jede/r auf der Website sieht, wann genau das Turnier stattfindet.', 'anzeige_datum', $rowTurnierSettings['anzeige_datum'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Jahr', 'Turnier-Jahr.', 'jahr', $rowTurnierSettings['jahr'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Startdatum', 'Erster Turniertag. Wird u.a. genutzt, um vergangene Turniere ("History") in der richtigen Reihenfolge zu sortieren - ansonsten aktuell rein informativ.', 'startdatum', $rowTurnierSettings['startdatum'], 'date', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Startzeit', 'Uhrzeit des Turnierstarts. Aktuell rein informativ, wird sonst an keiner Stelle automatisch ausgewertet.', 'startzeit', $rowTurnierSettings['startzeit'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Countdown-Start', 'Bestimmt, worauf der Countdown auf der Startseite herunterzählt. Braucht genau dieses Format: "Sep 06, 2025 14:00:00".', 'countdown_start', $rowTurnierSettings['countdown_start'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Enddatum', 'Letzter Turniertag. Aktuell rein informativ, wird sonst an keiner Stelle automatisch ausgewertet.', 'enddatum', $rowTurnierSettings['enddatum'], 'date', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Maximale Teamanzahl', 'Ab dieser Teamanzahl werden keine weiteren Anmeldungen mehr angenommen (Warteliste greift).', 'max_anzahl_teams', (int)$rowTurnierSettings['max_anzahl_teams'], 'number', $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Teilnahmebeitrag', 'Beitrag pro Team, in Euro.', 'teilnahmebeitrag', $rowTurnierSettings['teilnahmebeitrag'], 'text', $TurnierID, $bnAttr, $pwAttr);
    // Anzeige-Reihenfolge (order_on_website) hier bewusst nicht mehr bearbeitbar - irrelevant für den
    // laufenden Betrieb. Die Spalte/der Wert bleibt in der Datenbank unangetastet, nur die
    // Bearbeitungsmöglichkeit an dieser Stelle wurde entfernt.
    ?>
    <div class='ts-setting'>
        <span class='ts-setting-label'>Turnierphase</span>
        <span class='ts-hint'>Alternativer Ort, um die Turnierphase zu setzen (siehe auch der eigene "Turnierphase"-Punkt im Settings-Menü mit ausführlichen Erklärungen). Gruppenanzahl/-erstellung und Gruppeneinteilung stehen hier bewusst nicht zur Auswahl - dafür gibt es die eigenen Buttons "Gruppen für Gruppenphase generieren" und "Gruppeneinteilung losen" im Settings-Menü.</span>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $bnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $pwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_Feld_Aendern'/>
            <input type='hidden' name='feld' value='fk_turnier_phase'/>
            <select name='wert' class='ts-input'>
                <?php
                // Phasen 4 (Gruppenanzahl/-erstellung) und 5 (Gruppeneinteilung) bewusst ausgeblendet -
                // dieselbe Einschränkung wie beim eigenen "Turnierphase"-Menüpunkt, dafür gibt es die
                // dedizierten Buttons "Gruppen für Gruppenphase generieren"/"Gruppeneinteilung losen".
                $sqlTsPhase = 'SELECT * FROM `Turnier_Setting_Phasen` WHERE id NOT IN (4, 5) ORDER BY logical_order';
                $resultTsPhase = $conn->query($sqlTsPhase);
                while ($rowTsPhase = $resultTsPhase->fetch_assoc()) {
                    $selTsPhase = ($rowTsPhase['id'] == $rowTurnierSettings['fk_turnier_phase']) ? "selected" : "";
                    echo "<option value='" . $rowTsPhase['id'] . "' $selTsPhase>" . htmlspecialchars($rowTsPhase['name']) . "</option>";
                }
                ?>
            </select>
            <label class='admin-toggle'><input type='checkbox' onchange='this.form.submit()'> <span>bestätigen</span></label>
        </form>
    </div>
    <?php
    tsCheckboxFeld('Nur oberes Dreieck in Gruppenphase', 'Jede Begegnung einer Gruppe wird in der Tabelle normalerweise doppelt angezeigt (einmal oberhalb, einmal unterhalb der Diagonale) - aktiviert zeigt die Tabelle das Ergebnis nur einmal (oberes Dreieck). Kompakter, aber Übersichtlichkeit vs. Kompaktheit: siehe Hinweis bei "Lösche erste Zeile und Spalte".', 'nurOberesDreieckInGruppenphase', $rowTurnierSettings['nurOberesDreieckInGruppenphase'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Lösche erste Zeile und Spalte', 'Blendet zusätzlich die erste Zeile/Spalte der Gruppentabelle aus (nur sinnvoll zusammen mit "Nur oberes Dreieck", da dort sonst leer). Macht die Tabelle noch kompakter, kann aber verwirren: z.B. sieht eine Gruppe mit 4 Teams dann so aus, als hätte sie nur 3, weil das erste Team nur noch in den Spaltenköpfen der anderen auftaucht, nicht mehr als eigene Zeile/Spalte.', 'loescheErsteZeileUndSpalte', $rowTurnierSettings['loescheErsteZeileUndSpalte'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Losing Bracket offen für K.-o.-Verlierer', 'Verlierer der K.-o.-Phase spielen im Losing Bracket weiter.', 'losingbracket_open_for_ko_losers', $rowTurnierSettings['losingbracket_open_for_ko_losers'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Excel-Verknüpfung nutzen', 'Ersetzt den normalen (automatisch berechneten) Spielplan komplett durch eine eingebettete Excel-Tabelle - der normale Spielplan wird dann gar nicht mehr angezeigt. Nur aktivieren, wenn unten auch wirklich ein gültiger Excel-Link eingetragen ist.', 'use_excel', $rowTurnierSettings['use_excel'], $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Excel-Link', 'Nur relevant, wenn "Excel-Verknüpfung nutzen" aktiviert ist.', 'excel_link', $rowTurnierSettings['excel_link'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Schnee-Effekt', 'Aktiviert den winterlichen Schnee-Effekt auf der Website.', 'schnee', $rowTurnierSettings['schnee'], $TurnierID, $bnAttr, $pwAttr);
    ?>

    <h5><br /></h5>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  NUTZERMANAGEMENT (komplette UI für das neue Mehrfach-Rollen-System, ersetzt fk_rechte)  ### -->
<!-- ################################################################################################ -->
<!-- Kurzüberblick der Rollen + kompakte Liste aller Nutzer (sortiert nach Berechtigungsstärke) mit
     ihren aktuell zugewiesenen Rollen als Badges, "Rolle hinzufügen"-Dropdown (nur mit erlaubten
     Zielrollen) und "Rolle entfernen" pro Badge. Ganz unten ein kompaktes "Neuen Nutzer anlegen".
     Alles ausschließlich über System_Benutzer_in_Relation_Rolle, fk_rechte wird nirgends mehr gelesen. -->
<article id="backstage_nutzermanagement">
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <h1>Nutzermanagement</h1>
    <?php
    // RECHTE-AUDIT: Nutzermanagement (die ganze Seite, inkl. "Login als User") ist laut expliziter
    // Vorgabe strikt Co-Admin/Admin vorbehalten - bewusst NICHT mehr nur über das
    // restliche_rollen_vergeben-Flag geprüft (das heute zwar nur Admin/Co-Admin haben, aber falls das
    // Flag später mal einer anderen Rolle für andere Zwecke gegeben würde, dürfte das NICHT
    // automatisch auch Zugriff auf diese ganze Seite freischalten).
    $hatIrgendeinRollenVergabeRecht = $istAdminOderCoAdmin;
    if (!$hatIrgendeinRollenVergabeRecht) { ?>
        <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        $darfNeueAdmins = $rechteFlags['neue_admins'];
        $darfNeueCoAdmins = $rechteFlags['neue_co_admins'];
        $darfRestlicheRollenVergeben = $rechteFlags['restliche_rollen_vergeben'];
        // ====================================================================================
        // RECHTE-AUDIT: OB EINE ROLLE VERGEBEN WERDEN DARF, HÄNGT AN DEN FLAGS DER ZIEL-ROLLE
        // SELBST (rechte_neue_admins/rechte_neue_co_admins), NICHT AN IHRER ID ODER IHREM NAMEN.
        // ====================================================================================
        // Vorher wurde hart nach Rollen-ID geprüft (id==1 -> Admin, id==2 -> Co-Admin). Jetzt wird
        // stattdessen die Zielrolle selbst nachgeschlagen: hat SIE das Flag rechte_neue_admins,
        // braucht der Vergebende ebenfalls rechte_neue_admins usw. Das ist unabhängig von IDs/Namen
        // und funktioniert auch, falls später weitere admin-artige Rollen hinzukommen.
        function nmDarfRolleVergeben($zielRolleFlags, $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben) {
            if (($zielRolleFlags['rechte_neue_admins'] ?? false)) { return $darfNeueAdmins; }
            if (($zielRolleFlags['rechte_neue_co_admins'] ?? false)) { return $darfNeueCoAdmins; }
            return $darfRestlicheRollenVergeben;
        }

        // Alle Rollen (für Übersicht + Badge-Namen + eigene Flags je Rolle für nmDarfRolleVergeben)
        // Name/Reihenfolge/Beschreibung kommen weiterhin aus der DB-Tabelle (reine Anzeige-Metadaten),
        // die Rechte-Flags selbst aber aus getRollenFlags() (rollen_definitionen.php).
        $rollenNamenById = [];
        $rollenFlagsById = [];
        $rollenListeFuerUebersicht = [];
        $sqlRollen = 'SELECT * FROM System_Benutzer_in_Rolle ORDER BY hierarchie_ebene';
        $resultRollen = $conn->query($sqlRollen);
        while ($rowRolle = $resultRollen->fetch_assoc()) {
            $rollenNamenById[(int)$rowRolle['id']] = $rowRolle['name'];
            $rollenFlagsById[(int)$rowRolle['id']] = getRollenFlags((int)$rowRolle['id']);
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
    ?>
    <style>
        .nm-rollen-tabelle { width: 100%; margin-bottom: 1.2rem; font-size: 0.82rem; }
        .nm-userlist { margin-bottom: 1rem; }
        /* ============================================================================================
           NUTZER-KARTE - DREI KLAR GETRENNTE ZEILEN NACH FUNKTION STATT ALLES IN EINER WRAPPENDEN ZEILE
           ============================================================================================
           1) Identität/Login: Name + "Login als User". 2) Rollen: Badges + "Rolle hinzufügen" - alles,
           was mit Rollen zu tun hat, gehört visuell zusammen. 3) Nur für "echte" Admins, per gestrichelter
           Linie abgesetzt: Passwort anzeigen/ändern - bewusst als eigener, sensiblerer Bereich erkennbar. */
        .nm-user-card { border: 1px solid rgba(139, 92, 246, 0.22); border-radius: 8px; padding: 0.6rem 0.8rem; margin-bottom: 0.7rem; text-align: left; font-size: 0.82rem; }
        .nm-user-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
        .nm-user-row:last-child { margin-bottom: 0; }
        .nm-user-name { font-size: 0.95rem; font-weight: 700; flex: 1 1 auto; }
        .nm-user-admin-row { border-top: 1px dashed rgba(139, 92, 246, 0.3); padding-top: 0.5rem; }
        .nm-user-roles { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        /* Badge bleibt immer im kompakten Stil (auch wenn eine Entfernen-Möglichkeit existiert) - das
           "×" liegt als kleiner Kreis oben rechts AUSSERHALB der Badge (position:absolute), nimmt also
           keinen Platz im Badge-Inneren weg und macht die Badge dadurch nicht größer/breiter. */
        .nm-badge { position: relative; display: inline-flex; align-items: center; background: rgba(139, 92, 246, 0.18); border: 1px solid var(--admin-accent); border-radius: 10px; padding: 0.15rem 0.55rem; font-size: 0.72rem; white-space: nowrap; }
        .nm-badge-remove { position: absolute; top: -0.45rem; right: -0.45rem; width: 1.05rem; height: 1.05rem; border-radius: 50%; background: #7a2020; border: 1px solid #c0392b; color: #fff; font-size: 0.62rem; line-height: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; box-shadow: none; }
        .nm-login-als, .nm-addrole-form, .nm-pwchange-form { display: inline-flex; gap: 0.3rem; align-items: center; margin: 0; }
        .nm-login-als button { padding: 0.15rem 0.5rem; font-size: 0.7rem; }
        .nm-addrole-form select, .nm-pwchange-form input[type='text'] {
            padding: 0.15rem 0.35rem; font-size: 0.72rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.06); color: #fff;
        }
        .nm-pwchange-form input[type='text'] { width: 8rem; }
        .nm-addrole-form button { background: var(--admin-accent-deep); border-color: var(--admin-accent); border-radius: 4px; border-width: 1px; border-style: solid; color: #fff; cursor: pointer; padding: 0.15rem 0.5rem; font-size: 0.72rem; }
        /* Passwort anzeigen/ändern ist strikt "echten" Admins vorbehalten (siehe $binIchEchterAdmin
           weiter unten) - bekommt deshalb denselben roten Rahmen wie die "adminonly"-Stufe im
           Settings/Infos-Farbsystem, statt eines eigenen abweichenden Stils. */
        .nm-pwchange-form button { background: linear-gradient(135deg, var(--admin-accent-deep), var(--admin-accent)); border: 2px solid var(--admin-border-adminonly); border-radius: 4px; color: #fff; cursor: pointer; padding: 0.15rem 0.5rem; font-size: 0.72rem; }
        /* Passwort anzeigen + ändern optisch als EIN zusammengehöriger Block statt zwei loser Elemente */
        .nm-pw-group { display: inline-flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; background: rgba(139, 92, 246, 0.08); border: 2px solid var(--admin-border-adminonly); border-radius: 6px; padding: 0.3rem 0.6rem; }
        .nm-pw-label { font-size: 0.72rem; font-weight: 700; opacity: 0.85; }
        .nm-pw { opacity: 0.9; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .nm-pw-toggle { border: none; background: none; color: var(--admin-border-adminonly); cursor: pointer; font-size: 0.72rem; padding: 0; text-decoration: underline; }
        /* WICHTIG: bloße <button>-Elemente erben sonst die große Standard-Button-Optik der Website
           (2.75rem hoch, GROSSBUCHSTABEN, Letter-Spacing, weißer Schatten-Rahmen) - dadurch sah die
           Schrift größer/unpassender aus als die kleinen Buttons selbst. Hier gezielt NUR für die
           kompakten Nutzermanagement-Buttons zurückgesetzt (Selektoren sind alle nm-*-spezifisch,
           betrifft also keine anderen Buttons auf der Website). */
        .nm-login-als button, .nm-addrole-form button, .nm-pwchange-form button, .nm-pw-toggle {
            height: auto; line-height: 1.2; letter-spacing: normal; text-transform: none; box-shadow: none;
        }
    </style>

    <h2>Nutzer</h2>
    <p><i>Sortiert nach Berechtigungsstärke (Admin zuerst). Jeder Nutzer kann mehrere Rollen gleichzeitig haben.</i></p>
    <div class='nm-userlist'>
    <?php foreach ($alleNutzerMitRollen as $nutzer) {
        $nmPwId = 'nm_pw_' . $nutzer['id'];
    ?>
        <div class='nm-user-card'>
            <?php
            // Passwörter anzeigen/ändern: bewusst nur für "echte" Admins (rollenInfo['ist_admin']),
            // nicht für Co-Admins - auch wenn Co-Admins sonst Zugriff auf Nutzermanagement haben.
            $binIchEchterAdmin = ($rollenInfo !== null && $rollenInfo['ist_admin']);
            // "Login als User" bei einer Ziel-Person, die selbst Admin ist, nur für echte Admins:
            // ein Co-Admin könnte sich sonst als Admin einloggen und darüber z.B. Passwörter anderer
            // Nutzer einsehen/ändern - Rechte, die Co-Admin sonst gezielt NICHT hat. Flag-basiert
            // geprüft (rechte_neue_admins der Ziel-Rolle), nicht über eine hart codierte Rollen-ID.
            $zielIstAdmin = false;
            foreach ($nutzer['rolle_ids'] as $ridCheck) {
                if (!empty($rollenFlagsById[$ridCheck]['rechte_neue_admins'])) { $zielIstAdmin = true; break; }
            }
            $loginAlsErlaubt = $binIchEchterAdmin || !$zielIstAdmin;
            // NUTZER LÖSCHEN: identitätsbasiert über Rollen-ID 2 geprüft (nicht über ein Flag - das
            // Flag rechte_neue_co_admins ist bei Admin UND Co-Admin gleichzeitig gesetzt und würde
            // hier nicht zwischen beiden unterscheiden). Admins dürfen Admins/Co-Admins löschen,
            // Co-Admins weder Admins noch andere Co-Admins - siehe gleiche Prüfung serverseitig in
            // edit_account.php (Benutzer_Loeschen). Sich selbst kann niemand löschen.
            $zielIstCoAdmin = in_array(2, $nutzer['rolle_ids'], true);
            $binIchIch = ($rollenInfo !== null && $rollenInfo['benutzer_id'] === $nutzer['id']);
            $loeschenErlaubt = !$binIchIch && ($binIchEchterAdmin || (!$zielIstAdmin && !$zielIstCoAdmin));
            ?>
            <!-- Zeile 1: Identität/Login -->
            <div class='nm-user-row'>
                <span class='nm-user-name' id='nm_bn_display_<?php echo $nutzer['id']; ?>'><?php echo htmlspecialchars($nutzer['bn']); ?></span>
                <?php if ($binIchEchterAdmin) { ?>
                <button type='button' class='nm-pw-toggle' title='Benutzernamen ändern' onclick="var f=document.getElementById('nm_bn_form_<?php echo $nutzer['id']; ?>'); f.style.display = (f.style.display==='inline-flex') ? 'none' : 'inline-flex';">&#9998;</button>
                <form action='website_datachange/edit_account.php' method='POST' class='nm-pwchange-form' id='nm_bn_form_<?php echo $nutzer['id']; ?>' style='display:none;' onsubmit="return confirm('Benutzernamen von <?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?> wirklich ändern?');">
                    <input type='hidden' name='action' value='Benutzername_Aendern'>
                    <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                    <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                    <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                    <input type='text' name='neuer_benutzername' value='<?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?>' required>
                    <button type='submit'>ändern</button>
                </form>
                <?php } ?>
                <?php if ($loginAlsErlaubt) { ?>
                <!-- "Login als User" läuft jetzt komplett serverseitig über edit_account.php
                     (Login_Als_User) - hier stehen nur noch die EIGENEN Zugangsdaten der
                     anfragenden Person (die kennt sie ja schon), nie mehr das Ziel-Passwort im
                     HTML-Quelltext. -->
                <form action='website_datachange/edit_account.php<?php echo $test_turnier_id!=0 ? "?test_turnier_id=$test_turnier_id" : ""; ?>' method='POST' class='nm-login-als'>
                    <input type='hidden' name='action' value='Login_Als_User'>
                    <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                    <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                    <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                    <button type='submit' class='admin-menu-button admin-menu-button--coadmin' style='min-width:auto;padding:0.15rem 0.5rem;font-size:0.7rem;'>Login als User</button>
                </form>
                <?php } ?>
                <?php if ($loeschenErlaubt) { ?>
                <form action='website_datachange/edit_account.php<?php echo $test_turnier_id!=0 ? "?test_turnier_id=$test_turnier_id" : ""; ?>' method='POST' class='nm-login-als' onsubmit="return confirm('Nutzer <?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?> wirklich unwiderruflich löschen?');">
                    <input type='hidden' name='action' value='Benutzer_Loeschen'>
                    <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                    <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                    <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                    <button type='submit' class='admin-menu-button admin-menu-button--adminonly' style='min-width:auto;padding:0.15rem 0.5rem;font-size:0.7rem;'>Löschen</button>
                </form>
                <?php } ?>
            </div>
            <!-- Zeile 2: Rollen -->
            <div class='nm-user-row nm-user-roles'>
            <?php foreach ($nutzer['rolle_ids'] as $rid) {
                $rname = $rollenNamenById[$rid] ?? ('Rolle ' . $rid);
                echo "<span class='nm-badge'>" . htmlspecialchars($rname);
                // Kein count() > 1-Schutz mehr: ein Nutzer darf auch komplett rollenlos sein, die
                // letzte Rolle muss also genauso entfernbar sein wie jede andere.
                if (nmDarfRolleVergeben($rollenFlagsById[$rid] ?? [], $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) {
                    echo "<form action='website_datachange/edit_account.php' method='POST' style='display:inline;margin:0;' onsubmit=\"return confirm('Rolle wirklich entfernen?');\">
                        <input type='hidden' name='action' value='Rolle_Entfernen'>
                        <input type='hidden' name='admin_bn' value='$bnAttrNm'>
                        <input type='hidden' name='admin_pw' value='$pwAttrNm'>
                        <input type='hidden' name='ziel_benutzer_id' value='{$nutzer['id']}'>
                        <input type='hidden' name='entferne_rolle' value='$rid'>
                        <button type='submit' class='nm-badge-remove' title='Rolle entfernen'>&times;</button>
                    </form>";
                }
                echo "</span>";
            }
            $verfuegbareRollen = [];
            foreach ($rollenListeFuerUebersicht as $r) {
                $rid = (int)$r['id'];
                if (in_array($rid, $nutzer['rolle_ids'], true)) { continue; }
                if (!nmDarfRolleVergeben($rollenFlagsById[$rid] ?? [], $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) { continue; }
                $verfuegbareRollen[] = $r;
            }
            if (count($verfuegbareRollen) > 0) {
            ?>
            <form action='website_datachange/edit_account.php' method='POST' class='nm-addrole-form'>
                <input type='hidden' name='action' value='Rolle_Hinzufuegen'>
                <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                <select name='neue_rolle' required>
                    <option value='' disabled selected>Rolle hinzufügen ...</option>
                    <?php foreach ($verfuegbareRollen as $r) {
                        echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['name']) . "</option>";
                    } ?>
                </select>
                <button type='submit'>+</button>
            </form>
            <?php } ?>
            </div>
            <?php if ($binIchEchterAdmin) { ?>
            <!-- Zeile 3: Passwort - nur für "echte" Admins, per gestrichelter Linie abgesetzt.
                 Anzeigen + Ändern stecken bewusst in EINEM optischen Block (nm-pw-group), damit klar
                 wird, dass beides zusammengehört. -->
            <div class='nm-user-row nm-user-admin-row'>
                <div class='nm-pw-group'>
                    <span class='nm-pw-label'>Passwort:</span>
                    <span class='nm-pw'>
                        <span id='<?php echo $nmPwId; ?>' style='display:none;'><?php echo htmlspecialchars($nutzer['pw']); ?></span>
                        <button type='button' class='nm-pw-toggle' onclick="var s=document.getElementById('<?php echo $nmPwId; ?>'); var sichtbar = s.style.display !== 'none'; s.style.display = sichtbar ? 'none' : 'inline'; this.textContent = sichtbar ? 'anzeigen' : 'verbergen';">anzeigen</button>
                    </span>
                    <form action='website_datachange/edit_account.php' method='POST' class='nm-pwchange-form' onsubmit="return confirm('Passwort von <?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?> wirklich ändern?');">
                        <input type='hidden' name='action' value='Passwort_Aendern'>
                        <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                        <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                        <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                        <input type='text' name='neues_passwort' placeholder='Neues Passwort' required>
                        <button type='submit'>ändern</button>
                    </form>
                </div>
            </div>
            <?php } ?>
        </div>
    <?php } ?>
    </div>

    <h5><br/></h5>
    <a href='#backstage_neuen_nutzer_anlegen' class='admin-menu-button admin-menu-button--coadmin'>Neuen Nutzer anlegen</a>

    <h5><br/></h5>
    <h2>Rollen</h2>
    <p><i>Admin und Co-Admin sind <b>Sammel-Rollen</b>: wer eine davon hat, braucht keine weitere Rolle zusätzlich - sie umfassen automatisch alle Rechte der übrigen Rollen. Die restlichen Rollen (Autor*in, Moderator*in, Backstage-Zugang, Schiedsrichter*in) sind dagegen einzelne, unabhängige Rechte-Bausteine, die man je nach Bedarf miteinander kombiniert (z.B. braucht jemand, der Teams UND Spielergebnisse bearbeiten soll, sowohl Moderator*in als auch Schiedsrichter*in).</i></p>
    <?php
    // ================================================================================================
    // ROLLEN-ÜBERSICHT: BEWUSST SELBST FORMULIERT STATT 1:1 AUS DER DATENBANK ÜBERNOMMEN
    // ================================================================================================
    // Die "beschreibung"-Spalte in System_Benutzer_in_Rolle ist knapp/technisch gehalten. Hier steht
    // stattdessen eine ausführliche, an den tatsächlichen Rechte-Flags orientierte Erklärung, was man
    // mit der jeweiligen Rolle auf der Website konkret tun darf. Fällt eine Rollen-ID hier nicht in die
    // Liste (z.B. eine später neu angelegte Rolle), wird als Rückfallebene die DB-Beschreibung genutzt.
    $rollenErklaerung = [
        1  => 'Hat wirklich <b>alle</b> Rechte der Website: kann neue Admins und Co-Admins anlegen, alle restlichen Rollen vergeben, Turnier Settings/Turnierphase ändern, Website-Inhalte im CMS bearbeiten, Teams bearbeiten, den Backstage-Bereich sehen und beliebige Spielergebnisse eintragen. Wer Admin ist, braucht keine weitere Rolle zusätzlich. <b>Nur Admin</b> (nicht Co-Admin) kann außerdem hier im Nutzermanagement die Passwörter anderer Nutzer einsehen und ändern.',
        2  => 'Hat alles, was Admin auch hat - mit zwei Ausnahmen: kann selbst keine neuen Admins anlegen (Co-Admins und alle anderen Rollen aber schon), und kann <b>nicht</b> die Passwörter anderer Nutzer einsehen oder ändern - das bleibt ausschließlich Admin vorbehalten. Ansonsten reicht diese eine Rolle allein völlig aus.',
        5  => 'Darf ausschließlich die Website-Inhalte im CMS bearbeiten ("Website Inhalte bearbeiten"-Button). Sonst nichts - wer zusätzlich Teams bearbeiten oder Spielergebnisse eintragen soll, braucht dafür eine weitere Rolle dazu.',
        10 => 'Darf Teams bearbeiten (Teamname/Spielernamen ändern, Gruppe zuordnen, Bearbeitungsrechte vergeben/entziehen, Team abmelden) und hat dafür automatisch auch Zugang zum Backstage-Bereich (violetter Balken, um überhaupt zu den Teams-Funktionen zu gelangen). Für CMS-Inhalte oder Spielergebnisse braucht es zusätzliche Rollen.',
        15 => 'Darf sich in den Backstage-Bereich einloggen und dort die Infos/den Verlauf einsehen (violetter Balken), kann darüber hinaus aber nichts aktiv verändern. Gedacht als reine "Sichtbarkeits"-Rolle, z.B. für Helfer*innen, die Telefonnummern oder den DB-Verlauf einsehen sollen dürfen.',
        20 => 'Darf beliebige Spielergebnisse eintragen, ändern sowie Begegnungen finalisieren/unfinalisieren - auch bei Begegnungen, die nicht zum eigenen Team gehören. Hat aber <b>keinen</b> Zugang zum Backstage-Bereich (keinen violetten Balken); wer zusätzlich Backstage sehen soll, braucht die Rolle "Backstage-Zugang" separat dazu.',
        30 => 'Standardrolle für selbst registrierte Accounts - hat noch überhaupt keine Rechte. Muss von einem Admin/Co-Admin erst eine der obigen Rollen bekommen.',
    ];
    ?>
    <table class='withBorderCollapse nm-rollen-tabelle'>
        <thead><tr><th>Rolle</th><th>Was darf man damit tun?</th></tr></thead>
        <tbody>
        <?php foreach ($rollenListeFuerUebersicht as $r) {
            $rBeschreibung = $rollenErklaerung[(int)$r['id']] ?? htmlspecialchars($r['beschreibung']);
            echo "<tr><td>" . htmlspecialchars($r['name']) . "</td><td>" . $rBeschreibung . "</td></tr>";
        } ?>
        </tbody>
    </table>
    <?php } ?>
    <h5><br /></h5>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  NEUEN NUTZER ANLEGEN (eigene Seite statt Inline-Formular am Ende von Nutzermanagement)   ### -->
<!-- ################################################################################################ -->
<article id="backstage_neuen_nutzer_anlegen">
    <a href='#backstage_nutzermanagement' class='button'>Zurück</a>
    <h5><br /></h5>
    <?php
    // Gleiche Einschränkung wie bei backstage_nutzermanagement: strikt Co-Admin/Admin, nicht nur
    // flag-basiert (siehe Kommentar dort).
    $hatIrgendeinRollenVergabeRechtNeu = $istAdminOderCoAdmin;
    if (!$hatIrgendeinRollenVergabeRechtNeu) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else {
        $nnRollen = [];
        $resultNnRollen = $conn->query('SELECT * FROM System_Benutzer_in_Rolle ORDER BY hierarchie_ebene');
        while ($rowNnRolle = $resultNnRollen->fetch_assoc()) { $nnRollen[] = $rowNnRolle; }
        $nnBnAttr = htmlspecialchars($bn, ENT_QUOTES);
        $nnPwAttr = htmlspecialchars($pw, ENT_QUOTES);
    ?>
    <h1>Neuen Nutzer anlegen</h1>
    <?php // Rollen-Badges nutzen bewusst dasselbe .nm-badge/.nm-badge-remove-Design wie die
    // Nutzerübersicht (kompaktes Badge, rotes "×" oben rechts außerhalb) statt eines eigenen,
    // abweichenden Stils - die CSS-Regeln dafür kommen aus backstage_nutzermanagement weiter oben. ?>
    <style>
        #nn_ausgewaehlte_rollen { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.5rem; min-height: 1.6rem; }
    </style>
    <form action='website_datachange/edit_account.php' method='POST' onsubmit="if (document.querySelectorAll('input[name=\'neue_rollen[]\']').length === 0) { alert('Bitte mindestens eine Rolle hinzufügen.'); return false; } return true;">
        <input type='hidden' name='action' value='admin_erstellt_nutzer'/>
        <input type='hidden' name='admin_bn' value='<?php echo $nnBnAttr; ?>'>
        <input type='hidden' name='admin_pw' value='<?php echo $nnPwAttr; ?>'>
        <div class='field'>
            <label for='demo-category'>Benutzername</label>
            <input type='text' name='neuer_bn' class='Eingabe' style='color: white' required>
            <h5><br/></h5>
            <label for='demo-category'>Passwort</label>
            <input type='text' name='neuer_pw' class='Eingabe' style='color: white' required>
            <h5><br/></h5>
            <label for='demo-category'>Rollen <i>(ein Nutzer kann mehrere haben - Rolle wählen, dann "Hinzufügen")</i></label>
            <div style='display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;'>
                <select id='nn_rolle_auswahl'>
                    <option value='' disabled selected>Rolle hinzufügen ...</option>
                    <?php foreach ($nnRollen as $r) {
                        $rId = (int)$r['id'];
                        if (nmDarfRolleVergeben(getRollenFlags($rId), $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) {
                            echo "<option value='$rId'>" . htmlspecialchars($r['name']) . "</option>";
                        }
                    } ?>
                </select>
                <button type='button' class='button' onclick='nnRolleHinzufuegen()'>Hinzufügen</button>
            </div>
            <div id='nn_ausgewaehlte_rollen'></div>
        </div>
        <script>
            function nnRolleHinzufuegen() {
                var select = document.getElementById('nn_rolle_auswahl');
                var rolleId = select.value;
                var rolleName = select.options[select.selectedIndex].text;
                if (!rolleId || document.getElementById('nn_rolle_hidden_' + rolleId)) { return; }

                var container = document.getElementById('nn_ausgewaehlte_rollen');

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'neue_rollen[]';
                hidden.value = rolleId;
                hidden.id = 'nn_rolle_hidden_' + rolleId;
                container.appendChild(hidden);

                var badge = document.createElement('span');
                badge.className = 'nm-badge';
                badge.id = 'nn_rolle_badge_' + rolleId;
                badge.appendChild(document.createTextNode(rolleName));
                var entfernenBtn = document.createElement('button');
                entfernenBtn.type = 'button';
                entfernenBtn.className = 'nm-badge-remove';
                entfernenBtn.title = 'Rolle wieder entfernen';
                entfernenBtn.textContent = '×';
                entfernenBtn.onclick = function () { nnRolleEntfernen(rolleId); };
                badge.appendChild(entfernenBtn);
                container.appendChild(badge);
            }
            function nnRolleEntfernen(rolleId) {
                var badge = document.getElementById('nn_rolle_badge_' + rolleId);
                var hidden = document.getElementById('nn_rolle_hidden_' + rolleId);
                if (badge) { badge.remove(); }
                if (hidden) { hidden.remove(); }
            }
        </script>
        <ul class='actions'>
            <li><input type='submit' value='Anlegen' class='primary'/></li>
        </ul>
    </form>
    <?php } ?>
    <h5><br /></h5>
    <a href='#backstage_nutzermanagement' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ########################## -->
<!-- ########  Letzte DB-Änderungen  ######### -->
<!-- ########################## -->
<article id="backstage_letzte_aenderung">
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
    <h2>Letzte DB-Änderungen</h2>
    <?php // RECHTE-AUDIT: Diese Seite war bisher UNGESCHÜTZT erreichbar (der Datenbank-Änderungsverlauf
    // wurde unabhängig vom Login-Status angezeigt, sobald jemand direkt #backstage_letzte_aenderung
    // aufgerufen hat) - jetzt strikt nur echten Admins vorbehalten. ?>
    <?php if (!$istEchterAdmin) { ?>
    <p>Keine ausreichende Berechtigung. Nur Admins dürfen den DB-Verlauf einsehen.</p>
    <?php } else { ?>
    <p>Hier werden alle Datenbankänderungen dokumentiert, egal ob es um Löschung, Änderung oder Einfügen geht. Wenn ein Team ständig versucht, Dinge zu bearbeiten, die es nicht bearbeiten soll, siehst du das hier und kannst dem Team die Rechte wegnehmen. Die Änderungen sind in SQL formuliert. Falls du nicht weißt, wie SQL funktioniert, klicke einfach <a href='https://studyflix.de/informatik/structured-query-language-606'>hier</a></p>
    <?php if (!isset($_POST['load_db_verlauf'])) {
        $ladeAction = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
        echo "
        <form action='$ladeAction' method='POST'>
            <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES) . "'>
            <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES) . "'>
            <input type='hidden' name='load_db_verlauf' value='1'>
            <button type='submit' class='admin-menu-button admin-menu-button--adminonly'>DB-Verlauf jetzt laden</button>
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
    <?php } ?>
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
    <?php // RECHTE-AUDIT: Wie backstage_letzte_aenderung war auch diese Seite bisher ungeschuetzt
    // direkt per Hash-Link erreichbar - jetzt strikt nur echten Admins vorbehalten. ?>
    <?php if (!$istEchterAdmin) { ?>
    <p>Keine ausreichende Berechtigung. Nur Admins dürfen den Traffic einsehen.</p>
    <?php } else { ?>
    <p>Hier werden Website-Funktionalitäten getrackt.</p>
    <?php if (!isset($_POST['load_traffic'])) {
        $ladeAction = ($test_turnier_id==0) ? '/' : "/?test_turnier_id=$test_turnier_id";
        echo "
        <form action='$ladeAction' method='POST'>
            <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES) . "'>
            <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES) . "'>
            <input type='hidden' name='load_traffic' value='1'>
            <button type='submit' class='admin-menu-button admin-menu-button--adminonly'>Traffic jetzt laden</button>
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
    <?php } ?>
    <a href='#' class='button'>Zurück</a>
    <h5><br /></h5>
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

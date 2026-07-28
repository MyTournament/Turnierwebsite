<?php
// SICHERHEIT: MUSS vor dem ersten session_start() der gesamten Anfrage eingebunden werden (siehe
// Datei für Details - härtet HttpOnly/Secure/SameSite der Session-Cookies).
include_once __DIR__ . '/website_functionalities/session_bootstrap.php';

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

// ================================================================================================
// KO-PHASE: TURNIERBAUM- ODER TABELLENANSICHT - reine Anzeige-Präferenz für die Dauer der Session,
// bewusst KEINE Datenbank-Spalte dafür (siehe Chat). Turnierbaum ist der neue Standard. Umschaltbar
// per einfachem GET-Link (nicht POST) - harmlos bei Seiten-Reload, kein "Formular erneut senden".
// ================================================================================================
if (isset($_GET['ko_ansicht']) && in_array($_GET['ko_ansicht'], ['baum', 'tabelle'], true)) {
    $_SESSION['ko_ansicht'] = $_GET['ko_ansicht'];
}
$koAnsicht = $_SESSION['ko_ansicht'] ?? 'baum';

// ================================================================================================
// CAPTCHA-CHECK FÜR DEN LOGIN-RATE-LIMITER (siehe weiter unten beim eigentlichen Login-Block) -
// eigener formKey "login", damit sich dieser Ablauf nicht mit Registrierung ("user_register") oder
// Team-Anmeldung ("register") überschneidet. Das Login-Formular postet direkt an "/" (index.php
// selbst, kein eigenes Backend-Skript), daher muss der cb_action-Check hier ganz am Anfang stehen.
// ================================================================================================
if (isset($_POST['cb_action']) && $_POST['cb_action'] === 'check' && isset($_POST['cb_formkey']) && $_POST['cb_formkey'] === 'login') {
    require_once __DIR__ . '/website_functionalities/captcha_blanki.php';
    $loginCbRes = CaptchaBlanki::preverify($_POST);
    $_SESSION['flash_error_login_captcha'] = $loginCbRes['ok']
        ? 'Captcha bestätigt. Du kannst jetzt einloggen.'
        : (($loginCbRes['remaining']>0) ? ('Captcha falsch. Verbleibende Versuche: '.$loginCbRes['remaining']) : 'Captcha 3x fehlgeschlagen. Die Seite wurde neu geladen.');
    // Es gibt inzwischen zwei Account-Login-Formulare (#login und #backstage, siehe Chat) - ein
    // verstecktes Feld im jeweiligen Formular sagt, zu welchem davon nach dem Captcha-Check
    // zurückgesprungen werden soll (Standard: #login, die neue primäre Login-Seite).
    $cbReturnHash = isset($_POST['cb_return_hash']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['cb_return_hash']) : '';
    if ($cbReturnHash === '') { $cbReturnHash = 'login'; }
    header('Location: /#' . $cbReturnHash);
    exit;
}

//IMPORT PHP-DOCS
include_once 'database/db_connection.php'; //Datenbanklogin //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht
//include_once 'database/db_backup.php';
include_once 'website_functionalities/csrf.php'; // CSRF-Schutz (csrf_field()/csrf_verify()) - siehe Datei für Details

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
    header("Location: /bullerei/home.php");
    exit;
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
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST') { ?>
        <!-- "Formular erneut senden?" beim Reload vermeiden - diesmal bewusst OHNE Server-Redirect
             (der hat beim ersten Versuch den Login kaputt gemacht, siehe Chat/Git-Historie). Rein
             client-seitig: die Seite wird ganz normal fertig gerendert wie bisher, nur die
             Browser-Historie wird per history.replaceState() auf eine GET-Adresse umgeschrieben.
             Kann dadurch nichts an der eigentlichen Seite kaputt machen - im schlimmsten Fall wirkt
             es einfach nicht in jedem Browser/Fall, aber es blockiert nie das Rendering. -->
        <script>
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + window.location.hash);
            }
        </script>
        <?php } ?>
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
                gap: 0.6rem;
                width: 100%;
                margin: 0.8rem 0 0;
            }
            .ko-phase-cta--single {
                max-width: 440px;
            }
            .ko-phase-btn {
                position: relative;
                display: flex;
                flex: 1 1 260px;
                align-items: center;
                gap: 0.7rem;
                min-height: auto;
                padding: 0.55rem 0.85rem;
                border-radius: 10px;
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
            /* Turnierbaum/Tabelle-Umschalter für die KO-Phase - reiner Session-Zustand, siehe $koAnsicht
               in index.php. Zwei gleichwertige Pillen statt eines echten Toggle-Switches, damit auch per
               Tastatur/Screenreader klar zwei separate, direkt anspringbare Links vorliegen. */
            .ko-ansicht-umschalter { display: inline-flex; gap: 0.3rem; margin: 0.8rem 0 1rem; padding: 0.25rem; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14); border-radius: 999px; }
            .ko-ansicht-btn { padding: 0.4rem 0.9rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; color: #cdd8ea !important; text-decoration: none; transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out; }
            .ko-ansicht-btn:hover { background: rgba(255,255,255,0.08); }
            .ko-ansicht-btn--aktiv, .ko-ansicht-btn--aktiv:hover { background: var(--admin-accent, #8b5cf6); color: #ffffff !important; }
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
    if (!isset($_POST['bn'])) { $_POST['bn'] = null; }
    if (!isset($_POST['pw'])) { $_POST['pw'] = null; }

    // $gameEditMode wird weiter unten (nach Team- UND Account-Login-Verarbeitung) aus dem Login-
    // Zustand hergeleitet, nicht mehr aus einem manuellen POST-Toggle - siehe dortigen Kommentar.
    // $expertenmodus ist ein totes Feature (hat schon vor dieser Änderung keine erkennbare Wirkung
    // auf printGames() gehabt) und bleibt nur als Parameter für die vielen print...()-Funktionen
    // erhalten, damit deren Signaturen nicht angefasst werden müssen.
    $expertenmodus = 0;

    // ============================================================================================
    // GEMEINSAMES LOGIN-FELD FÜR CMS & BACKSTAGE (früher zwei getrennte Logins/Seiten)
    // ============================================================================================
    // Backstage.php wurde komplett in index.php gemergt; ein einziges bn/pw-Feld entscheidet über
    // Zugriff auf CMS-Bearbeitung UND Backstage, je nachdem welche Rollen-Flags der Account hat.
    // Fallback auf die Session, damit der Login nach einem Redirect (z.B. nach dem Speichern in Edit Data) erhalten bleibt
    $bn = $_POST["bn"] !== null ? $_POST["bn"] : (isset($_SESSION['admin_bn']) ? $_SESSION['admin_bn'] : null);
    $pw = $_POST["pw"] !== null ? $_POST["pw"] : (isset($_SESSION['admin_pw']) ? $_SESSION['admin_pw'] : null);

    include_once 'website_datachange/login_interface.php';
    // ============================================================================================
    // RATE-LIMITING FÜR DEN LOGIN: bisher konnte bn/pw beliebig oft ohne jede Bremse durchprobiert
    // werden (bei Klartext-Passwörtern besonders riskant). Ab $loginSchwelleFuerCaptcha
    // Fehlversuchen in Folge (Session-Zähler) muss erst ein Bild-Captcha bestätigt werden, bevor der
    // nächste Versuch überhaupt gegen die DB geprüft wird - genau wie bei Registrierung/Team-
    // Anmeldung. "Frischer Versuch" = bn UND pw kamen direkt aus $_POST (nicht aus der Session-
    // Fallback-Wiederherstellung) - nur dann zählt es als echter Login-Versuch.
    // ============================================================================================
    $istFrischerLoginVersuch = ($_POST["bn"] !== null && $_POST["pw"] !== null);
    if (!isset($_SESSION['login_fail_count'])) { $_SESSION['login_fail_count'] = 0; }
    $loginSchwelleFuerCaptcha = 5;
    $loginBenoetigtCaptcha = $_SESSION['login_fail_count'] >= $loginSchwelleFuerCaptcha;

    if ($istFrischerLoginVersuch && $loginBenoetigtCaptcha) {
        require_once __DIR__ . '/website_functionalities/captcha_blanki.php';
        $rollenInfo = CaptchaBlanki::passed('login') ? getUserRollenInfo($conn, $bn, $pw) : null;
    } else {
        $rollenInfo = ($bn !== null && $pw !== null) ? getUserRollenInfo($conn, $bn, $pw) : null;
    }
    if ($istFrischerLoginVersuch) {
        $_SESSION['login_fail_count'] = ($rollenInfo !== null) ? 0 : ($_SESSION['login_fail_count'] + 1);
        // Gezielte Rückmeldung statt einer einzigen generischen Meldung: existiert der Benutzername
        // gar nicht, macht "Passwort falsch" keinen Sinn - stattdessen Hinweis auf Registrierung.
        // Existiert er, aber der Login schlug trotzdem fehl, kann es nur am Passwort liegen.
        if ($rollenInfo === null) {
            if (benutzernameExistiert($conn, $bn)) {
                $_SESSION['flash_error_login'] = 'Passwort falsch.';
            } else {
                $_SESSION['flash_error_login'] = "Diesen Account gibt es nicht. <a href='#register_account'>Hier kannst du dich registrieren.</a>";
            }
        } else {
            unset($_SESSION['flash_error_login']);
        }
    }
    $rechteFlags = $rollenInfo['flags'] ?? array_fill_keys(['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'], false);
    // "Zufällige Spiele eintragen"-Buttons (Gruppenphase/K.-o.-Phase/Losing Bracket, nur im Testmodus):
    // sichtbar für Admin, Co-Admin, Turniermaster, Backstage-Zugang UND Schiedsrichter*in - exakt die
    // Vereinigung aus backstage- und alle_spiele-Flag (Schiedsrichter*in hat nur Letzteres).
    $darfZufaelligeSpieleEintragen = $rechteFlags['backstage'] || $rechteFlags['alle_spiele'];

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
    // Session-Persistenz: an JEDEN gültigen Login gekoppelt (nicht nur CMS/Backstage), damit auch ein
    // frisch registrierter Account ohne jede Rolle nach einem Redirect eingeloggt bleibt und die
    // Admin-Leiste (siehe unten) durchgängig "Eingeloggt als ..." anzeigen kann.
    if ($rollenInfo !== null) {
        // Login in der Session merken, damit er nach einem Redirect (z.B. edit_variables.php, edit_teams.php) erhalten bleibt
        $_SESSION['admin_bn'] = $bn;
        $_SESSION['admin_pw'] = $pw;
        // MUTUAL EXCLUSIVITY: Team- und Account-Login sollen nie gleichzeitig aktiv sein (siehe unten
        // beim Team-Login-Block) - ein frischer Account-Login beendet daher einen evtl. aktiven
        // Team-Login. Sonst wäre beim Ergebnis-Eintragen unklar, welche Identität gemeint ist.
        unset($_SESSION['team_bn'], $_SESSION['team_pw']);
    } else {
        unset($_SESSION['admin_bn'], $_SESSION['admin_pw']);
    }

    // POST/REDIRECT/GET-Redirect nach Login HIER WIEDER ENTFERNT: hat auf der echten Website den
    // Login komplett kaputt gemacht (nach dem Einloggen als Account blieb die Seite leer/dunkelgrau) -
    // Ursache nicht sicher gefunden, deshalb zurückgebaut statt weiter zu raten. "Formular erneut
    // senden?" beim Reload wird jetzt stattdessen rein clientseitig per history.replaceState() im
    // <head> vermieden (kein Server-Redirect mehr, kann die Seite dadurch nicht mehr kaputt machen).

    // ============================================================================================
    // SESSION-BASIERTE LOGIN-PERSISTENZ FÜR TEAMS (analog zum Admin-Login direkt darüber)
    // ============================================================================================
    // Ersetzt das bisherige "bei jedem Klick Kürzel+Passwort neu eintippen" - ein Team loggt sich
    // einmal ein (Kürzel+Passwort in eigenen POST-Feldern "team_login_kuerzel"/"team_login_passwort",
    // bewusst NICHT "bn"/"pw" genannt, um keine Kollision mit dem Account-Login-Formular oder den
    // eingebetteten Spiel-Formularen zu riskieren), der Login bleibt danach in der Session erhalten
    // und wird bei jedem Request frisch gegen die DB reverifiziert (gleiches Muster wie
    // getUserRollenInfo() oben). "?team_logout=1" loggt gezielt aus.
    if (isset($_GET['team_logout'])) {
        unset($_SESSION['team_bn'], $_SESSION['team_pw']);
    }
    if (isset($_POST['team_login_kuerzel']) && isset($_POST['team_login_passwort'])) {
        $teamLoginVersuchBn = $_POST['team_login_kuerzel'];
        $teamLoginVersuchPw = $_POST['team_login_passwort'];
        $teamLoginVersuchErgebnis = getTeamLoginInfo($conn, $TurnierID, $teamLoginVersuchBn, $teamLoginVersuchPw);
        if ($teamLoginVersuchErgebnis !== null) {
            $_SESSION['team_bn'] = $teamLoginVersuchBn;
            $_SESSION['team_pw'] = $teamLoginVersuchPw;
            // MUTUAL EXCLUSIVITY: siehe Kommentar beim Account-Login oben - ein frischer Team-Login
            // beendet einen evtl. aktiven Account-Login.
            unset($_SESSION['admin_bn'], $_SESSION['admin_pw']);
        } else {
            // Gezielte Rückmeldung wie beim Account-Login: existiert das Kürzel in diesem Turnier gar
            // nicht, macht "Passwort falsch" keinen Sinn - stattdessen Hinweis, das Kürzel zu prüfen.
            if (teamKuerzelExistiertInTurnier($conn, $TurnierID, $teamLoginVersuchBn)) {
                $_SESSION['flash_error_team_login'] = 'Passwort falsch.';
            } else {
                // ZUSATZ-CHECK (siehe Chat): das Kürzel+Passwort könnte zu einem VERGANGENEN Turnier
                // gehören (Team hat früher mal mitgespielt) - dann ist "gibt es nicht" irreführend,
                // stattdessen gezielt auf "Vergangene Turniere" verweisen statt nur "existiert nicht".
                $teamAusVergangenemTurnier = getTeamLoginInfoAusVergangenemTurnier($conn, $websiteId, $teamLoginVersuchBn, $teamLoginVersuchPw);
                if ($teamAusVergangenemTurnier !== null) {
                    $vergangenerTurnierNameSafe = htmlspecialchars($teamAusVergangenemTurnier['turnier_name'], ENT_QUOTES, 'UTF-8');
                    // SICHERHEIT: bewusst kein htmlspecialchars() auf die Gesamtnachricht (Muster wie beim
                    // Account-Login-Pendant oben) - der eingebettete Link ist eine feste Zeichenkette,
                    // nur $vergangenerTurnierNameSafe (Nutzereingabe/Turniername) ist separat escaped.
                    $_SESSION['flash_error_team_login'] = "Dieses Team-Kürzel und Passwort gehören zum vergangenen Turnier \"$vergangenerTurnierNameSafe\", nicht zum aktuellen Turnier. Geh zu \"Vergangene Turniere\" und logg dich dort im passenden Turnier ein. <a href='#history' class='button primary'>Zu vergangenen Turnieren</a>";
                } else {
                    $_SESSION['flash_error_team_login'] = 'Dieses Team-Kürzel gibt es in diesem Turnier nicht. Bitte nochmal nachschauen.';
                }
            }
        }
        // POST/REDIRECT/GET-Redirect HIER WIEDER ENTFERNT: hat beim Account-Login (gleiches Muster)
        // den Login auf der echten Website kaputt gemacht - sicherheitshalber auch hier zurückgebaut,
        // auch ohne dass das Team-Login-Pendant konkret gemeldet wurde. "Formular erneut senden?"
        // wird jetzt stattdessen rein clientseitig per history.replaceState() vermieden (siehe <head>).
    }
    $teamBnSession = isset($_SESSION['team_bn']) ? $_SESSION['team_bn'] : null;
    $teamPwSession = isset($_SESSION['team_pw']) ? $_SESSION['team_pw'] : null;
    $teamLoginInfo = ($teamBnSession !== null && $teamPwSession !== null) ? getTeamLoginInfo($conn, $TurnierID, $teamBnSession, $teamPwSession) : null;
    if ($teamLoginInfo === null) {
        unset($_SESSION['team_bn'], $_SESSION['team_pw']);
    }
    $teamEingeloggt = ($teamLoginInfo !== null);
    $teamDarfEditieren = $teamEingeloggt && ((int)$teamLoginInfo['bearbeitungsrechte'] === 1);

    // ============================================================================================
    // BEARBEITUNGSMODUS FÜR ERGEBNISSE: JETZT REIN LOGIN-BASIERT STATT MANUELLER POST-TOGGLE
    // ============================================================================================
    // Vorher konnte JEDE Person (auch ganz ohne Login) per Klick auf "Ergebnisse eintragen" die
    // Plus/Häkchen/Stern-Buttons einblenden - die eigentliche Absicherung passierte erst beim
    // tatsächlichen Absenden in edit_games.php. Jetzt ist die Sichtbarkeit selbst schon ans Login
    // gekoppelt (Team mit Bearbeitungsrechten ODER Account mit "alle_spiele"-Flag) - wer nicht
    // eingeloggt ist, sieht statt der Buttons einen Login-Hinweis (siehe printEditModeStuff()).
    // edit_games.php prüft beim Absenden trotzdem unverändert erneut (doppelte Absicherung bleibt).
    $gameEditMode = ($teamDarfEditieren || $rechteFlags['alle_spiele']) ? 1 : 0;

    // ============================================================================================
    // CMS-BEARBEITUNGSMODUS: JETZT SESSION-BASIERT STATT NUR PRO REQUEST
    // ============================================================================================
    // Vorher hing der Bearbeitungsmodus rein am POST-Feld "edit_content_mode" - jede Navigation oder
    // jedes Absenden eines Formulars OHNE dieses Feld (z.B. nach dem Speichern/Löschen eines
    // Bausteins, das über edit_content.php redirectet) hat den Modus stillschweigend wieder
    // ausgeschaltet. Jetzt wird der Zustand in der Session gemerkt und bleibt so über Redirects und
    // andere Aktionen hinweg erhalten, bis man aktiv auf "CMS verlassen" klickt. Der CMS/Backstage-
    // Umschalt-Button schickt dafür jetzt explizit "True" ODER "False" mit (statt das Feld beim
    // Ausschalten einfach wegzulassen) - nur SO ein expliziter Wert darf den Session-Zustand ändern,
    // jedes andere Formular auf der Seite lässt ihn unangetastet.
    if (!isset($edit_content_mode)) { $edit_content_mode = False; }
    if($LoggedInWithCMSorHigher){
        if (isset($_POST['edit_content_mode'])) {
            $_SESSION['cms_edit_mode'] = ($_POST['edit_content_mode'] === 'True');
        }
        $edit_content_mode = isset($_SESSION['cms_edit_mode']) ? $_SESSION['cms_edit_mode'] : False;
    } else {
        unset($_SESSION['cms_edit_mode']);
    }
    // Leiste selbst erscheint bei JEDEM gültigen Login, auch ohne jede Rolle (z.B. frisch registrierte
    // Accounts) - dann eben nur mit "Eingeloggt als ..." + Logout und OHNE jeden Funktions-Button
    // (CMS/Settings/Infos prüfen weiter unten ohnehin jeweils ihr eigenes Flag einzeln). Vorher war die
    // gesamte Leiste an CMS- oder Backstage-Flag gekoppelt, wodurch rechtelose Accounts nach dem Login
    // gar kein Feedback bekamen, dass der Login überhaupt geklappt hat.
    if ($rollenInfo !== null) {
        // SICHERHEIT: escapte Fassung von bn/pw fuer die Ausgabe in HTML (Admin-Leiste unten) - der
        // Benutzername kommt seit der Selbstregistrierung direkt vom Nutzer selbst und landete hier
        // bisher unescaped im Seitenquelltext ("Eingeloggt als ..." + verstecktes Formularfeld). Ein
        // Account mit z.B. einem Anfuehrungszeichen/Script-Tag im Namen haette damit gespeichertes XSS
        // im eigenen Browser ausgeloest - und, brisanter, auch im Browser eines Admins/Co-Admins, der
        // per "Login als User" diesen Account impersoniert (siehe Login_Als_User in edit_account.php).
        $bnBarSafe = htmlspecialchars((string)$bn, ENT_QUOTES, 'UTF-8');
        $pwBarSafe = htmlspecialchars((string)$pw, ENT_QUOTES, 'UTF-8');
        $bnAvatarBar = htmlspecialchars(ermittleAnzeigeAvatar($conn, $rollenInfo['benutzer_id']), ENT_QUOTES, 'UTF-8');
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
        // Vorher eigenes Blau für "Standard"/turnier_settings - seit turnier_settings exklusiv bei
        // Admin/Co-Admin liegt (nicht mehr auch Turniermaster, siehe rollen_definitionen.php), ist die
        // Sichtbarkeit identisch zur Bernstein-Stufe. Zwei Farben für dieselbe Zielgruppe wären nur
        // verwirrend gewesen, deshalb auf denselben Bernstein-Wert zusammengelegt (siehe Chat).
        $adminBorderCoadminWert = $istAdminOderCoAdmin ? '#f59e0b' : $adminBorderNeutral;
        $adminBorderStandardWert = $adminBorderCoadminWert;
        $adminBorderAdminonlyWert = $istAdminOderCoAdmin ? '#ef4444' : $adminBorderNeutral;
        $adminBorderCmsWert = $istAdminOderCoAdmin ? '#ec4899' : $adminBorderNeutral;
        // Sechste Stufe (Türkis): braucht WEDER cms noch teams/turnier_settings, sondern backstage ODER
        // alle_spiele - die einzige Kombination, die exakt Admin, Co-Admin, Turniermaster,
        // Backstage-Zugang UND Schiedsrichter*in erfasst (Schiedsrichter*in hat sonst in keiner der
        // obigen vier Stufen einen Platz, da er/sie kein backstage-Flag hat). Genutzt für die
        // "Zufällige Spiele eintragen"-Buttons im Testmodus.
        $adminBorderTestspieleWert = $istAdminOderCoAdmin ? '#14b8a6' : $adminBorderNeutral;
        // Siebte Stufe: reines backstage-Flag (Admin, Co-Admin, Turniermaster, Backstage-Zugang -
        // OHNE Schiedsrichter*in, anders als die Türkis-Stufe oben, die zusätzlich alle_spiele
        // einschließt). Eigene Farbe nötig, weil diese Zielgruppe mit keiner der anderen Stufen
        // identisch ist: schmaler als Türkis, breiter als Grün (teams-Flag hat Backstage-Zugang nicht).
        // Genutzt für Telefonnummern/Team-Passwörter/Warteliste/ER-Diagramm im Infos-Menü.
        $adminBorderBackstageWert = $istAdminOderCoAdmin ? '#3b82f6' : $adminBorderNeutral;
        echo "
        <style>
            :root {
                --admin-accent: #8b5cf6; --admin-accent-deep: #6d28d9; --admin-accent-light: #ddd6fe;
                /* Alle Backstage-Buttons tragen denselben Lila-Verlauf als Hintergrund - WER eine
                   Funktion sehen darf, zeigt stattdessen ein farbiger RAHMEN um den Button (siehe
                   .admin-menu-button--teams/--coadmin/--adminonly/--backstage weiter unten). Deutlich
                   unterscheidbare, zum Lila passende Rahmenfarben: Grün (teams-Flag: Admin/Co-Admin/
                   Turniermaster), Blau (backstage-Flag: zusätzlich Backstage-Zugang), Bernstein
                   (Admin+Co-Admin), Rot (nur echte Admins). Werte kommen aus PHP: nur Admin/Co-Admin
                   bekommen die echten Farben, alle anderen den neutralen Standard-Rahmen. */
                --admin-border-teams: $adminBorderTeamsWert;
                --admin-border-standard: $adminBorderStandardWert;
                --admin-border-coadmin: $adminBorderCoadminWert;
                --admin-border-adminonly: $adminBorderAdminonlyWert;
                --admin-border-backstage: $adminBorderBackstageWert;
                /* Fünfte Stufe: braucht nur das cms-Flag (Autor*in), kein Backstage-Zugang nötig -
                   deshalb eigene Farbe statt einer der obigen vier, die alle backstage-artige
                   Rechte betreffen. */
                --admin-border-cms: $adminBorderCmsWert;
                --admin-border-testspiele: $adminBorderTestspieleWert;
            }
            #admin-bar { position: fixed; top: 0; left: 0; width: 100%; z-index: 10000; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; padding: 0.35rem 0.75rem; background: rgba(30, 12, 48, 0.94); border-bottom: 1px solid var(--admin-accent); box-shadow: 0 2px 12px rgba(139, 92, 246, 0.25); box-sizing: border-box; }
            /* Rechte Chip-Leiste im gleichen kompakten Stil wie die Team-Leiste (#team-bar-status) -
               kleines Initialen-Badge statt 'Eingeloggt als X', Logout bleibt direkt daneben statt
               hinter einem Profil-Klick versteckt. Die CMS/Settings/Infos-Buttons sind KEIN Teil
               dieses Profil-Chips mehr, sondern wandern auf die linke Seite (#admin-bar-buttons). */
            #admin-bar-status { color: var(--admin-accent-light); font-size: 0.78rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; }
            .admin-bar-avatar { flex-shrink: 0; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: var(--admin-accent-deep); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; }
            .admin-bar-name { color: #fff; }
            /* Avatar+Name sind jetzt gemeinsam EIN Klick-Ziel zur eigenen Profilseite (#account_profil,
               siehe Chat) - eigener Link-Wrapper statt Text-Unterstreichung, damit es weiterhin wie ein
               Profil-Chip aussieht statt wie ein gewöhnlicher Textlink. */
            .admin-bar-profil-link { display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 999px; padding: 0.1rem 0.4rem 0.1rem 0.1rem; transition: background-color 0.15s ease-in-out; }
            .admin-bar-profil-link:hover { background: rgba(255,255,255,0.12); }
            #admin-bar-status .admin-bar-logout { margin: 0; padding: 0.25rem 0.65rem; font-size: 0.72rem; white-space: nowrap; background: rgba(255,255,255,0.1); color: #ffffff !important; border-radius: 999px; font-weight: 400 !important; }
            #admin-bar-status .admin-bar-logout:hover { background: rgba(255,255,255,0.18); }
            #admin-bar-buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
            #admin-bar-buttons form { margin: 0; display: inline; }
            #admin-bar .button { margin: 0; padding: 0.3rem 0.65rem; font-size: 0.72rem; white-space: nowrap; background: var(--admin-accent-deep); color: #ffffff !important; font-weight: 300 !important; }
            /* CMS-Button: eigene Farbstufe (siehe Farb-Legende in Settings/Infos) */
            #admin-bar .button--cms { border: 2px solid var(--admin-border-cms); }
            /* Settings-/Infos-Button: beide hängen nur am backstage-Flag, dieselbe Zielgruppe wie die
               grüne Stufe (Turniermaster, Backstage-Zugang, Co-Admin, Admin) */
            #admin-bar .button--teams { border: 2px solid var(--admin-border-teams); }
            /* Hamburger-Umschalter für #admin-bar-buttons: nur sichtbar/aktiv, wenn CMS+Settings+Infos
               (bis zu 3 Buttons) neben Profil-Chip nicht mehr in eine Zeile passen - erst so weit wie
               möglich kompakt gemacht (siehe Padding/Font oben), das hier ist nur das Netz für sehr
               schmale Bildschirme. JS (weiter unten) misst statt eine feste Pixel-Breakpoint-Grenze zu
               raten, ob wirklich umgebrochen wurde. */
            #admin-bar-hamburger { display: none; flex-shrink: 0; width: 1.8rem; height: 1.8rem; padding: 0; border-radius: 6px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: #fff; font-size: 1rem; line-height: 1; cursor: pointer; }
            #admin-bar-hamburger:hover { background: rgba(255,255,255,0.18); }
            #admin-bar.admin-bar--collapsed #admin-bar-hamburger { display: inline-flex; align-items: center; justify-content: center; }
            #admin-bar.admin-bar--collapsed #admin-bar-buttons { display: none; }
            #admin-bar.admin-bar--collapsed.admin-bar--open #admin-bar-buttons {
                display: flex; flex-direction: column; align-items: stretch; gap: 0.3rem;
                position: absolute; top: 100%; left: 0.75rem; margin-top: 0.4rem; padding: 0.5rem;
                background: rgba(30, 12, 48, 0.98); border: 1px solid var(--admin-accent); border-radius: 8px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.35); z-index: 10001; width: max-content; max-width: calc(100vw - 1.5rem);
            }
            #wrapper { padding-top: 64px; }
            .admin-menu-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; max-width: 640px; margin: 1rem auto; }
            .admin-menu-button { display: inline-block; min-width: 190px; margin: 0; padding: 0.5rem 1rem; font-size: 0.85rem; line-height: 1.2; border-radius: 6px; background: linear-gradient(135deg, var(--admin-accent-deep), var(--admin-accent)); border: 2px solid var(--admin-border-standard); color: #f5f2ff !important; text-transform: none; letter-spacing: 0.02em; text-align: center; text-decoration: none; }
            .admin-menu-button:hover { background: linear-gradient(135deg, var(--admin-accent), #a78bfa); }
            /* Alle drei Rechte-Stufen nutzen denselben Lila-Hintergrund wie der Standard-Button -
               einziger Unterschied ist die Rahmenfarbe (siehe :root weiter oben). */
            .admin-menu-button--coadmin { border-color: var(--admin-border-coadmin); }
            .admin-menu-button--adminonly { border-color: var(--admin-border-adminonly); }
            .admin-menu-button--teams { border-color: var(--admin-border-teams); }
            .admin-menu-button--backstage { border-color: var(--admin-border-backstage); }
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
            .admin-legende-swatch--testspiele { border-color: var(--admin-border-testspiele); }
            .admin-legende-swatch--backstage { border-color: var(--admin-border-backstage); }
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
            <button type='button' id='admin-bar-hamburger' title='Menü' onclick=\"document.getElementById('admin-bar').classList.toggle('admin-bar--open');\">&#9776;</button>
            <div id='admin-bar-buttons'>
        ";
        if ($LoggedInWithCMSorHigher) {
            echo "<form action='$adminBarActionUrl' method='POST'>
                <input type='hidden' name='bn' value='$bnBarSafe'>
                <input type='hidden' name='pw' value='$pwBarSafe'>";
            if ($edit_content_mode == True) {
                // Bewusst .button OHNE .primary (wie Settings/Infos) - .primary bringt eigene
                // Schriftschnitt-Regeln aus dem Grundtheme mit, die hier für einen einheitlichen
                // Look in der Admin-Leiste nicht gewünscht sind.
                // "False" wird jetzt explizit mitgeschickt (nicht mehr einfach weggelassen) - nur ein
                // ausdrücklicher Wert darf den jetzt session-basierten Modus umschalten.
                echo "<input type='hidden' name='edit_content_mode' value='False'>
                <button type='submit' class='button button--cms'>CMS verlassen</button>";
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
            <div id='admin-bar-status'>
                <a href='#account_profil' class='admin-bar-profil-link' title='Eigenes Profil ansehen'>
                    <span class='admin-bar-avatar'>$bnAvatarBar</span>
                    <span class='admin-bar-name'>$bnBarSafe</span>
                </a>
                <a href='/?logout=1' class='button admin-bar-logout' onclick=\"var s=window.location.search; this.href='/'+s+(s?'&':'?')+'logout=1'+window.location.hash;\">Logout</a>
            </div>
        </div>
        <script>
            // Bewusst SOFORT ausgeführt statt in einem DOMContentLoaded-Listener: test_turnier_mode.php
            // (falls Testmodus aktiv) rendert VOR der Admin-Leiste und misst admin-bar.offsetHeight in
            // seinem eigenen DOMContentLoaded-Listener, um sich selbst passend darunter zu stapeln. Da
            // mehrere DOMContentLoaded-Listener in Registrierungsreihenfolge (= Dokumentreihenfolge)
            // feuern, würde ein hier ebenfalls per DOMContentLoaded verzögertes Ein-/Ausklappen ERST
            // NACH der Testmodus-Positionierung laufen - der Testmodus-Balken hätte dann die falsche
            // (zu große) Höhe der Admin-Leiste gemessen. Ein normales <script> direkt nach dem Element
            // läuft dagegen synchron beim Parsen, also bevor irgendein DOMContentLoaded-Listener feuert.
            (function() {
                function pruefeAdminBarUeberlauf() {
                    var bar = document.getElementById('admin-bar');
                    var buttons = document.getElementById('admin-bar-buttons');
                    var status = document.getElementById('admin-bar-status');
                    if (!bar || !buttons || !status) return;
                    bar.classList.remove('admin-bar--collapsed', 'admin-bar--open');
                    if (Math.abs(buttons.getBoundingClientRect().top - status.getBoundingClientRect().top) > 2) {
                        bar.classList.add('admin-bar--collapsed');
                    }
                }
                pruefeAdminBarUeberlauf();
                window.addEventListener('resize', pruefeAdminBarUeberlauf);
                document.addEventListener('click', function(e) {
                    var bar = document.getElementById('admin-bar');
                    if (bar && bar.classList.contains('admin-bar--open') && !bar.contains(e.target)) {
                        bar.classList.remove('admin-bar--open');
                    }
                });
            })();
        </script>
        ";
    }

    // ================================================================================================
    // FIXIERTE TEAM-LEISTE (Pendant zur violetten Admin-Leiste, nur wenn ein Team eingeloggt ist)
    // ================================================================================================
    // Eigene Akzentfarbe (Grün/Teal statt Lila), damit auf einen Blick klar ist, ob man als Team oder
    // als Account eingeloggt ist (die beiden schließen sich laut Vorgabe ohnehin gegenseitig aus).
    // Stapelt sich per JS (offsetHeight-Messung, gleiches Muster wie #test-modus-bar in
    // test_turnier_mode.php) sauber unter Admin-Leiste UND Testmodus-Leiste, je nachdem was aktiv ist.
    if ($teamEingeloggt) {
        $teamKuerzelBarSafe = htmlspecialchars((string)$teamLoginInfo['kuerzel'], ENT_QUOTES, 'UTF-8');
        $teamIdBar = (int)$teamLoginInfo['id'];
        $teamInitialsBar = htmlspecialchars(strtoupper(substr((string)$teamLoginInfo['kuerzel'], 0, 2)), ENT_QUOTES, 'UTF-8');
        echo "
        <style>
            /* --team-accent (Teal/Grün) bleibt der Hintergrund/Rahmen der Leiste selbst - auf
               ausdrücklichen Wunsch beibehalten. NEU: --team-highlight, eine warme, komplementäre
               Signalfarbe (Orange) für die Leisten-SCHRIFT UND als Markierung überall dort, wo etwas
               das eigene Team betrifft (eigene Zeilen/Karten in Tabellen/Turnierbaum, Team-Status-Box,
               Teamzertifikat-Banner). Grund: das vorherige einheitliche Teal kollidierte optisch zu stark
               mit dem hellen Gruen des Finalisieren-Buttons und dem Blau des Plus-Buttons, die oft in
               derselben Ansicht auftauchen - siehe Chat. */
            :root { --team-accent: #14b8a6; --team-accent-deep: #0f766e; --team-accent-light: #99f6e4; --team-highlight: #fb923c; }
            /* Kompakte Account-Chip-Leiste statt breiter Statuszeile (Muster wie bei anderen Websites
               üblich: kleines Avatar-Badge + Name, Logout bleibt bewusst direkt daneben statt hinter
               einem Profil-Klick versteckt - praktisch zum schnellen Testen). */
            #team-bar { position: fixed; left: 0; width: 100%; z-index: 9999; display: flex; justify-content: flex-end; padding: 0.35rem 0.75rem; background: rgba(6, 45, 41, 0.94); border-bottom: 1px solid var(--team-accent); box-shadow: 0 2px 12px rgba(20, 184, 166, 0.25); box-sizing: border-box; }
            /* Avatar-Kreis (voll eingefärbt, Teal-Hintergrund + weiße Initialen) bleibt klar als eigenes
               Icon-Element abgegrenzt - direkt daneben ist der gesamte Textbereich einheitlich orange
               (vorher wechselte die Farbe MITTEN in der Phrase Team plus Kuerzel von Orange auf Weiss,
               das wirkte unruhig/zufaellig statt bewusst gestaltet - siehe Chat). */
            #team-bar-status { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--team-highlight); white-space: nowrap; }
            .team-bar-avatar { flex-shrink: 0; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: var(--team-accent-deep); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.62rem; font-weight: 700; }
            .team-bar-name b { color: var(--team-highlight); font-weight: 800; }
            .team-bar-link { color: var(--team-highlight); text-decoration: underline; text-underline-offset: 2px; }
            .team-bar-link:hover { color: #fff; }
            #team-bar-status .team-bar-logout { margin: 0; padding: 0.25rem 0.65rem; font-size: 0.72rem; white-space: nowrap; background: rgba(255,255,255,0.1); color: #ffffff !important; border-radius: 999px; font-weight: 400 !important; }
            #team-bar-status .team-bar-logout:hover { background: rgba(255,255,255,0.18); }
        </style>
        <div id='team-bar'>
            <div id='team-bar-status'>
                <span class='team-bar-avatar'>$teamInitialsBar</span>
                <span class='team-bar-name'>Team <b>$teamKuerzelBarSafe</b></span>
                <a href='?teamId=$teamIdBar#teaminfo' class='team-bar-link'>Teamseite</a>
                <a href='/?team_logout=1' class='button team-bar-logout' onclick=\"var s=window.location.search; this.href='/'+s+(s?'&':'?')+'team_logout=1'+window.location.hash;\">Logout</a>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Reihenfolge auf Wunsch geändert: Team-/Account-Leiste (wer eingeloggt ist) steht jetzt
                // immer ZUOBERST, die Testmodus-Leiste darunter - vorher war es umgekehrt. Team-Leiste
                // und Admin-Leiste schließen sich gegenseitig aus, daher hier nur admin-bar als 'davor'
                // berücksichtigt (praktisch also immer top:0). Die Gesamt-Padding-Berechnung zählt
                // trotzdem alle drei Leisten zusammen, damit es unabhängig von der Skript-Ausführungs-
                // reihenfolge (test_turnier_mode.php vs. hier) am Ende immer zum selben Ergebnis kommt.
                var teamBar = document.getElementById('team-bar');
                var adminBar = document.getElementById('admin-bar');
                var testBar = document.getElementById('test-modus-bar');
                var wrapper = document.getElementById('wrapper');
                var offset = adminBar ? adminBar.offsetHeight : 0;
                teamBar.style.top = offset + 'px';
                var totalOffset = offset + teamBar.offsetHeight + (testBar ? testBar.offsetHeight : 0);
                if (wrapper) {
                    wrapper.style.paddingTop = totalOffset + 'px';
                }
            });
        </script>
        ";
    }
    // ================================================================================================
    // IMMER ERREICHBARER MINI-LOGIN-LINK (oben rechts) - auf ausdrücklichen Wunsch, siehe Chat: bisher
    // gab es #login nur über einen längst auskommentierten Footer-Link, dadurch war die Login-Seite
    // ohne die genaue URL faktisch nicht mehr erreichbar - z.B. für Teams, die sich nach Turnierende
    // noch einmal einloggen wollen (Zertifikat, eigene Daten). Bewusst NUR sichtbar, wenn niemand
    // eingeloggt ist - sobald ein Login aktiv ist, übernehmen admin-bar/team-bar (samt Logout) exakt
    // diese Ecke des Bildschirms bereits.
    // ================================================================================================
    if ($rollenInfo === null && !$teamEingeloggt) {
        $quickLoginHref = (($test_turnier_id == 0) ? '' : "?test_turnier_id=$test_turnier_id") . '#login';
        echo "
        <style>
            #quick-login-link { position: fixed; top: 10px; right: 10px; z-index: 9998; padding: 0.3rem 0.9rem; border-radius: 999px; background: rgba(20, 20, 30, 0.72); border: 1px solid rgba(255,255,255,0.28); color: #fff; font-size: 0.78rem; font-weight: 600; text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
            #quick-login-link:hover { background: rgba(20, 20, 30, 0.92); border-color: rgba(255,255,255,0.5); }
        </style>
        <a href='" . htmlspecialchars($quickLoginHref, ENT_QUOTES, 'UTF-8') . "' id='quick-login-link'>Login</a>
        ";
    }
    if (isset($_SESSION['flash_error_team_login']) && $_SESSION['flash_error_team_login']) {
        // SICHERHEIT: bewusst kein htmlspecialchars() mehr auf die Gesamtnachricht (wie beim Account-
        // Login-Pendant) - eine der möglichen Nachrichten enthält jetzt einen festen "Zu vergangenen
        // Turnieren"-Link (siehe getTeamLoginInfoAusVergangenemTurnier() weiter oben), alle Bausteine
        // dieser Nachrichten sind entweder feste Zeichenketten oder bereits separat escaped.
        echo "<div style='max-width:640px;margin:1rem auto 0;padding:0.7rem 1rem;border-radius:8px;background:rgba(192,57,43,0.15);border:1px solid #c0392b;color:#ffeaea;text-align:center;font-size:0.9rem;'>" . $_SESSION['flash_error_team_login'] . "</div>";
        unset($_SESSION['flash_error_team_login']);
    }
    // ================================================================================================
    // ACCOUNT-LOGIN-FEHLERMELDUNGEN (Session-Flash) - AUSSERHALB jedes <article>, weil es inzwischen
    // ZWEI Account-Login-Formulare gibt (#login und #backstage, siehe Chat) und diese Meldungen sonst
    // nur auf dem Formular gezeigt würden, das im HTML-Quelltext zuerst steht (unset() nach der ersten
    // Anzeige, das zweite Formular hätte danach nichts mehr zum Anzeigen). So läuft die Anzeige egal auf
    // welcher der beiden Seiten sichtbar (gleiches Prinzip wie flash_error_team_login direkt darüber).
    // ================================================================================================
    if (isset($_SESSION['flash_error_login_captcha']) && $_SESSION['flash_error_login_captcha']) {
        $loginCbOk = (stripos($_SESSION['flash_error_login_captcha'], 'best') !== false);
        echo '<div style="max-width:640px;margin:1rem auto 0;padding:10px;border:1px solid '. ($loginCbOk ? '#27ae60' : '#c0392b') .';border-radius:6px;background:'. ($loginCbOk ? '#ecf9f0' : '#ffeaea') .';color:'. ($loginCbOk ? '#27ae60' : '#c0392b') .';text-align:center;font-size:0.9rem;">';
        echo htmlspecialchars($_SESSION['flash_error_login_captcha'], ENT_QUOTES, 'UTF-8');
        echo '</div>';
        unset($_SESSION['flash_error_login_captcha']);
    }
    if (isset($_SESSION['flash_error_login']) && $_SESSION['flash_error_login']) {
        // SICHERHEIT: bewusst kein htmlspecialchars() hier - die Meldung ist eine feste, vom Server
        // selbst gesetzte Zeichenkette (siehe oben, "Diesen Account gibt es nicht...") ohne jede
        // Nutzereingabe darin, kann also gefahrlos den Registrieren-Link als echtes <a> enthalten.
        echo '<div style="max-width:640px;margin:1rem auto 0;padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;text-align:center;font-size:0.9rem;">';
        echo $_SESSION['flash_error_login'];
        echo '</div>';
        unset($_SESSION['flash_error_login']);
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
            // Instagram-Verlinkung fest im Code statt als Teil des CMS/Anzeige-Untertitels - auf
            // Wunsch groesser und im ueblichen Instagram-Look (Kamera-Icon + Handle, Verlaufs-
            // Farbverlauf als Pill-Badge) statt kleiner, reiner Textzeile.
            echo"<a href='https://www.instagram.com/blankiball_official/?hl=de' target='_blank' rel='noopener' class='hero-instagram-link'>
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'><rect x='3' y='3' width='18' height='18' rx='5'/><circle cx='12' cy='12' r='4'/><circle cx='17.2' cy='6.8' r='0.6' fill='currentColor' stroke='none'/></svg>
                    <span>&#64;blankiball_official</span>
                </a>";
            ?>
        </div>
    </div>
    <!--<button onclick="insert_traffic($conn, 1, 'anonym', 1 , ' hat sich die Regeln angesehen')"> Click2 </button>-->
    <!-- Icons bewusst als schlichte, einfarbige Inline-SVGs statt der frueheren bunten Emojis -
         passt sich per currentColor der Textfarbe an und fuegt sich damit ins ansonsten monochrome
         Grundtheme der Website ein. -->
    <?php
    //Aktuelle Turnierphase herausfinden - erstmal ID (wird sowohl vom Spielplan-Nav-Button weiter
    // unten als auch von der Statusbox direkt darunter gebraucht)
        $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
        $resultTurnier = $conn->query($sqlTurnier);
        while ($rowTurnier = $resultTurnier->fetch_assoc()) {
            $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
            $schnee = $rowTurnier['schnee'];
        }
    // Statusbox nur für eingeloggte Teams: wo im Turnier stehen wir gerade? (siehe
    // getTeamStatusInfo()/printTeamStatusBox() in table_print_functions.php). Auf ausdrücklichen
    // Wunsch über den 6 Nav-Buttons auf der Startseite statt (wie vorher) darunter - Teams sollen den
    // Status als Erstes sehen, ohne erst an der Nav vorbeischauen zu müssen.
    if ($teamEingeloggt) {
        printTeamStatusBox($conn, $TurnierID, (int)$teamLoginInfo['id'], $turnier_phase_ID);
    }
    ?>
    <nav>
        <ul>
            <li><a href="#info" class="nav-btn--info">
                <span class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.6" r="0.6" fill="currentColor" stroke="none"/></svg></span>
                <span>Info</span>
            </a></li>
            <li><a href="#regeln" class="nav-btn--regeln" onclick="insert_traffic($conn, $websiteId, 'anonym', 1 , ' hat sich die Regeln angesehen');">
                <span class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5C4 4.67 4.67 4 5.5 4H12v16H5.5A1.5 1.5 0 0 1 4 18.5v-13z"/><path d="M20 5.5c0-.83-.67-1.5-1.5-1.5H12v16h6.5c.83 0 1.5-.67 1.5-1.5v-13z"/></svg></span>
                <span>Regeln</span>
            </a></li>
            <li><a href='#teams' class='nav-btn--teams'>
                <span class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.3 14.3c2.6.4 4.7 2.5 5.2 5.2"/></svg></span>
                <span>Teams</span>
            </a></li>
            <?php
                //SPIELPLAN
                $spielplanIstAktiv = ($turnier_phase_ID == 4 || $turnier_phase_ID == 5 || $turnier_phase_ID == 7 || $turnier_phase_ID == 9 || $turnier_phase_ID == 11 || $turnier_phase_ID == 13);
                $spielplanKlasse = $spielplanIstAktiv ? " class='nav-btn--spielplan'" : " class='nav-btn--spielplan disabled'";
                echo "<li><a href='#spielplan'$spielplanKlasse>
                    <span class='nav-icon'><svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'><rect x='3.5' y='5' width='17' height='15' rx='2'/><line x1='3.5' y1='9.5' x2='20.5' y2='9.5'/><line x1='7.5' y1='3' x2='7.5' y2='6.5'/><line x1='16.5' y1='3' x2='16.5' y2='6.5'/></svg></span>
                    <span>Spielplan</span>
                </a></li>";
            ?>
            <li><a href="#spenden" class="nav-btn--spenden">
                <span class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20.5S3.5 15.4 3.5 9.2C3.5 6.3 5.8 4 8.6 4c1.5 0 2.9.7 3.4 2 .5-1.3 1.9-2 3.4-2 2.8 0 5.1 2.3 5.1 5.2 0 6.2-8.5 11.3-8.5 11.3z"/></svg></span>
                <span>Spenden</span>
            </a></li>
            <li><a href="#pausenraum" class="nav-btn--pausenraum">
                <span class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h12v7a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8z"/><path d="M16 9h2a2.5 2.5 0 0 1 0 5h-2"/><line x1="8" y1="4" x2="8" y2="6"/><line x1="12" y1="3" x2="12" y2="6"/></svg></span>
                <span>Pausenraum</span>
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
            <?php // Special-Regeln-Button für den Adventscup: nur sichtbar, wenn der Schnee-Effekt für
            // dieses Turnier aktiviert ist (Turnier-Settings, siehe $schnee weiter oben). Eigener,
            // fest im Code stehender Button direkt unter "Team anmelden" - der bestehende, CMS-
            // verwaltete Button ("Special-Regeln für Adventscup" unten im Footer) bleibt unangetastet
            // an seiner Stelle stehen, dieser hier kommt nur zusätzlich dazu.
            if ($schnee == 1) { ?>
                <a href='#adventscup-special' class='button primary'>&#10052; Special-Regeln für den Adventscup</a>
            <?php } ?>
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
    <?php printStyleTagHilfe(); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- ADD CONTENT -->
<article id="addcontent">
    <h2>Content hinzufügen</h2>
    <p></p>
    <?php if (isset($_POST['contentID'])) { addContent($_POST['contentID'], $TurnierID, $bn, $pw); } ?>
    <?php printStyleTagHilfe(); ?>
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

        // Vorher CMS-Section 2 (Überschrift + "Gruppen"-Button + Funktion printTeams()) - auf
        // ausdrücklichen Wunsch fest im Code statt im CMS, 1:1 nachgebaut wie es vorher aussah.
        echo "<h2>Unsere glorreichen Teams</h2>";
        echo "<a href='#gruppen' class='button primary'>&#128101; Gruppen</a>";
        // BUGFIX: <li> ohne umschließendes <ul> rendert mit Browser-Standard-Bullet UND der von
        // printTeams() manuell davorgeschriebenen Nummer "1. " - sah dadurch doppelt nummeriert
        // aus. ul.alt (gleiches Muster wie bei printSchiedsrichterInnen()) entfernt die Bullets und
        // zeigt stattdessen dünne Trennlinien zwischen den Einträgen.
        echo "<ul class='alt'>";
        printTeams($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus);
        echo "</ul>";
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
<!-- SPIELER INFO -->
<article id="spielerinfo">
    <!--<a href="#teams" class="button">Zurück zu den Teams</a></br></br>-->
    <?php
    // SICHERHEIT: (int)-Cast schliesst SQL-Injection - printSpielerInfo() baut daraus weiter unten
    // einen rohen, nicht vorbereiteten SQL-String.
    // Kein eigenes Login-Formular mehr hier - $bn/$pw kommen aus dem normalen, oben schon
    // aufgeloesten Session-Login (Admin/Co-Admin/Turniermaster/Backstage-Zugang), siehe Chat.
    $spielerId = isset($_GET['spielerId']) ? (int)$_GET['spielerId'] : null;
    printSpielerInfo($TurnierID, $conn, $spielerId, $bn, $pw);
    ?>
    <!--</br></br><a href="#teams" class="button">Zurück zu den Teams</a>-->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>
<!-- TEAM INFO -->                       
<article id="teaminfo">
    <!--<a href="#" class="button">Zurück zur Startseite</a></br></br>-->
    <?php 
    // SICHERHEIT: (int)-Cast schliesst SQL-Injection - printTeamInfo() baut daraus rohe SQL-Strings,
    // und diese Seite ist oeffentlich ohne jeden Login erreichbar.
    $teamId = isset($_GET['teamId']) ? (int)$_GET['teamId'] : null;
    printTeamInfo($TurnierID, $conn, $teamId, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null, $rechteFlags['backstage']);
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
            ';
            // Special-Regeln-Button für den Adventscup: nur bei aktiviertem Schnee-Effekt, direkt
            // über den drei Phase-Karten - siehe gleiche Logik/Kommentar wie beim "Team anmelden"-
            // Button oben im Header.
            if ($schnee == 1) {
                echo "<a href='#adventscup-special' class='button primary'>&#10052; Special-Regeln für den Adventscup</a><br/><br/>";
            }
            echo '
            <!-- Alte Bilder ausgeblendet: ersetzen wir durch kompakte Karten mit Icons
            <img src="images/Sonstiges/Gruppenhase.jpg" alt="Gruppenphase Bild"  style="width:30%;"/>
            <img src="images/Sonstiges/KO.jpg" alt="KO-Phase Bild" style="width:30%;"/>
            -->
            ';
            printSpielplanPhaseKarten($conn, $TurnierID, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null, $turnier_phase_ID);
            echo '
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
    <div class="ko-phase-cta ko-phase-cta--single">
        <a href="#punktetabelle" class="ko-phase-btn ko-phase-btn--points">
            <span class="ko-btn-label">Punktetabelle</span>
            <span class="ko-btn-sub">Die Punkte der Gruppenspiele - sie entscheiden, wer weiterrückt.</span>
        </a>
    </div>
    <?php  printSpielplanGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id, $rechteFlags['alle_spiele'], $bn, $pw, $darfZufaelligeSpieleEintragen, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null); ?>
    <!--<a href="#spielplan" class="button">Zurück zur übersicht</a>  -->
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>                          
</article>
<!-- Punktetabelle -->
<article id="punktetabelle">
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 12, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID überGEBEN (F�r CMS) #####-->                      
    <!--<a href="#gruppenphase" class="button">Zurück zum Spielplan</a>-->
    <h1>Punktetabelle der Gruppenphase</h1>
    <?php
    // Hinweis zum K.-o.-Einzug-Modus: nur relevant/sichtbar, wenn die Startpositionen der K.-o.-Phase
    // automatisch aus den Gruppenplatzierungen berechnet werden (einzug_ko_manuell_anlegen = 0) - legt
    // die Orga das manuell fest, würde ein Hinweis auf einen "Modus" nur verwirren.
    $sqlKoModusInfo = 'SELECT m.einzug_ko_manuell_anlegen, km.name, km.beschreibung FROM Turnier_Main m LEFT JOIN Turnier_KO_Einzug_Modus km ON km.id = m.fk_ko_einzug_modus WHERE m.id = ' . $TurnierID;
    $resultKoModusInfo = $conn->query($sqlKoModusInfo);
    $rowKoModusInfo = $resultKoModusInfo ? $resultKoModusInfo->fetch_assoc() : null;
    if ($rowKoModusInfo && (int)$rowKoModusInfo['einzug_ko_manuell_anlegen'] === 0 && !empty($rowKoModusInfo['name'])) {
        $koModusNameSafe = htmlspecialchars($rowKoModusInfo['name'], ENT_QUOTES, 'UTF-8');
        $koModusBeschreibungSafe = nl2br(htmlspecialchars($rowKoModusInfo['beschreibung'] ?? '', ENT_QUOTES, 'UTF-8'));
        echo "
        <details class='details-hint ko-einzug-modus-hint'>
            <summary>Der Einzug in die K.-o.-Phase wird automatisch berechnet - Modus: <b>$koModusNameSafe</b></summary>
            <p>$koModusBeschreibungSafe</p>
        </details>
        ";
    }
    ?>
    <?php printPunktetabelleGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>  
</article>
<!-- KO-Phase -->
<article id="kophase">
    <h2>KnockOut-Phase</h2>
    <div class="ko-phase-cta">
        <a href="#rangliste" class="ko-phase-btn ko-phase-btn--rank">
            <span class="ko-btn-label">Rangliste</span>
            <span class="ko-btn-sub">Die aktuelle Platzierung aller Teams.</span>
        </a>
    </div>
    <?php
    // RECHTE-AUDIT: reine Anzeige-Präferenz (siehe $koAnsicht oben), keine Rechteprüfung nötig - beide
    // Ansichten zeigen exakt dieselben Begegnungen mit denselben Rechten (printKO_PhaseTabellen und
    // printTurnierbaum teilen sich dieselben Bausteine, siehe table_print_functions.php).
    $koAnsichtQueryBasis = "?test_turnier_id=$test_turnier_id";
    ?>
    <div class="ko-ansicht-umschalter">
        <a href="<?php echo $koAnsichtQueryBasis; ?>&ko_ansicht=baum#kophase" class="ko-ansicht-btn<?php echo ($koAnsicht === 'baum') ? ' ko-ansicht-btn--aktiv' : ''; ?>">&#127942; Turnierbaum</a>
        <a href="<?php echo $koAnsichtQueryBasis; ?>&ko_ansicht=tabelle#kophase" class="ko-ansicht-btn<?php echo ($koAnsicht === 'tabelle') ? ' ko-ansicht-btn--aktiv' : ''; ?>">&#128203; Tabelle</a>
    </div>
    <?php //cmsPrintSection( $websiteId, $siteID, $TurnierID, 13, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id); ?>
    <?php if ($koAnsicht === 'tabelle') { ?>
        <?php printKO_PhaseTabellen($TurnierID, $conn, $LoggedInWithBackstageOrHigher, $gameEditMode, $expertenmodus, $test_turnier_id, $rechteFlags['turnier_settings'], $bn, $pw, $darfZufaelligeSpieleEintragen, $rechteFlags['alle_spiele'], $teamEingeloggt ? (int)$teamLoginInfo['id'] : null, $rechteFlags['teams']); ?>
    <?php } else { ?>
        <?php printTurnierbaum($TurnierID, $conn, $LoggedInWithBackstageOrHigher, $gameEditMode, $expertenmodus, $test_turnier_id, $bn, $pw, $darfZufaelligeSpieleEintragen, $rechteFlags['alle_spiele'], $teamEingeloggt ? (int)$teamLoginInfo['id'] : null, $rechteFlags['teams'], $rechteFlags['turnier_settings']); ?>
    <?php } ?>
    <!--<a href="#spielplan" class="button">Zurück zur übersicht</a>-->
    <p></br></p>
    <p></br></p>
<!-- Losing Bracket -->
</article>
<article id="losingbracket">
    <h1 class="section-header">Losing-Bracket <img src="images/icon/sterni2.png" width="32" height="32" alt="Icon"></h1>
    <p class="muted">Teams, die aus der KO-Phase ausgeschieden sind, spielen hier weitere Partien um bessere Platzierungen.</p>
    <?php 
    //cmsPrintSection($websiteId, $siteID, $TurnierID, 35, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);
    // Direkte Ausgabe: nur Losing-Bracket-Gruppe, aber mit den gleichen Tabellen wie Gruppenphase
    include_once 'website_print_functions/table_print_functions.php';
    printSpielplanLosingBracket($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id, $darfZufaelligeSpieleEintragen, $rechteFlags['alle_spiele'], $teamEingeloggt ? (int)$teamLoginInfo['id'] : null);
    printPunktetabelleLosingBracket($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null);
    ?>
    <!--<a href="#spielplan" class="button">Zurück zur Übersicht</a>-->
    <div class="ko-phase-cta">
        <a href="#rangliste" class="ko-phase-btn ko-phase-btn--rank">
            <span class="ko-btn-label">Rangliste</span>
            <span class="ko-btn-sub">Die aktuelle Platzierung im Losing-Bracket.</span>
        </a>
    </div>
    <p></br></p> <!-- Abst??nde unten damit Button auf Handys nicht von Cookiewarnung Oberdeckt wird -->
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
    <!-- Auf ausdrücklichen Wunsch nicht mehr über das CMS (Section 15), sondern fest hier eingebaut
         und im Karten-Stil der Spielplan-Übersicht (.phase-cards) neu designt statt der vorherigen
         gestapelten Buttons mit <br/><br/> dazwischen. -->
    <h1 class="section-header">Info</h1>
    <p class="muted">Hier erhältst du einige Infos über den Turnierablauf, die Geschichte des Turniers und News!</p>
    <div class="phase-cards">
        <div class="phase-card phase-card--gruppen">
            <h3><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.6" r="0.6" fill="currentColor" stroke="none"/></svg> Über Blankiball</h3>
            <p class="muted">Was Blankiball ist und wie das Turnier abläuft.</p>
            <a href="#allgemeine_info" class="button primary">Mehr erfahren</a>
        </div>
        <div class="phase-card phase-card--ko">
            <h3><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 8.5A1.5 1.5 0 0 1 5 7h2.2l1.1-1.6h7.4L16.8 7H19a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 19 18H5a1.5 1.5 0 0 1-1.5-1.5v-8z"/><circle cx="12" cy="12.5" r="3.3"/></svg> Fotos vom Turnier</h3>
            <p class="muted">Die Galerie mit Bildern von bisherigen Turnieren.</p>
            <a href="#galerie" class="button primary">Zur Galerie</a>
        </div>
        <div class="phase-card phase-card--losing">
            <h3><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9a8 8 0 1 1 1.3 7.2"/><path d="M4 4v4.2h4.2"/><path d="M12 8v4.5l3 2"/></svg> Vergangene Turniere</h3>
            <p class="muted">Ein Blick zurück auf frühere Ausgaben von Blankiball.</p>
            <a href="#history" class="button primary">Zur Geschichte</a>
        </div>
        <div class="phase-card phase-card--faq">
            <h3><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.2 9.3a2.8 2.8 0 1 1 4.1 2.5c-.9.5-1.3 1-1.3 2.1"/><circle cx="12" cy="17.2" r="0.6" fill="currentColor" stroke="none"/></svg> FAQ</h3>
            <p class="muted">Antworten auf häufig gestellte Fragen.</p>
            <a href="#faq" class="button primary">Zu den FAQ</a>
        </div>
    </div>
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
<!-- Auf ausdrücklichen Wunsch nicht mehr über das CMS (Section 22), sondern fest hier eingebaut und
     neu designt - siehe print_sieger_innen_treppe() in table_print_functions.php. Die alte CMS-
     Function-Zuordnung (fk_function -> "trigger_sieger_innen_treppe") bleibt in der DB einfach
     ungenutzt liegen, wird aber nirgends mehr aufgerufen. -->
<article id="sieger_innen_treppe">
    <?php trigger_sieger_innen_treppe($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- RANGLISTE: war bisher reiner CMS-Textplatzhalter (Section 23), zeigte also nie eine echte
     Platzierung - print_platzierungen() gab es im Code schon, wurde aber nirgends aufgerufen. Auf
     ausdrücklichen Wunsch jetzt direkt hier eingebunden, CMS-Bindung entfernt. -->
<article id="rangliste">
    <h1 class="section-header">&#127942; Rangliste</h1>
    <p class="muted">Die Endplatzierung aller Teams - wird laufend aktualisiert, sobald Teams ausscheiden bzw. ihre Platzierung feststeht.</p>
    <?php print_platzierungen($TurnierID, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $teamEingeloggt ? (int)$teamLoginInfo['id'] : null); ?>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
</article>

<!-- BULLEREI KOMMT: auf ausdrücklichen Wunsch nicht mehr öffentlich im Footer, sondern nur noch für
     Admin/Co-Admin/Turniermaster im Backstage-Bereich (teams-Flag) - vorher konnte JEDE(R)
     Website-Besucher*in dieses Formular öffnen. -->
<article id='backstage_bullerei_kommt'>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
    <div style='text-align: center'>
        <?php if (!$rechteFlags['teams']) { ?>
        <p>Keine ausreichende Berechtigung. Das dürfen nur Admin, Co-Admin und Turniermaster.</p>
        <?php } else { ?>
        <?php printBullereiKommt($conn, $websiteId, $TurnierID, $bn, $pw) ?>
        <h5><br /></h5>
        <?php } ?>
    </div>
</article>


<!-- SPENDEN -->
<article id="spenden">
    <h1 class="section-header">&#128155; Spenden</h1>
    <p class="muted">Blankiball lebt davon, dass sich Leute in ihrer Freizeit unbezahlt darum kümmern - trotzdem entstehen ein paar Kosten, für die wir uns über jede Unterstützung freuen. Deine Spende hilft uns zum Beispiel bei:</p>
    <ul class="alt">
        <li>&#127866; Getränken vor Ort</li>
        <li>&#127942; Preisen für die Sieger*innen</li>
        <li>&#127760; der Website (Hosting, Domain, ...)</li>
        <li>&#128085; Vorschuss für Merch</li>
        <li>&#128176; laufenden Kosten rund ums Turnier</li>
        <li>&#127909; Interviewtechnik (Kamera, Mikros, ...)</li>
    </ul>
    <div class="cta-banner" style="border-color: rgba(255,107,107,0.35); background: linear-gradient(180deg, rgba(255,107,107,0.14), rgba(255,107,107,0.03));">
        <div class="title">Jeder Beitrag hilft &mdash; auch wenn's nur ein paar Euro sind. Vielen Dank!</div>
        <a href="https://www.paypal.com/paypalme/blankiball?country.x=DE&locale.x=de_DE" class="button primary">&#128184; Jetzt spenden</a>
    </div>
    <p></br></p> <!-- Abst�nde unten damit Button auf Handys nicht von Cookiewarnung �berdeckt wird -->
    <p></br></p>
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


<!-- LOGIN - reine Login-Seite, Ziel des "Login"-Buttons oben rechts (siehe Chat: soll bewusst NICHT
     dieselbe Seite wie "Backstage" unten sein, siehe #backstage direkt darunter). Bietet sowohl
     Team- als auch Account-Login an; beide laufen über denselben, bereits weiter oben vorhandenen
     Session-Mechanismus (POST-Feldnamen team_login_kuerzel/team_login_passwort bzw. bn/pw) - hier
     stehen nur die Formulare dafür, keinerlei eigene Login-Logik. Bewusst KEINE Registrierung hier,
     die bleibt exklusiv auf #backstage. -->
<article id="login">
    <style>
        .login-section { margin-bottom: 1.6rem; }
        .login-form-row { margin-bottom: 0.6rem; }
    </style>
    <h1>Login</h1>
    <p>Logge dich mit deinem <b>Team-Kürzel und Team-Passwort</b> ein. Falls du stattdessen einen <b>Account</b> hast (z.B. als Admin, Co-Admin, Turniermaster, Backstage-Zugang oder Schiedsrichter*in), kannst du dich weiter unten damit einloggen - beides funktioniert auf dieser Seite.</p>

    <div class='login-section'>
        <h2>Team-Login</h2>
        <?php
        if ($test_turnier_id == 0) {
            echo "<form method='post' action='#login'>";
        } else {
            echo "<form method='post' action='?test_turnier_id=$test_turnier_id#login'>";
        }
        ?>
            <div class='login-form-row'>
                <input type="text" name="team_login_kuerzel" class="Eingabe" placeholder="Team-Kürzel" style="color: white" required autocomplete="username">
                <input type="password" name="team_login_passwort" class="Eingabe" placeholder="Team-Passwort" style="color: white" required autocomplete="current-password">
            </div>
            <button type="submit" class="button primary">Einloggen</button>
        </form>
    </div>

    <div class='login-section' id="LogInStandalone">
        <h2>Account-Login</h2>
        <?php
        if($test_turnier_id==0){
            echo "<form action='/' method='POST'>";
        }else{
            echo "<form action='/?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        ?>
        <div class='login-form-row'>
            <input type="hidden" name="login_submit" value="1">
            <input type="hidden" name="cb_return_hash" value="login">
            <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required autocomplete="username">
            <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required autocomplete="current-password">
        </div>
        <?php
        $loginSubmitDisabled = '';
        if ($loginBenoetigtCaptcha) {
            require_once __DIR__ . '/website_functionalities/captcha_blanki.php';
            CaptchaBlanki::render('login');
            $loginSubmitDisabled = CaptchaBlanki::passed('login') ? '' : ' disabled';
        }
        ?>
        <button value="Anmelden" type="submit"<?php echo $loginSubmitDisabled; ?>>Anmelden</button>
        </form>
    </div>
    <p></br></p>
    <p></br></p>
</article>

<!-- BACKSTAGE - Testmodus, Account-Login (nochmal, siehe Chat), Account-Registrierung und
     Besucherzahl - Ziel des "Backstage"-Links im Footer. Ehemals #login; auf ausdrücklichen Wunsch
     umbenannt/aufgeteilt, damit der "Login"-Button oben rechts auf eine eigene, schlankere Seite ohne
     Testmodus/Registrierung/Besucherzahl führen kann (siehe #login direkt darüber). -->
<article id="backstage">
    <!-- ================================================================================================
         BACKSTAGE-EINSTIEGSSEITE - KOMPAKT, ABER MIT LUFT ZWISCHEN DEN VIER BEREICHEN
         (Testmodus / Login / Registrieren / Anzahl Websitebesuche)
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
        // RATE-LIMITING: nach mehreren Fehlversuchen (siehe $loginBenoetigtCaptcha weiter oben) muss
        // hier erst ein Bild-Captcha bestätigt werden, bevor der Absenden-Button nutzbar wird.
        // Die flash_error_login/flash_error_login_captcha-Meldungen selbst werden inzwischen weiter
        // oben AUSSERHALB jedes <article> angezeigt (siehe Kommentar dort) - es gibt jetzt zwei
        // Account-Login-Formulare (hier und auf #login), eine Anzeige pro Formular hätte die Meldung
        // nur auf einem der beiden gezeigt.
        // UX (siehe Chat): bewusst KEIN "zurück zur vorherigen Sektion"-Mechanismus für den Account-
        // Login - anders als beim Team-Login soll man nach dem Login als Account/Admin/Co-Admin auf der
        // Startseite landen (war zwischenzeitlich testweise auch hier eingebaut, auf ausdrücklichen
        // Wunsch wieder entfernt - das Team-Login-Formular in printEditModeStuff() bleibt wie gehabt
        // hash-erhaltend, weil dort die Action-URL den Hash direkt enthält).
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='/' method='POST'>";
        }else{ //Testturniere
            echo "<form action='/?test_turnier_id=$test_turnier_id' method='POST'>";
        }
        ?>
        <div class='login-form-row'>
            <!-- login_submit: eindeutiger Marker NUR für dieses Formular - viele andere Formulare auf
                 der Website schicken bn/pw ebenfalls mit (als Zugangsdaten der handelnden Person, z.B.
                 der CMS-Umschalter), damit dort aber nicht versehentlich der Redirect weiter unten
                 ausgelöst wird, muss der explizit an DIESES Feld gekoppelt sein. -->
            <input type="hidden" name="login_submit" value="1">
            <input type="hidden" name="cb_return_hash" value="backstage">
            <input type="text" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
            <input type="password" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
        </div>
        <?php
        $loginSubmitDisabled = '';
        if ($loginBenoetigtCaptcha) {
            require_once __DIR__ . '/website_functionalities/captcha_blanki.php';
            CaptchaBlanki::render('login');
            $loginSubmitDisabled = CaptchaBlanki::passed('login') ? '' : ' disabled';
        }
        ?>
        <!--<input type="submit" value="Absenden" style="color: black"/> -->
        <button value="Anmelden" type="submit"<?php echo $loginSubmitDisabled; ?>>Anmelden</button>
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
<!-- ###  EIGENES PROFIL - Ziel des Avatar/Namen-Klicks in der Admin-Leiste. Zeigt die eigenen     ### -->
<!-- ###  Rollen samt Erklaerung (getRollenErklaerungen(), siehe rollen_definitionen.php) und       ### -->
<!-- ###  erlaubt das Aendern des eigenen Benutzernamens/Passworts (Eigenes_Profil_Speichern in     ### -->
<!-- ###  edit_account.php) - unabhaengig von der Rolle, jeder eingeloggte Account darf das.        ### -->
<!-- ################################################################################################ -->
<article id="account_profil">
    <style>
        .profil-header { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.2rem; }
        .profil-avatar { flex-shrink: 0; width: 3rem; height: 3rem; border-radius: 50%; background: var(--admin-accent-deep); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; }
        .profil-rolle-karte { text-align: left; max-width: 640px; margin: 0 auto 0.8rem; padding: 0.7rem 1rem; border-radius: 8px; background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.25); }
        .profil-rolle-karte h3 { margin: 0 0 0.4rem; font-size: 0.95rem; }
        .profil-rolle-karte p, .profil-rolle-karte ul { font-size: 0.82rem; margin: 0; }
        /* Avatar-Auswahlraster: Emoji statt Bilder-Upload (siehe login_interface.php) - Klick markiert
           die Auswahl nur visuell und schreibt sie in ein verstecktes Feld, echt gespeichert wird sie
           erst zusammen mit dem Rest des Formulars über den "Speichern"-Button. */
        .profil-avatar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(2.4rem, 1fr)); gap: 0.4rem; margin-bottom: 0.6rem; }
        .profil-avatar-opt { font-size: 1.3rem; line-height: 1; padding: 0.35rem; border-radius: 8px; border: 2px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); cursor: pointer; }
        .profil-avatar-opt:hover { border-color: rgba(255,255,255,0.4); }
        .profil-avatar-opt--aktiv { border-color: var(--admin-accent); background: rgba(139,92,246,0.28); }
    </style>
    <?php if ($rollenInfo === null) { ?>
        <h1>Profil</h1>
        <p>Du bist aktuell nicht eingeloggt.</p>
        <a href='#login' class='button primary'>Einloggen</a>
    <?php } else {
        $profilAvatarAktuell = ermittleAnzeigeAvatar($conn, $rollenInfo['benutzer_id']);
        $profilAvatarSafe = htmlspecialchars($profilAvatarAktuell, ENT_QUOTES, 'UTF-8');
        $profilBnSafe = htmlspecialchars((string)$bn, ENT_QUOTES, 'UTF-8');
        $profilBnAttr = htmlspecialchars((string)$bn, ENT_QUOTES);
        $profilPwAttr = htmlspecialchars((string)$pw, ENT_QUOTES);
    ?>
    <div class='profil-header'>
        <span class='profil-avatar'><?php echo $profilAvatarSafe; ?></span>
        <h1><?php echo $profilBnSafe; ?></h1>
    </div>

    <?php if (isset($_SESSION['flash_error_profil']) && $_SESSION['flash_error_profil']) { ?>
        <div style="max-width:640px;margin:0 auto 1rem;padding:10px;border:1px solid #c0392b;border-radius:6px;background:#ffeaea;color:#c0392b;">
            <?php echo htmlspecialchars($_SESSION['flash_error_profil'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error_profil']); ?>
        </div>
    <?php } ?>

    <h2>Deine Rollen &amp; Berechtigungen</h2>
    <?php if (count($rollenInfo['rolle_ids']) === 0) { ?>
        <p>Du hast aktuell <b>keine Rolle und damit keinerlei Rechte</b>. Melde dich bei einem Admin oder Co-Admin, damit er dich im Nutzermanagement freischaltet.</p>
    <?php } else {
        $profilRollenErklaerung = getRollenErklaerungen();
        foreach ($rollenInfo['rolle_ids'] as $rid) {
            $rname = htmlspecialchars($rollenInfo['rollen_namen'][$rid] ?? ('Rolle ' . $rid), ENT_QUOTES, 'UTF-8');
            $erklaerung = $profilRollenErklaerung[$rid] ?? 'Keine Beschreibung hinterlegt.';
            echo "<div class='profil-rolle-karte'><h3>$rname</h3><p>$erklaerung</p></div>";
        }
    } ?>

    <h2>Profil bearbeiten</h2>
    <div class='login-section' style='max-width:400px;margin:0 auto;'>
        <form action='website_datachange/edit_account.php<?php echo $test_turnier_id!=0 ? "?test_turnier_id=$test_turnier_id" : ""; ?>' method='POST' onsubmit="return confirm('Profil wirklich aktualisieren?');">
            <input type='hidden' name='action' value='Eigenes_Profil_Speichern'>
            <?php echo csrf_field(); ?>
            <input type='hidden' name='admin_bn' value='<?php echo $profilBnAttr; ?>'>
            <input type='hidden' name='admin_pw' value='<?php echo $profilPwAttr; ?>'>
            <div class='login-form-row' style='text-align:left;'>
                <label style='display:block;font-size:0.78rem;opacity:0.8;margin-bottom:0.4rem;'>Avatar</label>
                <div class='profil-avatar-grid'>
                    <?php foreach (getProfilAvatarOptionen() as $avatarOpt) {
                        $avatarOptSafe = htmlspecialchars($avatarOpt, ENT_QUOTES, 'UTF-8');
                        $aktivKlasse = ($avatarOpt === $profilAvatarAktuell) ? ' profil-avatar-opt--aktiv' : '';
                        echo "<button type='button' class='profil-avatar-opt$aktivKlasse' data-avatar='$avatarOptSafe' onclick='profilAvatarWaehlen(this)'>$avatarOptSafe</button>";
                    } ?>
                </div>
                <input type='hidden' name='neuer_avatar' id='profil_avatar_input' value='<?php echo $profilAvatarSafe; ?>'>
            </div>
            <div class='login-form-row' style='text-align:left;'>
                <label for='profil_bn' style='display:block;font-size:0.78rem;opacity:0.8;margin-bottom:0.2rem;'>Benutzername</label>
                <input type='text' id='profil_bn' name='neuer_benutzername' value='<?php echo $profilBnSafe; ?>' class='Eingabe' style='color:white;width:100%;' required>
            </div>
            <div class='login-form-row' style='text-align:left;'>
                <label for='profil_pw' style='display:block;font-size:0.78rem;opacity:0.8;margin-bottom:0.2rem;'>Neues Passwort <i>(leer lassen für keine Änderung)</i></label>
                <input type='password' id='profil_pw' name='neues_passwort' placeholder='Neues Passwort' class='Eingabe' style='color:white;width:100%;' autocomplete='new-password'>
            </div>
            <button type='submit' class='button primary'>Speichern</button>
        </form>
    </div>
    <script>
        function profilAvatarWaehlen(btn) {
            document.querySelectorAll('.profil-avatar-opt--aktiv').forEach(function(b) { b.classList.remove('profil-avatar-opt--aktiv'); });
            btn.classList.add('profil-avatar-opt--aktiv');
            document.getElementById('profil_avatar_input').value = btn.getAttribute('data-avatar');
        }
    </script>
    <?php } ?>
    <p></br></p>
    <p></br></p>
</article>

<!-- ################################################################################################ -->
<!-- ###  ACCOUNT REGISTRIEREN - eigenstaendige Selbstregistrierung, erreichbar ueber den          ### -->
<!-- ###  "Registrieren"-Button auf der #backstage-Seite. Neue Accounts bekommen bewusst NOCH KEINE ### -->
<!-- ###  Rolle (nicht mal "Benutzer*in") - ein Admin/Co-Admin muss sie im Nutzermanagement erst    ### -->
<!-- ###  freischalten. Bot-Schutz ueber dasselbe Blankensteinpark-Bild-Captcha wie bei der          ### -->
<!-- ###  Team-Anmeldung (CaptchaBlanki), aber mit eigenem formKey "user_register" statt "register", ### -->
<!-- ###  damit sich die beiden unabhaengigen Captcha-Ablaeufe nicht gegenseitig ueberschreiben.     ### -->
<!-- ################################################################################################ -->
<article id="register_account">
    <a href='#backstage' class='button'>Zurück</a>
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
        <?php echo csrf_field(); ?>
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
        // Zwei Stufen für den "normalen" Backstage-Bereich (siehe Farb-Legende weiter unten):
        // - turnier_settings-Flag (Bernstein, exklusiv Admin/Co-Admin): "Neues Turnier anlegen",
        //   "Turnier Settings", "Turnierphase". "Gruppeneinteilung losen" und "Nutzermanagement"/
        //   "Bullerei kommt" hängen aus historischen/Sicherheitsgründen direkt an $istAdminOderCoAdmin
        //   statt am Flag, sind aber audience-mäßig identisch (daher derselbe Bernstein-Rahmen).
        // - teams-Flag (Grün, Admin/Co-Admin/Turniermaster): "Teams bearbeiten"/"...einsortieren" UND
        //   (auf ausdrücklichen Wunsch, siehe Chat "Turniermaster soll alles können was Backstage kann,
        //   nur mit Schreiben dazu") die operativen Turnier-Funktionen "Gruppen für Gruppenphase
        //   generieren", "Einzug ins KO-System", "Green-Card-Begegnung erstellen", "Liste gesperrter
        //   Begegnungen" und "Bullerei kommt" - Turniermaster darf das jetzt genauso wie Admin/Co-Admin.
        // "Teams generieren" ist NUR im Testmodus sichtbar (dunkelblau statt violett) und braucht
        // bewusst nur das breitere backstage-Flag (auch Backstage-Zugang darf das) - siehe
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
            <?php if ($test_turnier_id != 0 && $rechteFlags['backstage']) { ?>
            <a href='#backstage_teams_generieren' class='admin-menu-button admin-menu-button-testmodus'><span class='amn-num amn-num-testmodus'><?php echo $amnZaehler++; ?></span> Teams generieren</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_teams_bearbeiten' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Teams bearbeiten</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_gruppen_generieren' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Gruppen für Gruppenphase generieren</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_teams_gruppen_einsortieren' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Teams in Gruppen einsortieren</a>
            <?php } ?>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_gruppeneinteilung_losen' class='admin-menu-button admin-menu-button--coadmin'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Gruppeneinteilung losen</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_ko_einzug_modus' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Einzug ins KO-System</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_greencard_begegnungen_erstellen' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Green-Card-Begegnung erstellen</a>
            <a href='#backstage_gesperrte_begegnungen' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Liste gesperrter Begegnungen</a>
            <?php } ?>
            <?php if ($istAdminOderCoAdmin) { ?>
            <a href='#backstage_nutzermanagement' class='admin-menu-button admin-menu-button--coadmin'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Nutzermanagement</a>
            <?php } ?>
            <?php if ($rechteFlags['teams']) { ?>
            <a href='#backstage_bullerei_kommt' class='admin-menu-button admin-menu-button--teams'><span class='amn-num'><?php echo $amnZaehler++; ?></span> Bullerei kommt</a>
            <?php } ?>
        </div>
        <?php if ($istAdminOderCoAdmin) { ?>
        <div class='admin-legende'>
            <h4>Farb-Legende</h4>
            <p style='font-size:0.75rem; opacity:0.8; margin:0 0 0.8rem; text-align:center;'>Die Rahmenfarbe zeigt, WER etwas überhaupt sehen kann - nicht, wer nur lesen vs. tatsächlich bearbeiten darf. Backstage-Zugang ist eine reine Lese-Rolle (sieht z.B. Team-Passwörter/Warteliste, kann aber nirgends etwas verändern), während Turniermaster bei den grün markierten Funktionen auch wirklich bearbeiten darf - inzwischen praktisch alles außer den bernstein-/rot-markierten Admin-Kernfunktionen.</p>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--testspiele'></span>
                <div>
                    <b>Türkiser Rahmen</b> (nur bei "Zufällige Spiele eintragen" in Gruppenphase/K.-o.-Phase/Losing Bracket, jeweils nur im Testmodus)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Backstage-Zugang, Schiedsrichter*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--backstage'></span>
                <div>
                    <b>Blauer Rahmen</b>: Telefonnummern, Team-Passwörter, Warteliste, ER-Diagramm (Infos-Menü); im Testmodus zusätzlich "Teams generieren" (eigene dunkelblaue Testmodus-Optik statt Rahmenfarbe)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--cms'></span>
                <div>
                    <b>Pinker Rahmen</b> (nur beim CMS-Button oben in der Admin-Leiste, nicht im Settings-/Infos-Menü)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Autor*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--teams'></span>
                <div>
                    <b>Grüner Rahmen</b> (auch beim Settings- und Infos-Button oben in der Admin-Leiste): Teams bearbeiten/einsortieren, Gruppen für Gruppenphase generieren, Einzug ins KO-System, Green-Card-Begegnungen erstellen/sperren, Liste gesperrter Begegnungen, Begegnungs-ID in der K.-o.-Phase, Bullerei kommt<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--coadmin'></span>
                <div>
                    <b>Bernsteinfarbener Rahmen</b>: Neues Turnier anlegen, Turnier Settings, Turnierphase, Gruppeneinteilung losen, Nutzermanagement<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--adminonly'></span>
                <div>
                    <b>Roter Rahmen</b> (Verlauf/Traffic/DB-Verlauf, Passwörter anderer Accounts einsehen/ändern)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Co-Admin, Autor*in, Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
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
<article id="backstage_greencard_begegnungen_erstellen">
    <h1>&#127942; Green-Card-Begegnung erstellen</h1>
    <?php // RECHTE-AUDIT: teams-Flag = Admin/Co-Admin/Turniermaster (siehe rollen_definitionen.php) -
    // Begegnungen sperren ist auf ausdrücklichen Wunsch in einen eigenen Button je Begegnung in der
    // K.-o.-Phase umgezogen (printKO_PhaseTabellen), diese Seite kann seitdem nur noch Green-Card-
    // Begegnungen anlegen. ?>
    <?php if (!$rechteFlags['teams']) { ?>
    <p>Keine ausreichende Berechtigung. Begegnungen anlegen erfordert die Teams-Berechtigung.</p>
    <?php } else { ?>
    <!-- ============================================================================================
         NEU DESIGNT: übersichtlicherer Kasten, Team-Auswahl nebeneinander, echte Buttons statt
         roher <input type=submit>, natives HTML5 "required" auf der Bestätigungs-Checkbox statt
         eines separaten JS-alert()-Checks (weniger Code, gleiche Wirkung: ohne Häkchen kein Absenden).
         ============================================================================================ -->
    <style>
        .bb-section { max-width: 32rem; margin: 0 auto; border: 1px solid rgba(139, 92, 246, 0.28); border-radius: 10px; padding: 1.1rem 1.3rem; text-align: left; background: rgba(139, 92, 246, 0.05); }
        .bb-section h2 { margin: 0 0 0.4rem 0; }
        .bb-section .field { margin-bottom: 0.7rem; }
        .bb-section label { display: block; margin-bottom: 0.25rem; font-size: 0.85rem; opacity: 0.9; }
        .bb-section select, .bb-section input[type='number'] { width: 100%; box-sizing: border-box; }
        .bb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }
        @media (max-width: 480px) { .bb-grid { grid-template-columns: 1fr; } }
        .bb-hint { font-size: 0.78rem; opacity: 0.7; margin: 0.3rem 0 0; }
        .bb-confirm { display: flex; align-items: center; gap: 0.5rem; margin: 1rem 0; padding: 0.6rem 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.05); font-size: 0.85rem; }
        .bb-confirm input { margin: 0; }
    </style>

    <div class='bb-section'>
        <h2 class='major'>Neue Begegnung</h2>
        <p>Legt eine neue Begegnung manuell an (z.B. Freundschaftsspiel oder Nachtrag). Sie bekommt automatisch den Status <b>"Green Card"</b> und wird dadurch von der automatischen Spielplan-Berechnung nie wieder überschrieben oder verworfen.</p>
        <form action='website_datachange/edit_games.php' method='POST'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo htmlspecialchars($bn, ENT_QUOTES); ?>'/>
            <input type='hidden' name='pw' value='<?php echo htmlspecialchars($pw, ENT_QUOTES); ?>'/>
            <input type='hidden' name='action' value='Begegnung_Hinzufuegen'/>
            <?php echo csrf_field(); ?>
            <div class='bb-grid'>
                <div class='field'>
                    <label>Team 1 (Heimteam)</label>
                    <select name='team1' class='Eingabe' required>
                        <option value=''>-</option>
                        <?php
                        $sqlTeamBegegnungHinzufuegen = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY name';
                        $resultTeamBegegnungHinzufuegen = $conn->query($sqlTeamBegegnungHinzufuegen);
                        while ($rowTeamBegegnungHinzufuegen = $resultTeamBegegnungHinzufuegen->fetch_assoc()) {
                            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Teamname/-kuerzel
                            $TeamName = htmlspecialchars($rowTeamBegegnungHinzufuegen['name'], ENT_QUOTES, 'UTF-8');
                            $TeamKuerzel = htmlspecialchars($rowTeamBegegnungHinzufuegen['kuerzel'], ENT_QUOTES, 'UTF-8');
                            $TeamId = (int)$rowTeamBegegnungHinzufuegen['id'];
                            echo "<option value=$TeamId>$TeamName ($TeamKuerzel)</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class='field'>
                    <label>Team 2 (Auswärtsteam)</label>
                    <select name='team2' class='Eingabe' required>
                        <option value=''>-</option>
                        <?php
                        $sqlTeamBegegnungHinzufuegen2 = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ' ORDER BY name';
                        $resultTeamBegegnungHinzufuegen2 = $conn->query($sqlTeamBegegnungHinzufuegen2);
                        while ($rowTeamBegegnungHinzufuegen2 = $resultTeamBegegnungHinzufuegen2->fetch_assoc()) {
                            // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Teamname/-kuerzel
                            $TeamName = htmlspecialchars($rowTeamBegegnungHinzufuegen2['name'], ENT_QUOTES, 'UTF-8');
                            $TeamKuerzel = htmlspecialchars($rowTeamBegegnungHinzufuegen2['kuerzel'], ENT_QUOTES, 'UTF-8');
                            $TeamId = (int)$rowTeamBegegnungHinzufuegen2['id'];
                            echo "<option value=$TeamId>$TeamName ($TeamKuerzel)</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class='field'>
                <label>Phase</label>
                <select name='ko_finallevel' class='Eingabe' required>
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
                <label>Bracket-Position <i>(nur bei K.-o.-Phase nötig, sonst leer lassen)</i></label>
                <input type='number' name='ko_turnierbaumposition' min='1' class='Eingabe' placeholder='z.B. 1'>
                <p class='bb-hint'>Bestimmt den Platz im Turnierbaum dieser K.-o.-Runde. Im Zweifel vorher bei der KO-Phase auf der Startseite nachsehen, welche Positionen in der gewählten Runde schon belegt sind.</p>
            </div>
            <label class='bb-confirm'>
                <input type='checkbox' required>
                <span>Ich habe geprüft, dass Teams und Bracket-Position stimmen.</span>
            </label>
            <button type='submit' class='button primary'>Begegnung anlegen</button>
        </form>
    </div>
    <?php } ?>
    <a href='#backstage_daten_bearbeiten' class='button'>Zurück</a>
    <h5><br /></h5>
</article>

<!-- ################################################################################################ -->
<!-- ###  LISTE GESPERRTER BEGEGNUNGEN  ############################################################# -->
<!-- ################################################################################################ -->
<article id="backstage_gesperrte_begegnungen">
    <h1>&#128274; Liste gesperrter Begegnungen</h1>
    <?php if (!$rechteFlags['teams']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <p class="muted">Alle Begegnungen dieses Turniers, die aktuell gesperrt sind (Red Card) - geschützt vor der automatischen Spielplan-Berechnung und für die Öffentlichkeit unsichtbar. Über "Entsperren" könnt ihr das jederzeit wieder aufheben.</p>
    <?php
    $hatSperrTrackingAnzeige = false;
    $stmtSchemaAnzeige = $conn->prepare("SELECT COUNT(*) AS anzahl FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Turnier_Begegnung' AND COLUMN_NAME IN ('gesperrt_von', 'gesperrt_am')");
    if ($stmtSchemaAnzeige) {
        $stmtSchemaAnzeige->execute();
        $schemaRowAnzeige = $stmtSchemaAnzeige->get_result()->fetch_assoc();
        $hatSperrTrackingAnzeige = ($schemaRowAnzeige && (int)$schemaRowAnzeige['anzahl'] === 2);
    }
    $sqlGesperrt = 'SELECT * FROM Turnier_Begegnung WHERE status = 6 AND fk_heimteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') AND fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ' . $TurnierID . ') ORDER BY id DESC';
    $resultGesperrt = $conn->query($sqlGesperrt);
    $anzahlGesperrt = 0;
    echo "<table class='withBorderCollapse'><thead><tr><th>#</th><th>Phase</th><th>Team A</th><th>Team B</th><th>Gesperrt von</th><th>Gesperrt am</th><th></th></tr></thead><tbody>";
    while ($resultGesperrt && ($rowGesperrt = $resultGesperrt->fetch_assoc())) {
        $anzahlGesperrt++;
        $ggId = (int)$rowGesperrt['id'];
        $ggLevel = (int)$rowGesperrt['ko_finallevel'];
        $ggLevelName = ($ggLevel === 0) ? 'Gruppenphase' : (($ggLevel === 20) ? 'Losing Bracket' : null);
        if ($ggLevelName === null) {
            $resLevelName = $conn->query('SELECT name FROM Turnier_KO_Finallevel WHERE id = ' . $ggLevel);
            $ggLevelName = ($resLevelName && ($lr = $resLevelName->fetch_assoc())) ? $lr['name'] : "Level $ggLevel";
        }
        $ggHeim = $conn->query('SELECT kuerzel FROM Turnier_Team WHERE id = ' . (int)$rowGesperrt['fk_heimteam'])->fetch_assoc()['kuerzel'] ?? '?';
        $ggAusw = $conn->query('SELECT kuerzel FROM Turnier_Team WHERE id = ' . (int)$rowGesperrt['fk_auswaertsteam'])->fetch_assoc()['kuerzel'] ?? '?';
        $ggVon = $hatSperrTrackingAnzeige ? htmlspecialchars((string)($rowGesperrt['gesperrt_von'] ?? '-'), ENT_QUOTES, 'UTF-8') : '<i title="Erfordert die Spalten gesperrt_von/gesperrt_am auf Turnier_Begegnung">unbekannt</i>';
        $ggAm = $hatSperrTrackingAnzeige ? htmlspecialchars((string)($rowGesperrt['gesperrt_am'] ?? '-'), ENT_QUOTES, 'UTF-8') : '<i>unbekannt</i>';
        $bnAttrGg = htmlspecialchars($bn, ENT_QUOTES);
        $pwAttrGg = htmlspecialchars($pw, ENT_QUOTES);
        echo "<tr>
            <td>#$ggId</td>
            <td>" . htmlspecialchars($ggLevelName, ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($ggHeim, ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($ggAusw, ENT_QUOTES, 'UTF-8') . "</td>
            <td>$ggVon</td>
            <td>$ggAm</td>
            <td>
                <form action='website_datachange/edit_games.php' method='POST' style='margin:0;display:inline;' onsubmit=\"return confirm('Begegnung #$ggId wirklich entsperren?');\">
                    <input type='hidden' name='TurnierID' value='$TurnierID'>
                    <input type='hidden' name='bn' value='$bnAttrGg'>
                    <input type='hidden' name='pw' value='$pwAttrGg'>
                    <input type='hidden' name='action' value='Begegnung_Entsperren'>
                    " . csrf_field() . "
                    <input type='hidden' name='begegnungIdEntsperren' value='$ggId'>
                    <button type='submit' class='button' style='margin:0;padding:0.3rem 0.7rem;font-size:0.75rem;'>Entsperren</button>
                </form>
            </td>
        </tr>";
    }
    echo "</tbody></table>";
    if ($anzahlGesperrt === 0) {
        echo "<p class='muted'>Aktuell keine gesperrten Begegnungen.</p>";
    }
    if (!$hatSperrTrackingAnzeige) {
        echo "<p class='bb-hint'>Hinweis: Wer/wann gesperrt hat wird erst erfasst, sobald die Spalten <code>gesperrt_von</code> (VARCHAR) und <code>gesperrt_am</code> (DATETIME) auf der Tabelle <code>Turnier_Begegnung</code> existieren.</p>";
    }
    ?>
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
    // direktem Hash-Link erreichbar. Jetzt am backstage-Flag (Admin/Co-Admin/Turniermaster/
    // Backstage-Zugang), auf ausdrücklichen Wunsch - siehe Chat. ?>
    <?php if (!$rechteFlags['backstage']) { ?>
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
        // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS - Spielername/Teamname/Telefonnummer
        // sind alles bei der Team-Anmeldung frei waehlbare Felder.
        $spielername = htmlspecialchars($rowTelefon['name'], ENT_QUOTES, 'UTF-8');
        $telefonnummer = htmlspecialchars($rowTelefon['telefonnummer'], ENT_QUOTES, 'UTF-8');
        $teamID = $rowTelefon['fk_team'];
        $timestamp = htmlspecialchars($rowTelefon['timestamp'], ENT_QUOTES, 'UTF-8');
        $sqlTeamname = 'SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND id = '. $teamID .'';
        $resultTeamname = $conn->query($sqlTeamname);
        $teamname = '';
        while ($rowTeamname = $resultTeamname->fetch_assoc()) {
            $teamname = htmlspecialchars($rowTeamname['name'], ENT_QUOTES, 'UTF-8');
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
        <?php if (!$rechteFlags['teams']) { ?>
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
     Turniere nutzbar (nicht mehr auf den Testmodus beschränkt) - bewusst weiterhin exklusiv Admin/
     Co-Admin (istAdminOderCoAdmin), anders als "Gruppen für Gruppenphase generieren" (teams-Flag). -->
<article id="backstage_gruppeneinteilung_losen">
    <div style='text-align: center'>
        <h2>Gruppeneinteilung losen</h2>
        <?php // RECHTE-AUDIT: bewusst strenger als der Rest der Turnier-Settings - exklusiv Admin/
        // Co-Admin, siehe Kommentar bei "Gruppeneinteilung_Losen" in edit_variables.php. ?>
        <?php if (!$istAdminOderCoAdmin) { ?>
        <p>Keine ausreichende Berechtigung. Das dürfen nur Admin und Co-Admin.</p>
        <?php } else {
            $glPhasen = [];
            $resultGlPhasen = $conn->query('SELECT * FROM `Turnier_Setting_Phasen` ORDER BY logical_order');
            while ($rowGlPhase = $resultGlPhasen->fetch_assoc()) { $glPhasen[] = $rowGlPhase; }
            $glFolgePhaseName = '?';
            foreach ($glPhasen as $p) { if ((int)$p['id'] === 13) { $glFolgePhaseName = $p['name']; } }

            // SICHERHEITSSPERRE: Sind für dieses Turnier schon Spielstände eingetragen, ist ein
            // Neu-Losen der Gruppen riskant (Teams landen ggf. in einer anderen Gruppe als der, in der
            // sie schon gespielt haben) - dann statt des einfachen Bestätigen-Hakens ein mehrstufiger
            // Ablauf: Warnhinweis -> erneuter Login (eigene bn/pw-Felder, nicht die Session) ->
            // zweite Bestätigung. Ohne bereits eingetragene Spiele bleibt der einfache Weg bestehen.
            $glSpieleVorhanden = false;
            $resGlSpieleCheck = $conn->query('SELECT COUNT(*) AS anzahl FROM Turnier_Spiel s JOIN Turnier_Begegnung b ON b.id = s.fk_begegnung WHERE b.fk_heimteam IN (SELECT id FROM Turnier_Team WHERE fk_turnier = ' . $TurnierID . ')');
            if ($resGlSpieleCheck && ($rowGlSpieleCheck = $resGlSpieleCheck->fetch_assoc())) {
                $glSpieleVorhanden = ((int)$rowGlSpieleCheck['anzahl'] > 0);
            }
        ?>
        <p>Würfelt alle Teams ohne Gruppe gleichmäßig auf die vorhandenen Gruppen, indem kurzzeitig die Turnierphase "Gruppeneinteilung" durchlaufen wird. Anschließend wechselt das Turnier automatisch weiter zur unten gewählten Turnierphase (Standard: "<?php echo htmlspecialchars($glFolgePhaseName); ?>").</p>

        <?php if (!$glSpieleVorhanden) { ?>
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
        <?php } else { ?>
        <!-- ========================================================================================
             MEHRSTUFIGER SICHERHEITS-ABLAUF: bereits Spiele eingetragen -> Warnung -> erneuter Login
             -> zweite Bestätigung. Der einzige Ort auf der Website, der eine erneute Anmeldung
             verlangt statt der bereits laufenden Session-Zugangsdaten - bewusst so, weil diese
             Aktion die Gruppenzuordnung mitten im laufenden Turnier durcheinanderbringen kann.
             ======================================================================================== -->
        <style>
            .gl-warnbox { max-width: 32rem; margin: 1rem auto; padding: 1rem 1.2rem; border-radius: 10px; background: rgba(239, 68, 68, 0.1); border: 2px solid #ef4444; text-align: left; }
            .gl-warnbox h3 { margin: 0 0 0.5rem; color: #ef4444; }
            .gl-warnbox p { font-size: 0.9rem; line-height: 1.5; margin: 0 0 0.9rem; }
            .gl-relogin { max-width: 26rem; margin: 1rem auto; padding: 1rem 1.2rem; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.15); text-align: left; }
            .gl-relogin .field { margin-bottom: 0.7rem; }
            .gl-relogin label { display: block; margin-bottom: 0.25rem; font-size: 0.85rem; opacity: 0.9; }
            .gl-relogin input, .gl-relogin select { width: 100%; box-sizing: border-box; }
        </style>
        <div id='gl-schritt-1' class='gl-warnbox'>
            <h3>&#9888; Achtung: Es sind bereits Spiele für dieses Turnier eingetragen!</h3>
            <p>Die Gruppeneinteilung jetzt neu zu losen kann bereits eingetragene Ergebnisse durcheinanderbringen - Teams könnten dadurch in eine andere Gruppe verschoben werden als die, in der sie schon gespielt haben. Das solltet ihr nur tun, wenn ihr genau wisst, was ihr tut.</p>
            <button type='button' class='button' onclick="document.getElementById('gl-schritt-1').style.display='none'; document.getElementById('gl-schritt-2').style.display='block';">Ich verstehe die Risiken - weiter</button>
        </div>
        <div id='gl-schritt-2' class='gl-relogin' style='display:none;'>
            <p style='margin:0 0 0.8rem;'><b>Zur Bestätigung bitte noch einmal einloggen</b> (nicht die bereits laufende Sitzung - eigene Zugangsdaten, jetzt neu eingeben):</p>
            <form action='website_datachange/edit_variables.php' method='POST' onsubmit="return confirm('Wirklich die Gruppeneinteilung neu losen, obwohl für dieses Turnier schon Spiele eingetragen sind? Das kann NICHT rückgängig gemacht werden.');">
                <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
                <input type='hidden' name='action' value='Gruppeneinteilung_Losen'/>
                <input type='hidden' name='spiele_bereits_eingetragen_bestaetigt' value='1'/>
                <?php echo csrf_field(); ?>
                <div class='field'>
                    <label>Benutzername</label>
                    <input type='text' name='bn' class='Eingabe' required autocomplete='username'>
                </div>
                <div class='field'>
                    <label>Passwort</label>
                    <input type='password' name='pw' class='Eingabe' required autocomplete='current-password'>
                </div>
                <div class='field'>
                    <label>Danach Turnierphase</label>
                    <select name='danach_turnierphase' class='Eingabe'>
                        <?php foreach ($glPhasen as $p) {
                            $sel = ((int)$p['id'] === 13) ? "selected" : "";
                            echo "<option value='" . (int)$p['id'] . "' $sel>" . htmlspecialchars($p['name']) . "</option>";
                        } ?>
                    </select>
                </div>
                <button type='submit' class='button' style='background:#ef4444;width:100%;'>Ja, nach erneutem Login jetzt neu losen</button>
            </form>
        </div>
        <?php } ?>
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
    <?php if (!$rechteFlags['teams']) { ?>
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
    <p>Legt fest, nach welchem Schema die Gruppenplatzierungen auf die ersten K.-o.-Begegnungen verteilt werden. Wirkt nur, solange der Schalter "Einzug K.-o.-Phase manuell anlegen" unten <b>nicht</b> aktiviert ist - ist er aktiviert, wird stattdessen alles manuell über "Begegnungen bearbeiten" angelegt und die Auswahl weiter unten komplett ignoriert.</p>
    <?php
        // Auf ausdrücklichen Wunsch direkt hier eingebettet (vorher nur als Text-Hinweis mit Link zu den
        // Turnier Settings) - wer sich mit dem Einzug ins KO-System befasst, soll den Schalter fürs
        // manuelle Anlegen nicht auf einer separaten Seite suchen müssen. Bleibt trotzdem exklusiv
        // Admin/Co-Admin vorbehalten (gleiches Flag wie in den Turnier Settings selbst) - Turniermaster
        // sehen diese Seite zwar auch (teams-Flag), dürfen den Schalter aber nicht umlegen.
        $keEinzugKoManuellAktuell = (int)($keRowSettings['einzug_ko_manuell_anlegen'] ?? 0);
    ?>
    <div class='ts-setting' style='margin-bottom:1rem;'>
        <span class='ts-setting-label'>Einzug K.-o.-Phase manuell anlegen</span>
        <span class='ts-hint'>Wenn aktiviert, berechnet die Website die ersten K.-o.-Paarungen nicht automatisch aus den Gruppenplatzierungen, sondern erwartet, dass diese manuell (z.B. über "Begegnungen bearbeiten") angelegt werden. Wichtig: bei aktiviertem Schalter gibt es zusätzlich noch ein eigenes Häkchen direkt in der K.-o.-Phase ("Gruppenphase beendet / K.-o.-Einzug fertig angelegt"), das erst gesetzt werden muss, damit die Website die manuell angelegten Begegnungen als startklar erkennt.</span>
        <?php if ($rechteFlags['turnier_settings']) { ?>
        <form action='website_datachange/edit_variables.php' method='POST' class='ts-row'>
            <input type='hidden' name='TurnierID' value='<?php echo $TurnierID; ?>'/>
            <input type='hidden' name='bn' value='<?php echo $keBnAttr; ?>'/>
            <input type='hidden' name='pw' value='<?php echo $kePwAttr; ?>'/>
            <input type='hidden' name='action' value='Turnier_Settings_EinzugKoManuell_Aendern'/>
            <input type='hidden' name='rueck_anker' value='backstage_ko_einzug_modus'/>
            <input type='checkbox' id='ke_einzug_ko_manuell_anlegen' name='einzug_ko_manuell_anlegen' value='1' <?php echo ($keEinzugKoManuellAktuell == 1) ? "checked" : ""; ?>>
            <label for='ke_einzug_ko_manuell_anlegen'>aktiviert</label>
            <label class='admin-toggle'>
                <input type='checkbox' onchange='this.form.submit()'>
                <span>bestätigen</span>
            </label>
        </form>
        <?php } else { ?>
        <span class='ts-hint'><i>Aktuell <?php echo $keEinzugKoManuellAktuell == 1 ? 'aktiviert' : 'deaktiviert'; ?> - nur Admin/Co-Admin können diesen Schalter ändern.</i></span>
        <?php } ?>
    </div>
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
            <?php if ($rechteFlags['backstage']) { ?>
            <a href='#backstage_tel' class='admin-menu-button admin-menu-button--backstage'>Telefonnummern</a>
            <?php } ?>
            <?php if ($rechteFlags['backstage']) { ?>
            <a href='#backstage_teampasswort' class='admin-menu-button admin-menu-button--backstage'>Team-Passwörter</a>
            <?php } ?>
            <?php if ($rechteFlags['backstage']) { ?>
            <a href='#backstage_warteliste' class='admin-menu-button admin-menu-button--backstage'>Warteliste</a>
            <?php } ?>
            <?php if ($rechteFlags['backstage']) { ?>
            <a href='#backstage_er_diagram' class='admin-menu-button admin-menu-button--backstage'>ER-Diagramm</a>
            <?php } ?>
            <?php if ($istEchterAdmin) { ?>
            <a href='#backstage_verlauf' class='admin-menu-button admin-menu-button--adminonly'>Verlauf</a>
            <?php } ?>
        </div>
        <?php if ($istAdminOderCoAdmin) { ?>
        <div class='admin-legende'>
            <h4>Farb-Legende</h4>
            <p style='font-size:0.75rem; opacity:0.8; margin:0 0 0.8rem; text-align:center;'>Die Rahmenfarbe zeigt, WER etwas überhaupt sehen kann - nicht, wer nur lesen vs. tatsächlich bearbeiten darf. Backstage-Zugang ist eine reine Lese-Rolle (sieht z.B. Team-Passwörter/Warteliste, kann aber nirgends etwas verändern), während Turniermaster bei den grün markierten Funktionen auch wirklich bearbeiten darf - inzwischen praktisch alles außer den bernstein-/rot-markierten Admin-Kernfunktionen.</p>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--testspiele'></span>
                <div>
                    <b>Türkiser Rahmen</b> (nur bei "Zufällige Spiele eintragen" in Gruppenphase/K.-o.-Phase/Losing Bracket, jeweils nur im Testmodus)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Backstage-Zugang, Schiedsrichter*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--backstage'></span>
                <div>
                    <b>Blauer Rahmen</b>: Telefonnummern, Team-Passwörter, Warteliste, ER-Diagramm (Infos-Menü); im Testmodus zusätzlich "Teams generieren" (eigene dunkelblaue Testmodus-Optik statt Rahmenfarbe)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Backstage-Zugang, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--cms'></span>
                <div>
                    <b>Pinker Rahmen</b> (nur beim CMS-Button oben in der Admin-Leiste, nicht im Settings-/Infos-Menü)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Autor*in, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--teams'></span>
                <div>
                    <b>Grüner Rahmen</b> (auch beim Settings- und Infos-Button oben in der Admin-Leiste): Teams bearbeiten/einsortieren, Gruppen für Gruppenphase generieren, Einzug ins KO-System, Green-Card-Begegnungen erstellen/sperren, Liste gesperrter Begegnungen, Begegnungs-ID in der K.-o.-Phase, Bullerei kommt<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Turniermaster, Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--coadmin'></span>
                <div>
                    <b>Bernsteinfarbener Rahmen</b>: Neues Turnier anlegen, Turnier Settings, Turnierphase, Gruppeneinteilung losen, Nutzermanagement<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Co-Admin, Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Autor*in, Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
                </div>
            </div>
            <div class='admin-legende-zeile'>
                <span class='admin-legende-swatch admin-legende-swatch--adminonly'></span>
                <div>
                    <b>Roter Rahmen</b> (Verlauf/Traffic/DB-Verlauf, Passwörter anderer Accounts einsehen/ändern)<br>
                    <span style='color:#2ecc71;'>&check; Sichtbar für:</span> Admin<br>
                    <span style='color:#e74c3c;'>&#10007; Nicht sichtbar für:</span> Co-Admin, Autor*in, Turniermaster, Backstage-Zugang, Schiedsrichter*in, Benutzer*in
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
    // per direktem Hash-Link erreichbar. Jetzt am backstage-Flag (Admin/Co-Admin/Turniermaster/
    // Backstage-Zugang), auf ausdrücklichen Wunsch - siehe Chat. ?>
    <?php if (!$rechteFlags['backstage']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <?php
    $sqlWarteliste = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_warteliste IN (SELECT id FROM `Turnier_Warteliste` WHERE fk_turnier = '. $TurnierID .')';
    $resultWarteliste = $conn->query($sqlWarteliste);
    $zeahler = 1;
    while ($rowWarteliste = $resultWarteliste->fetch_assoc()) {
        // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Team-/Spielernamen
        $a=htmlspecialchars($rowWarteliste["name"], ENT_QUOTES, 'UTF-8');
        $teamId = $rowWarteliste["id"];
        $b=printKuerzelWithLink($conn, $teamId);
        $ausgabeString = "";
        $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";
        $sqlSpieler = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team = ' . $rowWarteliste["id"] . ' ORDER BY ID';
        $resultSpieler = $conn->query($sqlSpieler);
        while ($rowSpieler = $resultSpieler->fetch_assoc()) {
            $x=htmlspecialchars($rowSpieler["name"], ENT_QUOTES, 'UTF-8');
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
    // direktem Hash-Link erreichbar. Jetzt am backstage-Flag (Admin/Co-Admin/Turniermaster/
    // Backstage-Zugang), auf ausdrücklichen Wunsch - siehe Chat. ?>
    <?php if (!$rechteFlags['backstage']) { ?>
    <p>Keine ausreichende Berechtigung.</p>
    <?php } else { ?>
    <?php
    // Auf ausdrücklichen Wunsch stehen Passwörter nicht mehr direkt offen in der Liste (zu leicht aus
    // Versehen mitgelesen/über die Schulter geschaut) - stattdessen erst per Klick auf "anzeigen" pro
    // Zeile einblendbar, siehe togglePasswortSichtbarkeit() unten.
    $sqlPasswort = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '. $TurnierID .'';
    $resultPasswort = $conn->query($sqlPasswort);
    $zeahler = 1;
    while ($rowPasswort = $resultPasswort->fetch_assoc()) {
        // SICHERHEIT: htmlspecialchars() gegen gespeichertes XSS ueber Teamname/Team-Passwort (beides
        // vom Team selbst bei der Anmeldung frei waehlbar).
        $a=htmlspecialchars($rowPasswort["name"], ENT_QUOTES, 'UTF-8');
        $teamId = $rowPasswort["id"];
        $passwort = htmlspecialchars($rowPasswort["password"], ENT_QUOTES, 'UTF-8');
        $b=printKuerzelWithLink($conn, $teamId);
        $ausgabeString = "";
        $ausgabeString .= "$zeahler. $a <em>($b)</em> &mdash;";
        $zeahler++;
        echo "<li>$ausgabeString | Passwort: "
            . "<span class='pw-mask' id='pw-mask-$teamId'>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>"
            . "<span class='pw-value' id='pw-value-$teamId' hidden>$passwort</span> "
            . "<button type='button' class='button small' onclick=\"togglePasswortSichtbarkeit($teamId, this)\">anzeigen</button>"
            . "</li>";
    }
    ?>
    <script>
        function togglePasswortSichtbarkeit(teamId, btn) {
            var mask = document.getElementById('pw-mask-' + teamId);
            var value = document.getElementById('pw-value-' + teamId);
            var jetztAnzeigen = value.hidden;
            value.hidden = !jetztAnzeigen;
            mask.hidden = jetztAnzeigen;
            btn.textContent = jetztAnzeigen ? 'verbergen' : 'anzeigen';
        }
    </script>
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
    <?php if ($test_turnier_id == 0 || !$rechteFlags['backstage']) { ?>
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
    if ($test_turnier_id == 0 || !$darfZufaelligeSpieleEintragen || !in_array($zsScope, ['gruppenphase', 'ko'], true)) {
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
            <input type='checkbox' id='neu_nur_oberes_dreieck_lb' name='nurOberesDreieckInLosingBracket' value='1' <?php echo (($altesTurnier['nurOberesDreieckInLosingBracket'] ?? 0) == 1) ? "checked" : ""; ?>>
            <label for='neu_nur_oberes_dreieck_lb'>Nur oberes Dreieck in Losing Bracket</label>
            <p style='font-size:0.8rem;opacity:0.75;margin:0.2rem 0 0;'>Wie "Nur oberes Dreieck in Gruppenphase", aber für die Losing-Bracket-Tabelle.</p>
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
                " . csrf_field() . "
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
                " . csrf_field() . "
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
    tsCheckboxFeld('Nur oberes Dreieck in Losing Bracket', 'Wie "Nur oberes Dreieck in Gruppenphase", aber für die Losing-Bracket-Tabelle: aktiviert zeigt die Tabelle das Ergebnis nur einmal (oberes Dreieck) statt doppelt.', 'nurOberesDreieckInLosingBracket', $rowTurnierSettings['nurOberesDreieckInLosingBracket'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Lösche erste Zeile und Spalte', 'Blendet zusätzlich die erste Zeile/Spalte der Gruppentabelle aus (nur sinnvoll zusammen mit "Nur oberes Dreieck", da dort sonst leer). Macht die Tabelle noch kompakter, kann aber verwirren: z.B. sieht eine Gruppe mit 4 Teams dann so aus, als hätte sie nur 3, weil das erste Team nur noch in den Spaltenköpfen der anderen auftaucht, nicht mehr als eigene Zeile/Spalte.', 'loescheErsteZeileUndSpalte', $rowTurnierSettings['loescheErsteZeileUndSpalte'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Losing Bracket offen für K.-o.-Verlierer', 'Verlierer der K.-o.-Phase spielen im Losing Bracket weiter.', 'losingbracket_open_for_ko_losers', $rowTurnierSettings['losingbracket_open_for_ko_losers'], $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Excel-Verknüpfung nutzen', 'Ersetzt den normalen (automatisch berechneten) Spielplan komplett durch eine eingebettete Excel-Tabelle - der normale Spielplan wird dann gar nicht mehr angezeigt. Nur aktivieren, wenn unten auch wirklich ein gültiger Excel-Link eingetragen ist.', 'use_excel', $rowTurnierSettings['use_excel'], $TurnierID, $bnAttr, $pwAttr);
    tsTextFeld('Excel-Link', 'Nur relevant, wenn "Excel-Verknüpfung nutzen" aktiviert ist.', 'excel_link', $rowTurnierSettings['excel_link'], 'text', $TurnierID, $bnAttr, $pwAttr);
    tsCheckboxFeld('Schnee-Effekt', 'Aktiviert den winterlichen Schnee-Effekt auf der Website. Zeigt zusätzlich an drei Stellen (direkt unter "Team anmelden" auf der Startseite, über den drei Phase-Karten auf der Spielplan-Seite, und weiterhin unten im Footer) einen Button zu den Special-Regeln für den Adventscup an - gedacht für Turniere rund um die Weihnachtszeit.', 'schnee', $rowTurnierSettings['schnee'], $TurnierID, $bnAttr, $pwAttr);
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
                'kommentar' => $rowNutzer['admin_kommentar'] ?? null,
                'rolle_ids' => $rolleIds,
            ];
        }
        // Auf ausdrücklichen Wunsch alphabetisch statt nach Rollen-/Berechtigungsstärke sortiert (siehe
        // Chat) - Benutzername ist eindeutig, daher reicht ein einfacher String-Vergleich.
        usort($alleNutzerMitRollen, function($a, $b) { return strcasecmp($a['bn'], $b['bn']); });

        $bnAttrNm = htmlspecialchars($bn, ENT_QUOTES);
        $pwAttrNm = htmlspecialchars($pw, ENT_QUOTES);
    ?>
    <style>
        .nm-rollen-tabelle { width: 100%; margin-bottom: 1.2rem; font-size: 0.82rem; }
        .nm-userlist { margin-bottom: 1rem; }
        /* ============================================================================================
           NUTZER-KARTE ALS AKKORDEON (<details>/<summary>) - auf ausdrücklichen Wunsch: kompakte
           Liste (Avatar + Name + evtl. Klarname-Kommentar + Rollen-Chips), die sich erst auf Klick zu
           allen Bearbeitungsmöglichkeiten aufklappt, statt alles dauerhaft ausgebreitet zu zeigen.
           ============================================================================================ */
        .nm-user-card { border: 1px solid rgba(139, 92, 246, 0.22); border-radius: 8px; margin-bottom: 0.6rem; text-align: left; font-size: 0.82rem; overflow: hidden; transition: background-color 0.4s ease, border-color 0.4s ease; }
        .nm-user-card[open] { background: rgba(139, 92, 246, 0.05); }
        /* Kurzzeitiges Aufleuchten, wenn nach dem Speichern zu dieser Karte gescrollt wird (siehe
           nmScrollZuGeaendertemNutzer() weiter unten) - macht auf einen Blick klar, welcher Nutzer
           gerade bearbeitet wurde, ohne dass man ihn in der Liste erst wiedersuchen muss. */
        .nm-user-card--highlight { border-color: var(--admin-accent); background: rgba(139, 92, 246, 0.16); }
        .nm-user-card summary { list-style: none; cursor: pointer; }
        .nm-user-card summary::-webkit-details-marker { display: none; }
        .nm-user-summary { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.8rem; }
        .nm-user-summary:hover { background: rgba(255,255,255,0.04); }
        .nm-user-summary-main { display: inline-flex; align-items: baseline; gap: 0.4rem; flex-wrap: wrap; min-width: 0; }
        .nm-user-avatar { flex-shrink: 0; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: var(--admin-accent-deep); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 700; }
        .nm-user-name { font-size: 0.95rem; font-weight: 700; }
        .nm-user-kommentar { font-size: 0.78rem; font-style: italic; opacity: 0.75; }
        .nm-user-summary-roles { display: flex; align-items: center; gap: 0.3rem; flex-wrap: wrap; margin-left: auto; justify-content: flex-end; }
        .nm-role-chip-mini { display: inline-block; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 0.1rem 0.5rem; font-size: 0.68rem; white-space: nowrap; opacity: 0.85; }
        .nm-role-chip-mini--none { font-style: italic; opacity: 0.55; }
        .nm-expand-arrow { flex-shrink: 0; opacity: 0.6; font-size: 0.7rem; transition: transform 0.2s ease; }
        .nm-user-card[open] .nm-expand-arrow { transform: rotate(180deg); }
        .nm-user-details { padding: 0 0.8rem 0.7rem; border-top: 1px solid rgba(139, 92, 246, 0.18); }
        .nm-user-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.6rem; }
        .nm-user-admin-row { border-top: 1px dashed rgba(139, 92, 246, 0.3); padding-top: 0.6rem; }
        .nm-user-roles { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        /* Badge bleibt immer im kompakten Stil (auch wenn eine Entfernen-Möglichkeit existiert) - das
           "×" liegt als kleiner Kreis oben rechts AUSSERHALB der Badge (position:absolute), nimmt also
           keinen Platz im Badge-Inneren weg und macht die Badge dadurch nicht größer/breiter. */
        .nm-badge { position: relative; display: inline-flex; align-items: center; background: rgba(139, 92, 246, 0.18); border: 1px solid var(--admin-accent); border-radius: 10px; padding: 0.15rem 0.55rem; font-size: 0.72rem; white-space: nowrap; transition: opacity 0.15s ease, border-color 0.15s ease, background-color 0.15s ease; }
        .nm-badge-remove { position: absolute; top: -0.45rem; right: -0.45rem; width: 1.05rem; height: 1.05rem; border-radius: 50%; background: #7a2020; border: 1px solid #c0392b; color: #fff; font-size: 0.62rem; line-height: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; box-shadow: none; }
        /* Noch nicht gespeicherte Aenderungen: Rolle zum Entfernen vorgemerkt (durchgestrichen, rot
           angedeutet) bzw. Rolle zum Hinzufuegen vorgemerkt (gestrichelt, gruen angedeutet) - so ist
           auf einen Blick klar, was beim naechsten Klick auf "Speichern" tatsaechlich passieren wird. */
        .nm-badge--pending-remove { opacity: 0.5; border-color: #c0392b; text-decoration: line-through; background: rgba(192, 57, 43, 0.12); }
        .nm-badge--pending-add { border-style: dashed; border-color: #27ae60; background: rgba(39, 174, 96, 0.14); }
        .nm-login-als, .nm-addrole-row, .nm-pwchange-row { display: inline-flex; gap: 0.3rem; align-items: center; margin: 0; }
        .nm-login-als button { padding: 0.15rem 0.5rem; font-size: 0.7rem; }
        .nm-addrole-row select, .nm-pwchange-row input[type='text'] {
            padding: 0.15rem 0.35rem; font-size: 0.72rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.06); color: #fff;
        }
        .nm-pwchange-row input[type='text'] { width: 10rem; }
        .nm-addrole-row button { background: var(--admin-accent-deep); border-color: var(--admin-accent); border-radius: 4px; border-width: 1px; border-style: solid; color: #fff; cursor: pointer; padding: 0.15rem 0.5rem; font-size: 0.72rem; }
        /* Passwort anzeigen/ändern ist strikt "echten" Admins vorbehalten (siehe $binIchEchterAdmin
           weiter unten) - bekommt deshalb denselben roten Rahmen wie die "adminonly"-Stufe im
           Settings/Infos-Farbsystem, statt eines eigenen abweichenden Stils. */
        .nm-pw-group { display: inline-flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; background: rgba(139, 92, 246, 0.08); border: 2px solid var(--admin-border-adminonly); border-radius: 6px; padding: 0.3rem 0.6rem; }
        .nm-pw-label { font-size: 0.72rem; font-weight: 700; opacity: 0.85; }
        .nm-pw { opacity: 0.9; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
        .nm-pw-toggle { border: none; background: none; color: var(--admin-border-adminonly); cursor: pointer; font-size: 0.72rem; padding: 0; text-decoration: underline; }
        /* Speichern/Verwerfen: sticky-artig am unteren Kartenrand, aber schlicht innerhalb des Flusses -
           per Default dezent, wird erst per JS farbig/aktiv sobald es etwas zu speichern gibt. */
        .nm-save-row { justify-content: flex-end; border-top: 1px dashed rgba(139, 92, 246, 0.3); padding-top: 0.6rem; }
        .nm-pending-hinweis { font-size: 0.75rem; color: #f0b429; margin-right: auto; }
        .nm-save-btn { background: linear-gradient(135deg, #1e8449, #27ae60); border: 1px solid #27ae60; border-radius: 5px; color: #fff; cursor: pointer; padding: 0.3rem 0.9rem; font-size: 0.78rem; font-weight: 700; }
        .nm-save-btn:disabled { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.18); color: rgba(255,255,255,0.4); cursor: default; }
        .nm-discard-btn { background: none; border: 1px solid rgba(255,255,255,0.3); border-radius: 5px; color: #fff; cursor: pointer; padding: 0.3rem 0.7rem; font-size: 0.75rem; }
        /* WICHTIG: bloße <button>-Elemente erben sonst die große Standard-Button-Optik der Website
           (2.75rem hoch, GROSSBUCHSTABEN, Letter-Spacing, weißer Schatten-Rahmen) - dadurch sah die
           Schrift größer/unpassender aus als die kleinen Buttons selbst. Hier gezielt NUR für die
           kompakten Nutzermanagement-Buttons zurückgesetzt (Selektoren sind alle nm-*-spezifisch,
           betrifft also keine anderen Buttons auf der Website). */
        .nm-login-als button, .nm-addrole-row button, .nm-pwchange-row button, .nm-pw-toggle, .nm-save-btn, .nm-discard-btn {
            height: auto; line-height: 1.2; letter-spacing: normal; text-transform: none; box-shadow: none;
        }
    </style>

    <h2>Nutzer</h2>
    <p><i>Alphabetisch sortiert. Jeder Nutzer kann mehrere Rollen gleichzeitig haben - auf einen Klick auf
    den Namen klappt die Karte mit allen Bearbeitungsmöglichkeiten auf.</i></p>
    <p><i>Der kleine Notiz-Button &#128221; neben jedem Namen ist nur für Admin/Co-Admin sichtbar - z.B. praktisch,
    um sich zu notieren, welche echte Person hinter einem Benutzernamen steckt, wenn sich jemand nicht mit
    Klarnamen angemeldet hat.</i></p>
    <!-- SUCHE + ROLLENFILTER: rein clientseitig (die Liste ist bereits komplett serverseitig gerendert) -
         blendet passende .nm-user-card-Elemente per JS ein/aus, statt die Seite neu zu laden. -->
    <div class='nm-filter-row' style='display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;margin-bottom:0.8rem;'>
        <input type='text' id='nm_suche' placeholder='Nutzer suchen...' class='Eingabe' style='color:white;max-width:220px;margin:0;' oninput='nmFilterListe()'>
        <select id='nm_rollenfilter' onchange='nmFilterListe()' style='padding:0.3rem 0.5rem;border-radius:4px;border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.06);color:#fff;'>
            <option value=''>Alle Rollen</option>
            <option value='keine'>Keine Rolle</option>
            <?php foreach ($rollenListeFuerUebersicht as $r) {
                echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['name']) . "</option>";
            } ?>
        </select>
        <span id='nm_treffer_hinweis' style='font-size:0.78rem;opacity:0.75;'></span>
    </div>
    <script>
        function nmFilterListe() {
            var suchtext = document.getElementById('nm_suche').value.trim().toLowerCase();
            var rollenfilter = document.getElementById('nm_rollenfilter').value;
            var karten = document.querySelectorAll('.nm-user-card');
            var sichtbar = 0;
            karten.forEach(function(karte) {
                var bn = karte.getAttribute('data-bn') || '';
                var rollen = (karte.getAttribute('data-rollen') || '').split(',').filter(Boolean);
                var passtSuche = suchtext === '' || bn.indexOf(suchtext) !== -1;
                var passtRolle = true;
                if (rollenfilter === 'keine') {
                    passtRolle = rollen.length === 0;
                } else if (rollenfilter !== '') {
                    passtRolle = rollen.indexOf(rollenfilter) !== -1;
                }
                var passt = passtSuche && passtRolle;
                karte.style.display = passt ? '' : 'none';
                if (passt) { sichtbar++; }
            });
            var hinweis = document.getElementById('nm_treffer_hinweis');
            if (hinweis) {
                hinweis.textContent = (suchtext !== '' || rollenfilter !== '') ? (sichtbar + ' Treffer') : '';
            }
        }
    </script>
    <div class='nm-userlist'>
    <?php foreach ($alleNutzerMitRollen as $nutzer) {
        $nmPwId = 'nm_pw_' . $nutzer['id'];
        $nmDataBn = htmlspecialchars(strtolower($nutzer['bn']), ENT_QUOTES);
        $nmDataRollen = htmlspecialchars(implode(',', $nutzer['rolle_ids']), ENT_QUOTES);
    ?>
        <details class='nm-user-card' id='nm_user_<?php echo $nutzer['id']; ?>' data-user-id='<?php echo $nutzer['id']; ?>' data-bn='<?php echo $nmDataBn; ?>' data-rollen='<?php echo $nmDataRollen; ?>'>
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
            $nmInitial = htmlspecialchars(ermittleAnzeigeAvatar($conn, $nutzer['id']), ENT_QUOTES, 'UTF-8');
            ?>
            <!-- Kompakte Zeile (immer sichtbar): Avatar, Name, evtl. Klarname-Kommentar, Rollen als
                 kleine Chips rechts, Pfeil - auf Klick klappt die ganze Karte auf. Auf ausdrücklichen
                 Wunsch, siehe Chat: vorher waren alle Bearbeitungsfunktionen für JEDEN Nutzer dauerhaft
                 ausgebreitet, was die Liste bei vielen Nutzern unübersichtlich machte. -->
            <summary class='nm-user-summary'>
                <span class='nm-user-summary-main'>
                    <span class='nm-user-avatar'><?php echo $nmInitial; ?></span>
                    <span class='nm-user-name'><?php echo htmlspecialchars($nutzer['bn']); ?></span>
                    <?php if (!empty($nutzer['kommentar'])) { ?>
                    <span class='nm-user-kommentar'>(<?php echo htmlspecialchars($nutzer['kommentar'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php } ?>
                </span>
                <span class='nm-user-summary-roles'>
                    <?php if (count($nutzer['rolle_ids']) > 0) {
                        foreach ($nutzer['rolle_ids'] as $rid) {
                            $rname = $rollenNamenById[$rid] ?? ('Rolle ' . $rid);
                            echo "<span class='nm-role-chip-mini'>" . htmlspecialchars($rname) . "</span>";
                        }
                    } else { ?>
                    <span class='nm-role-chip-mini nm-role-chip-mini--none'>Keine Rolle</span>
                    <?php } ?>
                </span>
                <span class='nm-expand-arrow'>&#9662;</span>
            </summary>
            <div class='nm-user-details'>
                <!-- Zeile 1: Identität/Login - eigene, sofort abgesendete Mini-Formulare (seltene
                     Einzelaktionen, nicht Teil des "mehrere Rollen nacheinander"-Problems). -->
                <div class='nm-user-row'>
                    <!-- Admin-Kommentar: rein interne Notiz für Admin/Co-Admin (z.B. "das ist eigentlich
                         Max Mustermann"), nie öffentlich sichtbar. Beide Rollen dürfen das sehen UND
                         bearbeiten - anders als beim Benutzernamen/Passwort weiter unten, die "echten"
                         Admins vorbehalten bleiben. -->
                    <button type='button' class='nm-pw-toggle' title='Admin-Kommentar bearbeiten' onclick="var f=document.getElementById('nm_kommentar_form_<?php echo $nutzer['id']; ?>'); f.style.display = (f.style.display==='inline-flex') ? 'none' : 'inline-flex';">&#128221; Notiz</button>
                    <form action='website_datachange/edit_account.php' method='POST' class='nm-pwchange-row' id='nm_kommentar_form_<?php echo $nutzer['id']; ?>' style='display:none;'>
                        <input type='hidden' name='action' value='Admin_Kommentar_Aendern'>
                        <?php echo csrf_field(); ?>
                        <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                        <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                        <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                        <input type='text' name='neuer_kommentar' value='<?php echo htmlspecialchars((string)$nutzer['kommentar'], ENT_QUOTES, 'UTF-8'); ?>' placeholder='z.B. echter Name' style='width:12rem;'>
                        <button type='submit'>speichern</button>
                    </form>
                    <?php if ($binIchEchterAdmin) { ?>
                    <button type='button' class='nm-pw-toggle' title='Benutzernamen ändern' onclick="var f=document.getElementById('nm_bn_form_<?php echo $nutzer['id']; ?>'); f.style.display = (f.style.display==='inline-flex') ? 'none' : 'inline-flex';">&#9998; Benutzername</button>
                    <form action='website_datachange/edit_account.php' method='POST' class='nm-pwchange-row' id='nm_bn_form_<?php echo $nutzer['id']; ?>' style='display:none;' onsubmit="return confirm('Benutzernamen von <?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?> wirklich ändern?');">
                        <input type='hidden' name='action' value='Benutzername_Aendern'>
                        <?php echo csrf_field(); ?>
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
                        <?php echo csrf_field(); ?>
                        <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                        <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                        <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                        <button type='submit' class='admin-menu-button admin-menu-button--coadmin' style='min-width:auto;padding:0.15rem 0.5rem;font-size:0.7rem;'>Login als User</button>
                    </form>
                    <?php } ?>
                    <?php if ($loeschenErlaubt) { ?>
                    <form action='website_datachange/edit_account.php<?php echo $test_turnier_id!=0 ? "?test_turnier_id=$test_turnier_id" : ""; ?>' method='POST' class='nm-login-als' onsubmit="return confirm('Nutzer <?php echo htmlspecialchars($nutzer['bn'], ENT_QUOTES); ?> wirklich unwiderruflich löschen?');">
                        <input type='hidden' name='action' value='Benutzer_Loeschen'>
                        <?php echo csrf_field(); ?>
                        <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                        <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                        <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>
                        <button type='submit' class='admin-menu-button admin-menu-button--adminonly' style='min-width:auto;padding:0.15rem 0.5rem;font-size:0.7rem;'>Löschen</button>
                    </form>
                    <?php } ?>
                </div>

                <!-- Rollen + Passwort: EIN gemeinsames Formular, das erst per "Speichern" abgesendet
                     wird - auf ausdrücklichen Wunsch (siehe Chat), damit mehrere Rollenänderungen
                     nicht mehr jede für sich einen Page-Reload auslösen. Bis zum Speichern passiert
                     alles rein clientseitig (siehe nmStageRoleAdd()/nmToggleRoleRemove() weiter unten),
                     die Hidden-Inputs rollen_hinzufuegen[]/rollen_entfernen[] werden erst dabei erzeugt. -->
                <form action='website_datachange/edit_account.php<?php echo $test_turnier_id!=0 ? "?test_turnier_id=$test_turnier_id" : ""; ?>' method='POST' id='nm_batch_form_<?php echo $nutzer['id']; ?>' onsubmit='return confirm("Änderungen wirklich speichern?");'>
                    <input type='hidden' name='action' value='Nutzer_Rollen_Speichern'>
                    <?php echo csrf_field(); ?>
                    <input type='hidden' name='admin_bn' value='<?php echo $bnAttrNm; ?>'>
                    <input type='hidden' name='admin_pw' value='<?php echo $pwAttrNm; ?>'>
                    <input type='hidden' name='ziel_benutzer_id' value='<?php echo $nutzer['id']; ?>'>

                    <div class='nm-user-row nm-user-roles' id='nm_rollen_badges_<?php echo $nutzer['id']; ?>'>
                    <?php foreach ($nutzer['rolle_ids'] as $rid) {
                        $rname = $rollenNamenById[$rid] ?? ('Rolle ' . $rid);
                        $rnameAttr = htmlspecialchars($rname, ENT_QUOTES, 'UTF-8');
                        echo "<span class='nm-badge' id='nm_role_chip_{$nutzer['id']}_{$rid}'>" . htmlspecialchars($rname);
                        // Kein count() > 1-Schutz mehr: ein Nutzer darf auch komplett rollenlos sein, die
                        // letzte Rolle muss also genauso entfernbar sein wie jede andere.
                        if (nmDarfRolleVergeben($rollenFlagsById[$rid] ?? [], $darfNeueAdmins, $darfNeueCoAdmins, $darfRestlicheRollenVergeben)) {
                            echo "<button type='button' class='nm-badge-remove' title='Rolle entfernen (erst beim Speichern wirksam)' onclick=\"nmToggleRoleRemove({$nutzer['id']}, $rid, this.parentElement)\">&times;</button>";
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
                    ?>
                    </div>
                    <?php if (count($verfuegbareRollen) > 0) { ?>
                    <div class='nm-user-row nm-addrole-row'>
                        <select id='nm_rollen_select_<?php echo $nutzer['id']; ?>'>
                            <option value='' selected>Rolle hinzufügen ...</option>
                            <?php foreach ($verfuegbareRollen as $r) {
                                echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['name']) . "</option>";
                            } ?>
                        </select>
                        <button type='button' onclick='nmStageRoleAdd(<?php echo $nutzer['id']; ?>)'>+ hinzufügen</button>
                    </div>
                    <?php } ?>

                    <?php if ($binIchEchterAdmin) { ?>
                    <!-- Passwort - nur für "echte" Admins, per gestrichelter Linie abgesetzt. Anzeigen
                         bleibt eine reine Anzeige-Umschaltung, das Textfeld für ein neues Passwort ist
                         Teil desselben Formulars wie die Rollen und wird erst mit "Speichern" wirksam. -->
                    <div class='nm-user-row nm-user-admin-row'>
                        <div class='nm-pw-group'>
                            <span class='nm-pw-label'>Passwort:</span>
                            <span class='nm-pw'>
                                <span id='<?php echo $nmPwId; ?>' style='display:none;'><?php echo htmlspecialchars($nutzer['pw']); ?></span>
                                <button type='button' class='nm-pw-toggle' onclick="var s=document.getElementById('<?php echo $nmPwId; ?>'); var sichtbar = s.style.display !== 'none'; s.style.display = sichtbar ? 'none' : 'inline'; this.textContent = sichtbar ? 'anzeigen' : 'verbergen';">anzeigen</button>
                            </span>
                            <span class='nm-pwchange-row'>
                                <input type='text' name='neues_passwort' placeholder='Neues Passwort (leer = unverändert)' oninput='nmUpdateSaveState(<?php echo $nutzer['id']; ?>)'>
                            </span>
                        </div>
                    </div>
                    <?php } ?>

                    <div class='nm-user-row nm-save-row'>
                        <span class='nm-pending-hinweis' id='nm_pending_hinweis_<?php echo $nutzer['id']; ?>' hidden>Ungespeicherte Änderungen</span>
                        <button type='button' class='nm-discard-btn' id='nm_discard_btn_<?php echo $nutzer['id']; ?>' hidden onclick='nmDiscardChanges(<?php echo $nutzer['id']; ?>)'>Verwerfen</button>
                        <button type='submit' class='nm-save-btn' id='nm_save_btn_<?php echo $nutzer['id']; ?>' disabled>Speichern</button>
                    </div>
                </form>
            </div>
        </details>
    <?php } ?>
    </div>
    <script>
        // ============================================================================================
        // NUTZERMANAGEMENT: ROLLEN + PASSWORT ERST CLIENTSEITIG SAMMELN, DANN GEMEINSAM SPEICHERN
        // ============================================================================================
        // Auf ausdrücklichen Wunsch (siehe Chat): "+ hinzufügen" und das "×" an einer Rolle senden NICHT
        // mehr sofort ein eigenes Formular ab (vorher: ein Page-Reload PRO Einzeländerung). Stattdessen
        // wird der Zustand rein im DOM gesammelt (neue Badges bzw. "durchgestrichene" Badges + jeweils
        // ein verstecktes Input-Feld im gemeinsamen Formular) und erst beim Klick auf "Speichern" in
        // EINEM Request abgeschickt. Die eigentliche DB-Änderung passiert weiterhin serverseitig
        // (Nutzer_Rollen_Speichern in edit_account.php) - hier wird nur der Formularzustand verwaltet.
        function nmUpdateSaveState(userId) {
            var form = document.getElementById('nm_batch_form_' + userId);
            if (!form) { return; }
            var pendingRollen = form.querySelectorAll('input[name="rollen_hinzufuegen[]"], input[name="rollen_entfernen[]"]').length;
            var pwFeld = form.querySelector('input[name="neues_passwort"]');
            var hatPwAenderung = pwFeld && pwFeld.value.trim() !== '';
            var hatAenderungen = pendingRollen > 0 || hatPwAenderung;
            var saveBtn = document.getElementById('nm_save_btn_' + userId);
            var discardBtn = document.getElementById('nm_discard_btn_' + userId);
            var hinweis = document.getElementById('nm_pending_hinweis_' + userId);
            if (saveBtn) { saveBtn.disabled = !hatAenderungen; }
            if (discardBtn) { discardBtn.hidden = !hatAenderungen; }
            if (hinweis) { hinweis.hidden = !hatAenderungen; }
        }

        function nmToggleRoleRemove(userId, roleId, chipEl) {
            var form = document.getElementById('nm_batch_form_' + userId);
            var vorhandenesInput = form.querySelector('input[name="rollen_entfernen[]"][value="' + roleId + '"]');
            if (vorhandenesInput) {
                vorhandenesInput.remove();
                chipEl.classList.remove('nm-badge--pending-remove');
            } else {
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = 'rollen_entfernen[]'; input.value = roleId;
                form.appendChild(input);
                chipEl.classList.add('nm-badge--pending-remove');
            }
            nmUpdateSaveState(userId);
        }

        function nmStageRoleAdd(userId) {
            var select = document.getElementById('nm_rollen_select_' + userId);
            var roleId = select.value;
            if (!roleId) { return; }
            var roleName = select.options[select.selectedIndex].textContent;
            var form = document.getElementById('nm_batch_form_' + userId);
            var badgesWrap = document.getElementById('nm_rollen_badges_' + userId);

            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'rollen_hinzufuegen[]'; input.value = roleId;
            form.appendChild(input);

            var chip = document.createElement('span');
            chip.className = 'nm-badge nm-badge--pending-add';
            chip.setAttribute('data-role-id', roleId);
            chip.setAttribute('data-role-name', roleName);
            chip.appendChild(document.createTextNode(roleName));
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'nm-badge-remove';
            removeBtn.title = 'Hinzufügen rückgängig machen';
            removeBtn.innerHTML = '&times;';
            removeBtn.onclick = function() {
                input.remove();
                chip.remove();
                var opt = document.createElement('option');
                opt.value = roleId; opt.textContent = roleName;
                select.appendChild(opt);
                nmUpdateSaveState(userId);
            };
            chip.appendChild(removeBtn);
            badgesWrap.appendChild(chip);

            select.remove(select.selectedIndex);
            select.value = '';
            nmUpdateSaveState(userId);
        }

        function nmDiscardChanges(userId) {
            var form = document.getElementById('nm_batch_form_' + userId);
            if (!form) { return; }
            var select = document.getElementById('nm_rollen_select_' + userId);
            form.querySelectorAll('.nm-badge--pending-add').forEach(function(chip) {
                if (select) {
                    var opt = document.createElement('option');
                    opt.value = chip.getAttribute('data-role-id');
                    opt.textContent = chip.getAttribute('data-role-name');
                    select.appendChild(opt);
                }
                chip.remove();
            });
            form.querySelectorAll('.nm-badge--pending-remove').forEach(function(chip) {
                chip.classList.remove('nm-badge--pending-remove');
            });
            form.querySelectorAll('input[name="rollen_hinzufuegen[]"], input[name="rollen_entfernen[]"]').forEach(function(el) { el.remove(); });
            var pwFeld = form.querySelector('input[name="neues_passwort"]');
            if (pwFeld) { pwFeld.value = ''; }
            nmUpdateSaveState(userId);
        }

        // Nach dem Speichern (siehe nm_scroll_zu-Redirect in edit_account.php) automatisch zur gerade
        // bearbeiteten Nutzer-Karte scrollen, sie aufklappen und kurz hervorheben - erspart das manuelle
        // Wiedersuchen des Nutzers in der (ggf. langen) Liste.
        document.addEventListener('DOMContentLoaded', function() {
            var params = new URLSearchParams(window.location.search);
            var scrollZu = params.get('nm_scroll_zu');
            if (!scrollZu) { return; }
            var karte = document.getElementById('nm_user_' + scrollZu);
            if (!karte) { return; }
            karte.open = true;
            karte.classList.add('nm-user-card--highlight');
            window.setTimeout(function() { karte.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 350);
            window.setTimeout(function() { karte.classList.remove('nm-user-card--highlight'); }, 2500);
            // Parameter aus der URL entfernen, damit ein Neuladen der Seite nicht wieder dorthin scrollt.
            params.delete('nm_scroll_zu');
            var neueQuery = params.toString();
            var neueUrl = window.location.pathname + (neueQuery ? '?' + neueQuery : '') + window.location.hash;
            window.history.replaceState(null, '', neueUrl);
        });
    </script>

    <h5><br/></h5>
    <a href='#backstage_neuen_nutzer_anlegen' class='admin-menu-button admin-menu-button--coadmin'>Neuen Nutzer anlegen</a>

    <h5><br/></h5>
    <h2>Rollen</h2>
    <p><i>Admin und Co-Admin sind <b>Sammel-Rollen</b>: wer eine davon hat, braucht keine weitere Rolle zusätzlich - sie umfassen automatisch alle Rechte der übrigen Rollen. Die restlichen Rollen (Autor*in, Turniermaster, Backstage-Zugang, Schiedsrichter*in) sind dagegen einzelne, unabhängige Rechte-Bausteine, die man je nach Bedarf miteinander kombiniert (z.B. braucht jemand, der Teams UND Spielergebnisse bearbeiten soll, sowohl Turniermaster als auch Schiedsrichter*in).</i></p>
    <?php
    // Erklärungstexte kommen jetzt aus getRollenErklaerungen() (database/rollen_definitionen.php) -
    // dieselbe Funktion wird auch auf der eigenen Profilseite (#account_profil) genutzt, damit beide
    // Stellen zwangsläufig denselben Text zeigen. Fällt eine Rollen-ID dort nicht in die Liste (z.B.
    // eine später neu angelegte Rolle), wird als Rückfallebene die DB-Beschreibung genutzt.
    $rollenErklaerung = getRollenErklaerungen();
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
        <?php echo csrf_field(); ?>
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
        // Fragment (#backstage_letzte_aenderung) an die Action gehaengt, damit die hash-basierte
        // Navigation nach dem POST-Reload wieder auf dieser Seite bleibt statt auf die Startseite
        // zu springen (vorher fehlte das Fragment komplett).
        $ladeAction = ($test_turnier_id==0) ? '/#backstage_letzte_aenderung' : "/?test_turnier_id=$test_turnier_id#backstage_letzte_aenderung";
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
        // Kompakte Darstellung statt <hr>+<p> pro Eintrag - die Theme-Standardabstände (hr: 2.75rem,
        // p: 2rem) summierten sich pro Eintrag zu ~4.75rem Lücke, bei 500 Zeilen kaum überblickbar.
        echo "<div style='text-align:left;font-size:0.85rem;'>";
        while ($rowSystem_Data_DB_Verlauf = $resultSystem_Data_DB_Verlauf->fetch_assoc()) {
            $data_db_verlauf_timestamp = $rowSystem_Data_DB_Verlauf['timestamp'];
            $data_db_verlauf_who = $rowSystem_Data_DB_Verlauf['fk_who'];
            $data_db_verlauf_content = $rowSystem_Data_DB_Verlauf['content'];
            echo "<div style='padding:0.3rem 0;border-bottom:1px solid rgba(255,255,255,0.12);'><b>" . htmlspecialchars($data_db_verlauf_who) . ":</b> " . htmlspecialchars($data_db_verlauf_content) . " <span style='opacity:0.6'>($data_db_verlauf_timestamp)</span></div>";
        }
        echo "</div>";
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
        // Fragment (#backstage_traffic) an die Action gehaengt, damit die hash-basierte Navigation
        // nach dem POST-Reload wieder auf dieser Seite bleibt statt auf die Startseite zu springen
        // (vorher fehlte das Fragment komplett).
        $ladeAction = ($test_turnier_id==0) ? '/#backstage_traffic' : "/?test_turnier_id=$test_turnier_id#backstage_traffic";
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
        // Kompakte Darstellung statt <hr>+<p> pro Eintrag - siehe DB-Verlauf weiter oben, gleiches Problem.
        echo "<div style='text-align:left;font-size:0.85rem;'>";
        while ($row = $result->fetch_assoc()) {
            $traffic_timestamp = $row['timestamp'];
            $traffic_who = $row['fk_who'];
            $traffic_kategorie = $row['traffic_kategorie'];
            $traffic_text = $row['text'];
            echo "<div style='padding:0.3rem 0;border-bottom:1px solid rgba(255,255,255,0.12);'><b>" . htmlspecialchars($traffic_kategorie) . "</b> " . htmlspecialchars($traffic_who) . " " . htmlspecialchars($traffic_text) . " <span style='opacity:0.6'>($traffic_timestamp)</span></div>";
        }
        echo "</div>";
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
    <?php
        // Direkt nach dem Eintragen noch einmal den Status zeigen (fungiert dank der
        // HTML5UP-Artikel-Overlays schon als "Popup, das man wegklickt" - siehe printTeamStatusBox()).
        if ($teamEingeloggt) {
            printTeamStatusBox($conn, $TurnierID, (int)$teamLoginInfo['id'], $turnier_phase_ID);
        }
    ?>
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
    <!-- Alter "Backstage"-Link lag jahrelang tot in einem auskommentierten Legacy-Block weiter unten
         (siehe dort) - hier stattdessen ein neuer, schlanker Link, damit #backstage (Testmodus/
         Account-Registrierung/Besucherzahl) überhaupt wieder erreichbar ist, siehe Chat. -->
    <p class="copyright"><a href="#backstage">Backstage</a></p>
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
                    </audio></NULL><p><hr></p><p class="copyright">Bei Fragen, nutze das <a href="#kontakt">Kontaktformular</a></p class="copyright"><p class="copyright">© Blankiball <a href="#impressum">Impressum</a></p class="copyright"><p class="copyright"><br/>
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

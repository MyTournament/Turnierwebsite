<?php
//IMPORT PHP-DOCS
include_once 'database/db_connection.php'; //Datenbanklogin //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht

include_once 'variables.php'; //Variablen einbinden (Turniernummer) //Wichtig dass das vor Test-Modus-Abfrage kommt weil Test-Modus das Ergebnis braucht


//BULLEREI KOMMT
$sqlWebsite = 'SELECT * FROM `System_Website` WHERE id = '. $websiteId .' ORDER BY ID';
$resultWebsite = $conn->query($sqlWebsite);
while ($rowWebsite = $resultWebsite->fetch_assoc()) {
    $sperrung = $rowWebsite['sperrung'];
}

if($sperrung == 1){
    header("Location: /home.php");
}


include_once 'website_functionalities/load_website.php';
$website_array = determine_domain_id($conn);
$websiteId = $website_array[0]; //TODO: auch die anderen Websites die der Domain zugeordnet sind irgendwie nutzen #Übersicht
if ($websiteId == null){
    echo "WEBSITE nicht gefunden";
}

?>

<!DOCTYPE HTML>
<html>
	<head>
		<title>Blankiball Bierball Turnier</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta name="description" content="Merke dir - Sternburg Bier | 06.09. - 12.09.21">
        <meta name="author" content="Hermann Blankenstein">
		<link rel="stylesheet" href="assets/css/main.css" />
		<noscript><link rel="stylesheet" href="assets/css/noscript.css" /></noscript>
        <link href="images/icon/logo_export_icon/transparent/favicon-96x96.png" rel="shortcut icon" type="image/png">

        <!-- HOME SCREEN LINK -->
        <!--<link rel="stylesheet" href="css/addtohomescreen.css">-->
        <script rel="stylesheet" type="text/css" src="website_functionalities/add_to_homescreen/style/addtohomescreen.css"></script>
        <script src="website_functionalities/add_to_homescreen/src/addtohomescreen.js"></script>
        <script>
            if(
                (("standalone" in window.navigator) && !window.navigator.standalone) //ios
                ||
                ( !window.matchMedia('(display-mode: standalone').matches ) //android
            ){
                addToHomescreen();
            }
        </script>

        <!-- Additionally, include jQuery (necessary for the bookmark script) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	</head>
<body class="is-preload">

<!-- Wrapper -->
<div id="wrapper">

<?php
    echo "<script>console.log('WebsiteId: ' + $websiteId)</script>";
    include_once 'website_functionalities/countdown.php';
    include_once 'website_functionalities/test_turnier_mode.php'; //Test-Modus
    include_once 'database/db_update.php'; //Wichtig dass das nach Test-Modus-Abfrage kommt damit das mit aktualisierter TurnierID passiert
    try{
        db_update($conn, $TurnierID); //db_update.php AUSFÜHREN
    }catch (Exception $e) {
        $message = $e->getMessage();
        print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***$message*** ###</i>";
    }catch (Throwable $e) { //Alles was nicht schon vorher abgefangen wird
        print "<i style='color: red'>### Die Website hat einen kritischen Fehler abgefangen, der höchstwahrscheinlich die Funktionalität der Website einschränkt. Am besten mal Richard oder Jonas Bescheid sagen. Fehlermeldung: ***unbekannter Fehler*** ###</i>";
    }
    foreach (glob("website_print_functions/*.php") as $filename){
        include_once $filename;
    }
    $siteID = 1; // SITE ID (Für CMS)

    $gameEditMode = 0; //
    $gameEditMode = $_POST['gameEditMode'];

    //ANMELDUNG FÜR CMS
    $bn = $_POST["bn"];
    $pw = $_POST["pw"];
    
    $LoggedInWithCMSorHigher = False;
        foreach ($conn->query("SELECT * FROM System_Benutzer_in WHERE fk_rechte <= 5") as $row) {
            if ($bn == $row["Benutzername"] && $pw == $row["Passwort"]) {
                $LoggedInWithCMSorHigher = True;
                $edit_content_mode = False;
            }
        }
    if($LoggedInWithCMSorHigher){
        $edit_content_mode = $_POST["edit_content_mode"];
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
    <div > <!-- class="logo" -->
        <!-- <img src="images/icon/sterni1.png" width="70" height="70" border="10" alt="Home"> -->
        <img src="images/hermann_logo/hanf_radler.png" width="150" height=auto border="10" alt="Home">
    </div>
    <div class="content">
        <div class="inner">
            <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 8, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
        </div>
    </div>
    <nav>
        <ul>
            <li><a href="#info">📚 Info</a></li>
            <li><a href="#regeln">👮🏽‍♀️ Regeln</a></li>
            <li><a href="#teams">👨‍👧‍👧 Teams</a></li>
            <?php 
            //Aktuelle Turnierphase herausfinden - erstmal ID
                $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
                $resultTurnier = $conn->query($sqlTurnier);
                while ($rowTurnier = $resultTurnier->fetch_assoc()) {
                    $turnier_phase_ID = $rowTurnier['fk_turnier_phase'];
                }

                //SPIELPLAN
                if($turnier_phase_ID == 4 ||$turnier_phase_ID == 5 || $turnier_phase_ID == 7 || $turnier_phase_ID == 9 || $turnier_phase_ID == 11){
                    echo"<li><a href='#spielplan'>🎯 Spielplan</a></li>";
                }else{
                    echo"<li class='button disabled'><a href='#spielplan'>🎯 Spielplan</a></li>";
                }
            ?>
            <li><a href="#pausenraum">🏓 Pausenraum</a></li>
            
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
            var datum_aus_db = "<?php echo $countdown_date?>";//"Sep 26, 2022 14:00:00";
            let countDownDate = new Date(datum_aus_db).getTime(); 
            //document.write(datum_aus_db);
            countdown(countDownDate); 
            </script> 
            <!-- "Sep 26, 2022 14:00:00" -->
            <?php //ANMELDUNG
            if($turnier_phase_ID == 3 || $turnier_phase_ID == 11){
                echo"<a href='#anmelden' class='button primary'>Team anmelden</a>";
                cmsPrintSection($websiteId, $siteID, $TurnierID, 19, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); // ANMELDEFRIST
            }else if($turnier_phase_ID == 12){ //WARTELISTE
                echo "<p><i>Hinweis: Der Anmeldezeitraum ist leider schon beendet. Es gibt aber eine Warteliste. Du kannst dein Team also trotzdem noch anmelden, wir können nur nicht versprechen dass wir noch Kapazität haben.</i></p>";
                echo"<a href='#anmelden' class='button primary'>Team anmelden (Warteliste)</a>";
                echo "<br/><br/>";
            } ?>
            <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 5, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
            
            
            
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
    <?php //cmsPrintSection($websiteId, $siteID, $TurnierID, 3, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <?php changeContent($conn, $TurnierID, $_POST['contentID'], $_POST['content'], $_POST['content_style_tag'], $_POST['function'], $_POST['content_order_in_group']); ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- ADD CONTENT -->
<article id="addcontent">
    <h2>Content hinzufügen</h2>
    <p></p>
    <?php addContent($_POST['contentID'], $TurnierID); ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 9, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="allgemeine_info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 4, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="map">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 6, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- REGELN -->
<article id="regeln">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 1, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- TEAMS -->
<article id="teams">
    <?php //ANMELDUNG
    if($turnier_phase_ID == 3 || $turnier_phase_ID == 11){
        echo"<a href='#anmelden' class='button primary'>Team anmelden</a>";
        cmsPrintSection($websiteId, $siteID, $TurnierID, 19, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); // ANMELDEFRIST
    }else if($turnier_phase_ID == 12){ //WARTELISTE
        echo "<p><i>Hinweis: Der Anmeldezeitraum ist leider schon beendet. Es gibt aber eine Warteliste. Du kannst dein Team also trotzdem noch anmelden, wir können nur nicht versprechen dass wir noch Kapazität haben.</i></p>";
        echo"<a href='#anmelden' class='button primary'>Team anmelden (Warteliste)</a>";
    }else{
        echo"<a href='#anmelden' class='button disabled'>Team anmelden</a>";
    } ?>
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 2, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>
<!-- SPIELER INFO - LOGIN -->                       
<article id="spielerinfo_login">
    <a href="#teams" class="button">Zurück zu den Teams</a></br></br>
    <?php 
    $spielerId = $_GET['spielerId'];
    printSpielerInfoLogin($TurnierID, $conn, $spielerId); 
    ?>
    </br></br><a href="#teams" class="button">Zurück zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELER INFO -->                       
<article id="spielerinfo">
    <a href="#teams" class="button">Zurück zu den Teams</a></br></br>
    <?php 
    $spielerId = $_GET['spielerId'];
    printSpielerInfo($TurnierID, $conn, $spielerId); 
    ?>
    </br></br><a href="#teams" class="button">Zurück zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- TEAM INFO -->                       
<article id="teaminfo">
    <a href="#" class="button">Zurück zur Startseite</a></br></br>
    <?php 
    $teamId = $_GET['teamId'];
    printTeamInfo($TurnierID, $conn, $teamId); 
    ?>
    </br></br><a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN ÜBERSICHT -->                       
<article id="spielplan">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 10, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- SPIELPLAN GRUPPEN -->
<article id="gruppen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 28, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#teams" class="button">Zurück zu den Teams</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- GRUPPENPHASE - SPIELPLAN -->
<article id="gruppenphase">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 11, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <?php //printSpielplanGruppenphase($TurnierID, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> 
    <a href="#spielplan" class="button">Zurück zur Übersicht</a>  
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                          
</article>
<!-- Punktetabelle -->
<article id="punktetabelle">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 12, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->                      
    <a href="#gruppenphase" class="button">Zurück zum Spielplan</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>  
</article>
<!-- KO-Phase -->
<article id="kophase">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 13, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->   
    <a href="#spielplan" class="button">Zurück zur Übersicht</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>  
</article>
<!-- Turnierbaum -->
<article id="turnierbaum">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 29, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->   
    <a href="#kophase" class="button">Zurück zur KO-Phase</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>  
</article>
<!-- IMPRESSUM -->
<article id="impressum">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 14, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- datenschutzerklärung -->
<article id="datenschutzerklaerung">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 17, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- INFOS -->
<article id="info">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 15, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- ZEITPLAN -->
<article id="zeitplan">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 20, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="#info" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- FAQ -->
<article id="faq">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 21, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="#info" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- PLATZHALTER -->
<article id="platzhalter">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 16, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####--> 
    <a href="/" class="button">Zurück zur Startseite</a>
    <h5><br/></h5>  
</article>

<!-- schiedsrichter*innen -->
<article id="schiedsrichterinnen">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 24, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#info" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- schiedsrichter*innen -->
<article id="blankiball_simulator">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 30, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <a href="#" class="button">Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
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

<!-- ANMELDEN -->
<article id="anmelden">
    <?php printTeamAnmelden($TurnierID, $test_turnier_id); ?>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="sieger_innen_treppe">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- SIEGER_INNEN TREPPE -->
<article id="rangliste">
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 23, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- BULLEREI KOMMT -->
<article id='bullerei_kommt'>
    <div style='text-align: center'> 
        <?php printBullereiKommt($conn, $websiteId) ?>
        <a href='#' class='button'>Zurück</a>
        <h5><br /></h5>  
    </div>
</article>


<!-- ELEMENTS -->
<?php include_once 'elements.php'; ?>

<!-- PAUSENRAUM -->
<?php include_once 'pausenraum.php'; ?>

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
    <a href="/" class="button">Zurück zur Startseite</a>
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
            <li><a href="#info" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>


<!-- LOGIN - FÜR WORDPRESS -->
<article id="login">
<title>Adressbuch</title>
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
    <p></br></p>

    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 18, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
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
        <h3>Tritt jetzt der Blankiball-Whatsapp-Gruppe bei!</h3>
        <p>... oder der Telegram-Gruppe, falls du kein Whatsapp hast oder Whatsapp kacke findest</p>
        <ul class="actions stacked">
            <li><a class="button" style='background-color: green' href='https://chat.whatsapp.com/CY9qU6l0PsCCaUVT9kx81O'>Offizielle Whatsapp Gruppe</a></li>
            <li><a class="button" style='background-color: green' href='https://chat.whatsapp.com/HjSpKYv7FH28hqStjuXIhV'>Chat-Gruppe</a></li>
            <li><a class="button" style='background-color: blue' href='https://t.me/joinchat/1yfnu49yuYo0OGEy'>Telegram-Gruppe</a></li>
            </br>
            <li><a class="button" href='#'>Zurück zur Startseite</a></li>
        </ul>
    </div>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- logincheck_failure -->
<article id="logincheck_failure">
    <h1>Login fehlgeschlagen</h1>
    <p>Entweder du hast das Kürzel/Passwort falschgeschrieben oder der Anmeldezeitraum ist abgelaufen und du wurdest jetzt in die Warteliste eingefügt. Falls der Anmeldezeitraum noch läuft, versuche entweder noch einmal dein Team anzumelden oder wende dich an kummerkasten@blankiball.de</p>
    <a class="button" href='#'>Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- edit_games_success -->
<article id="edit_games_success">
    <h1>Danke für deinen Eintrag!</h1>
    <p>Dein Eintrag sollte direkt auf der Website sichtbar sein. Falls du Fragen oder Probleme hast, wende dich an kummerkasten@blankiball.de!</p>
    <a class="button" href='#spielplan'>Zum Spielplan</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>		

<!-- edit_games_failure -->
<article id="edit_games_failure">
    <h1>Ups, da ist wohl etwas schiefgelaufen!</h1>
    <p>Vielleicht war dein Passwort falsch, vielleicht hast du nicht die nötigen Rechte. Vielleicht hat Hermann auch einen Fehler gemacht. Falls du Fragen oder Probleme hast, wende dich an kummerkasten@blankiball.de!</p>
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
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 22, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> 

    <!-- Lädt Song runter: style="display: none" autostart='true' <section><embed name='Songtitel' src='assets/audio/kein_bier_mehr_da.opus' border='0' width='152' height='10' style="color: black"  Delay='0' VOLUME='100' loop='true' controls='smallconsole'> </section>  -->
    
    <?php cmsPrintSection($websiteId, $siteID, $TurnierID, 7, $conn, $edit_content_mode, $gameEditMode, $test_turnier_id); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
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

<!-- ########################## -->
<!-- ########  COOKIES  ######### -->
<!-- ########################## -->  
<?php include_once 'assets/js/cookies.js';?>

<script type="text/javascript" id="cookieinfo"
src="/assets/js/cookieinfo.min.js" data-linkmsg="Zeig mir diese 'Cookies' &#9733;" data-moreinfo="javascript:start()" data-onclick="javascript:start()" data-expires="1min Wartezeit bis die Cookies gelöscht werden. Zu verändern in der .js Datei">
</script>          


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

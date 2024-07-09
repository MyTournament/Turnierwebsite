<?php
//########################
//DBUPDATE.PHP AUSFÜHREN
include_once '../dbupdate.php';
//########################

//######################################
//VARIABLEN EINBINDEN (DB_Login und TURNIERNUMMER)
include_once '../variables.php';
//######################################
?>

<?php
  //LOGIN
  $loginkuerzel = $_POST['Login_Kuerzel'];
  $loginpasswort = $_POST['Login_Passwort'];
  $sqlLogin = "SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND kuerzel = '$loginkuerzel' AND password = '$loginpasswort' ORDER BY ID";
  $resultLogin = $conn->query($sqlLogin);
  $successfulLogin = 0; //false
  while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
      $successfulLogin = 1;
  }
  if ($successfulLogin == 0){ //fehlerhafter Login
    $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
    echo "<script type='text/javascript'>alert('$message');</script>";
  }else{
      $begegnungID = $_POST['begegnungId'];
      if($begegnungID){ //wird von neuem addgame aufgerufen, wo man die + Buttons hat
        $heimteamKuerzel = $_POST['heimteam'];
        $auswaertsteamKuerzel = $_POST['auswaertsteam'];
        echo "<script>console.log('Spiel wurde mit + Button eingefügt; BegegnungsID: $begegnungID')</script>";


        
      }else{ //wird von altem registergame aufgerufen, wo man die Teams noch manuell auswählen muss
        //REDUNTANTER CODE
                //Variablen speichern
              //$heimteamKuerzel = "platzhalter";
              $heimteamKuerzel = $_POST['Team1'];
              $auswaertsteamKuerzel = $_POST['Team2'];
              $phase = $_POST['Phase'];
              echo "<script>console.log('Phase: $phase')</script>";
              //TODO: Gruppenphase oder KO-Phase
              $heimteamID = 0;
              $auswaertsteamID = 0;
              //TeamIDs finden
              //Team 1
              $sqlTeam1 = "SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND kuerzel = '$heimteamKuerzel' AND fk_turnier = '$TurnierID' ORDER BY ID";
              $result1 = $conn->query($sqlTeam1);
              while ( !empty( $rowTeam1 = $result1->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                  $heimteamID = $rowTeam1["id"];
                  echo "<script>console.log('Team1 ID gefunden')</script>";
              }
              echo "<script>console.log('Team1 ID: $heimteamID | Kürzel: $heimteamKuerzel')</script>";
              //Team 2
              $sqlTeam2 = "SELECT * FROM `Turnier_Team` WHERE geloescht = 0 AND kuerzel = '$auswaertsteamKuerzel' AND fk_turnier = '$TurnierID' ORDER BY ID";
              $result2 = $conn->query($sqlTeam2); 
              while ($rowTeam2 = $result2->fetch_assoc()) {
                  $auswaertsteamID = $rowTeam2["id"];
                  echo "<script>console.log('Team2 ID gefunden')</script>";
              }
              echo "<script>console.log('Team2 ID: $auswaertsteamID | Kürzel: $auswaertsteamKuerzel')</script>";
              //Passende Begegnung raussuchen -> ID speichern
              $sql = "SELECT * FROM `Turnier_Begegnung` WHERE (ko_finallevel = '$phase' AND fk_heimteam = '$heimteamID' AND fk_auswaertsteam = '$auswaertsteamID')"; //kommutativ -> OR (fk_heimteam = '$auswaertsteamID' AND fk_auswaertsteam = '$heimteamID')
              $result = $conn->query($sql);  
              $begegnungID = 0;
              while ($row = $result->fetch_assoc()) {
                  $begegnungID = $row["id"]; // ID der Begegnung speichern
              }
              if ($begegnungID == 0){ //fehlerhafter Login
                $message = "Leider keine passende Begegnung gefunden. Wende dich gerne an kummerkasten@blankiball.de";
                echo "<script type='text/javascript'>alert('$message');</script>";
              }else{}
              echo "<script>console.log('BegegnungsID: $begegnungID')</script>";
              //Neues Spiel zu dieser Begegnung anlegen
              // TODO Checken ob Begegnung in richtiger Finalstufe ist? Mit austragungsstatus?
              
              //RETURNT DIE ID DES GERADE ANGELEGTEN TEAMS UND GIBT SIE IN KONSOLE AUS
              //$teamID = $conn->insert_id;	//Variable insert_id von conn wird aufgerufen
              //echo '<script>console.log('.$teamID.')</script>';

              $stmt->close();
        }
        
        $begegnungsAction = $_POST['begegnungsAction'];
        if($begegnungsAction == 'add'){
            $stmt = $conn->prepare('INSERT INTO Turnier_Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?)');
            $stmt->bind_param("ssss", $begegnungID, $_POST['Flaschen1'], $_POST['Flaschen2'], $_POST['Login_Kuerzel']); //TODO richtige Reihenfolge
            $stmt->execute();

            //DB-Verlauf
            $a = $_POST['Flaschen1'];
            $b = $_POST['Flaschen2'];
            $c = $_POST['Login_Kuerzel'];
            $content = "INSERT INTO Turnier_Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?) | 'ssss', $begegnungID, $a, $b, $c";
            $stmtDbVerlauf = $conn->prepare('INSERT INTO System_Data_DB_Verlauf (fk_who, content) VALUES (?, ?)');
            $stmtDbVerlauf->bind_param("ss", $loginkuerzel, $content);
            $stmtDbVerlauf->execute();
        }else if($begegnungsAction == 'final'){
            $stmt = $conn->prepare("UPDATE Turnier_Begegnung SET `status` = 5 WHERE id = '$begegnungID'");
            $stmt->execute();
        }else{

        }
        
        
  }
  
  
    //echo 'Der Eintrag war erfolgreich';
//} else {
//    echo 'Ihre Angaben sind fehlerhaft.';
//}
//echo '<a href="adressbuch.html">Zurück</a>';
?> 


<!DOCTYPE HTML>
<!--
 _____ _                  _                       _____                      _       ___   _ _  __     
/  ___| |                | |                     |  ___|                    | |     /   | | (_)/ _|    
\ `--.| |_ ___ _ __ _ __ | |__  _   _ _ __ __ _  | |____  ___ __   ___  _ __| |_   / /| | | |_| |_ ___ 
 `--. \ __/ _ \ '__| '_ \| '_ \| | | | '__/ _` | |  __\ \/ / '_ \ / _ \| '__| __| / /_| | | | |  _/ _ \
/\__/ / ||  __/ |  | | | | |_) | |_| | | | (_| | | |___>  <| |_) | (_) | |  | |_  \___  | | | | ||  __/
\____/ \__\___|_|  |_| |_|_.__/ \__,_|_|  \__, | \____/_/\_\ .__/ \___/|_|   \__|     |_/ |_|_|_| \___|
                                           __/ |           | |                                         
                                          |___/            |_|                                         
-->
<html>
	<head>
		<title>Blankiball Bierball Turnier</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta name="description" content="Merke dir - Sternburg Bier">
        <meta name="author" content="Hermann Blankenstein">
		<link rel="stylesheet" href="../assets/css/main.css" />
		<noscript><link rel="stylesheet" href="../assets/css/noscript.css" /></noscript>
        <link href="../images/icon/sterni1.png" rel="shortcut icon" type="image/png">
	</head>
	<body class="is-preload">

		<!-- Wrapper -->
			<div id="wrapper">

				<!-- Header -->
					<header id="header">
						<div > <!-- class="logo" -->
							<img src="../images/icon/sterni1.png" width="70" height="70" border="10" alt="Home">
						</div>
						<div class="content">
						  <div class="inner">
                <h1>Vielen Dank für deinen Eintrag!</h1>
								<p>Wenn alles geklappt hat (du also gerade keine Fehlermeldung bekommen hast), sollte deine Änderung direkt sichtbar sein. Falls nicht, lade die Seite entweder einmal neu oder schreibe eine Mail an kummerkasten@blankiball.de</a></p>
							</div>
						</div>
						<nav>
						  <ul>
								<li><a href=https://blankiball.de/>Zurück zur Startseite</a></li>
							</ul>
						</nav>         
                        <div class="content">
						  <div class="inner">                   
                    <!--style="display: none"--><section style="display: none"><embed name='Songtitel' src='img/coronasong.mp3' border='0' width='152' height='10' style="color: black" autostart='true' Delay='0' VOLUME='100' loop='true' controls='smallconsole'> </section>                       
                    <h6 style="color: white"><br /></h6>
            
                    <img src="../images/icon/insta.png" width="20" height="20" border="0" alt="Home">
                    <p><a style="color: white" href="https://www.instagram.com/blankiball_official/?hl=de/">@blankiball_official</a><br />
                    <a style="color: white" href="https://www.instagram.com/roehrlitrinkhalme/?hl=de/">@roehrlitrinkhalme</a><br />
                    <a style="color: white" href="https://www.instagram.com/sternburg.brauerei/?hl=de/">@sternburg.official</a><br />
                    <a style="color: white" href="https://www.instagram.com/gretarthouse/?hl=de/">@gretarthouse</a></p>                         
                    <h6 style="color: white"><br /></h6>
                    <p><a style="color: white;font-size:15px;" href="https://open.spotify.com/user/11129583931/playlist/3K13BWkhzAVwHdRM2F6P8Z">Der offizielle S<img src="../images/icon/spoti.png" width="15" height="15" border="5" alt="Home">undtrack zum Turnier<br/></a></p>
							</div>
						</div>

						
						
						
					</header>

				
                
                
                
                
				<!-- Footer -->
					<footer id="footer">
              <p class="copyright">Bei Fragen, wende dich an <a>kummerkasten@blankiball.de</a></p>
              <p class="copyright">&copy; Blankiball. <a href="https://blankiball.de#impressum">Impressum</a></p>  
					</footer>
                    

			</div>

		<!-- BG -->
			<div id="bg"></div>

		<!-- Scripts -->
			<script src="../assets/js/jquery.min.js"></script>
			<script src="../assets/js/browser.min.js"></script>
			<script src="../assets/js/breakpoints.min.js"></script>
			<script src="../assets/js/util.js"></script>
			<script src="../assets/js/main.js"></script>
            
            <!-- COOKIES -->
            
					<script language="JavaScript">
                    /******************************************
                    * Snow Effect Script- By Altan d.o.o. (http://www.altan.hr/snow/index.html)
                    * Visit Dynamic Drive DHTML code library (http://www.dynamicdrive.com/) for full source code
                    * Last updated Nov 9th, 05' by DD. This notice must stay intact for use
                    ******************************************/
                      //Configure below to change URL path to the snow image
                      var snowsrc="../images/icon/cookie.png"
                      // Configure below to change number of snow to render
                      var no = 20;
                      // Configure whether snow should disappear after x seconds (0=never):
                      var hidesnowtime = 0;
                      // Configure how much snow should drop down before fading ("windowheight" or "pageheight")
                      var snowdistance = "pageheight";
                      //0 before start, after that 1
                      var startbool=0;
                      var size=50;
                      function start(){
                        startbool=1;
                        cookie_init();
                      }
                    ///////////Stop Config//////////////////////////////////
                        
                      var ie4up = (document.all) ? 1 : 0;
                      var ns6up = (document.getElementById&&!document.all) ? 1 : 0;
                            function iecompattest(){
                            return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
                            }
                    
                      var dx, xp, yp;    // coordinate and position variables
                      var am, stx, sty;  // amplitude and step variables
                      var i, doc_width = 800, doc_height = 600;
                    
                      if (ns6up) {
                        doc_width = self.innerWidth;
                        doc_height = self.innerHeight;
                      } else if (ie4up) {
                        doc_width = iecompattest().clientWidth;
                        doc_height = iecompattest().clientHeight;
                      }
                      var body = document.body, html = document.documentElement;
                        doc_height = Math.max(   document.body.scrollHeight, document.documentElement.scrollHeight,
                      document.body.offsetHeight, document.documentElement.offsetHeight,
                      document.body.clientHeight, document.documentElement.clientHeight);
                    
                      dx = new Array();
                      xp = new Array();
                      yp = new Array();
                      am = new Array();
                      stx = new Array();
                      sty = new Array();
                    
                      for (i = 0; i < no; ++ i) {
                          
                        dx[i] = 0;                        // set coordinate variables
                        xp[i] = Math.random()*(doc_width-50);  // set position variables
                        yp[i] = Math.random()*doc_height;
                        am[i] = Math.random()*20;         // set amplitude variables
                        stx[i] = 0.02 + Math.random()/10; // set step variables
                        sty[i] = 0.7 + Math.random();     // set step variables
                                    if (ie4up||ns6up) {
                          if (i == 0) {
                            document.write("<div id=\"dot"+ i +"\" style=\"POSITION: absolute; Z-INDEX: "+ i +"; VISIBILITY: hidden; TOP: 15px; LEFT: 15px;\"><a href=\"http://dynamicdrive.com\"><img width= "+size+"px' height='"+size+"px' src='"+snowsrc+"' border=\"0\"><\/a><\/div>");
                          } else {
                            document.write("<div id=\"dot"+ i +"\" style=\"POSITION: absolute; Z-INDEX: "+ i +"; VISIBILITY: hidden; TOP: 15px; LEFT: 15px;\"><img width= '"+size+"px' height='"+size+"px' src='"+snowsrc+"' border=\"0\"><\/div>");
                          }
                        }
                      }
                        function cookie_init(){
                            for (i=0; i<no; i++) document.getElementById("dot"+i).style.visibility="visible"
                      }
                     
                    
                      function snowIE_NS6() {  // IE and NS6 main animation function
                        doc_width = ns6up?window.innerWidth-10 : iecompattest().clientWidth-10;
                                    doc_height=(window.innerHeight && snowdistance=="windowheight")? window.innerHeight : (ie4up && snowdistance=="windowheight")?  iecompattest().clientHeight : (ie4up && !window.opera && snowdistance=="pageheight")? iecompattest().scrollHeight : iecompattest().offsetHeight;
                        for (i = 0; i < no; ++ i) {  // iterate for every dot
                          
                          if(startbool==1){
                              yp[i] += sty[i];
                          if (yp[i] > doc_height-50) {
                            xp[i] = Math.random()*(doc_width-am[i]-30);
                            yp[i] = 0;
                            stx[i] = 0.02 + Math.random()/10;
                            sty[i] = 0.7 + Math.random();
                          }
                          dx[i] += stx[i];
                          document.getElementById("dot"+i).style.top=yp[i]+"px";
                          document.getElementById("dot"+i).style.left=xp[i] + am[i]*Math.sin(dx[i])+"px";
                          }
                        }
                        snowtimer=setTimeout("snowIE_NS6()", 10);
                      }
                    
                            function hidesnow(){
                                    if (window.snowtimer) clearTimeout(snowtimer)
                                    for (i=0; i<no; i++) document.getElementById("dot"+i).style.visibility="hidden"
                            }
                    
                    
                    if (ie4up||ns6up){
                        snowIE_NS6();
                                    if (hidesnowtime>0)
                                    setTimeout("hidesnow()", hidesnowtime*1000)
                                    }
                        
                    //*****************************************************************************
                    //********************END OF THE SNOW SCRIPT***********************************
                    //*****************************************************************************
                    </script>
                        <script type="text/javascript" id="cookieinfo"
                        src="../assets/css/cookieinfo.min.js" data-linkmsg="Show me those 'Cookies'" data-moreinfo="javascript:start()" data-onclick="javascript:start()" data-expires="1min Wartezeit bis die Cookies gelöscht werden. Zu verändern in der .js Datei">
                    	</script>
            

	</body>
</html>

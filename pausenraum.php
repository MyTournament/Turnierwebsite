<?php
function printAchievements($conn){
    echo"<br/><br/>
        <div class='scroll'>";
            echo" <h2>Achievements</h2>
            <ul class='alt'>";
            $sql = "SELECT * FROM Pausenraum_Achievement ORDER BY id DESC";
            $result = $conn->query($sql);
            while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                //AccountNamen herausfinden
                $accountId = $row['fk_account'];
                $sqlAccount = "SELECT * FROM System_Benutzer_in WHERE id = '$accountId'";
                $resultAccount = $conn->query($sqlAccount);
                while ( !empty( $rowAccount = $resultAccount->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                    $accountName = $rowAccount['Benutzername'];
                }
                //Type-Namen herausfinden
                $typeId = $row['fk_type'];
                $sqlType = "SELECT * FROM Pausenraum_Achievement_Type WHERE id = '$typeId'";
                $resultType = $conn->query($sqlType);
                while ( !empty( $rowType = $resultType->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
                    $typeName = $rowType['name'];
                }

                $add_text = $row['add_text'];
                echo "<li>$accountName $typeName $add_text</li>";
            }
            echo"</ul>
        </div>";
}
?>

<!-- Pausenraum -->
<article id="pausenraum">
    <h1>Pausenraum</h1>
    <p></p>
    <?php 
    //ACCOUNT ID
    $sql = "SELECT * FROM System_Benutzer_in WHERE Benutzername = '$bn' AND Passwort = '$pw'";
    $result = $conn->query($sql);
    while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
        $accountId = $row['id'];
    }
    if($_POST['accountId']){
        $accountId = $_POST['accountId'];

        $sql = "SELECT * FROM System_Benutzer_in WHERE id = '$accountId'";
        $result = $conn->query($sql);
        while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
            $bn = $row['Benutzername'];
            $pw = $row['Passwort'];
        }
    }
    
    if($accountId){
        echo "
        <p style='color:green'>Du bist eingeloggt als $bn | <a href='/'>Abmelden</a></p>
        
        <p></p>
        <a href='#sterni_zeahler' class='button primary'>Sterni Zähler <img src='images/icon/sterni1.png' width='20' height='20' border='5' alt='Home'></a>
        <br/><br/>
        <a href='#bierball_locations' class='button primary'>Bierball Locations</a>";
        printAchievements($conn);
        }else{
            echo"
            <a href='#login_standard' class='button primary'>Login</a>
            <a href='#register_account' class='button primary'>Registrieren</a>
            <p></p>
            <a href='#sterni_zeahler' class='button disabled'>Sterni Zähler <img src='images/icon/sterni1.png' width='20' height='20' border='5' alt='Home'></a>
            <br/><br/>
            <a href='#bierball_locations' class='button disabled'>Bierball Locations</a>
            ";
            printAchievements($conn);
    } ?>
    <p></br></p>
    <a href='#' class='button'>Zurück zur Startseite</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- LOGIN für Pausenraum -->
<article id="login_standard">
    <h1>Login</h1>
    <p></br></p>
    <!-- <a href="minigames/sterni_zeahler.php" class="button">Sterni Zähler</a> -->
    <title>Adressbuch</title>
    <div id="LogIn">
    <form action="/#pausenraum" method="POST">
        <input type="text" id="benutzer" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
        <input type="password" id="passwd" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <!--<input type="submit" value="Absenden" style="color: black"/> -->
    <p></br></p>
    <button id="btn_login_Anmelden" value="Anmelden" type="submit">Anmelden</button>
    </form>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- REGISTRIEREN für Pausenraum -->
<article id="register_account">
    <h1>Registrieren</h1>
    <p></br></p>
    <!-- <a href="minigames/sterni_zeahler.php" class="button">Sterni Zähler</a> -->
    <title>Adressbuch</title>
    <div id="LogIn">
    <?php 
    if($test_turnier_id==0){ //Fall: normales Turnier
        echo "<form action='website_datachange/edit_account.php' method='POST'>";
    }else{ //Testturniere
        echo "<form action='website_datachange/edit_account.php?test_turnier_id=$test_turnier_id' method='POST'>";
    }
    ?>
        <input type="text" id="benutzer" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
        <input type="password" id="passwd" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <!--<input type="submit" value="Absenden" style="color: black"/> -->
    <p></br></p>
    <button id="btn_login_Anmelden" name="action" value="register" type="submit">Registrieren</button>
    </form>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- bierball_locations -->
<article id="bierball_locations">
    <h1>Gute Bierball Locations</h1>
    <p></p>
    <ul class="alt">
        <?php
        $sql = 'SELECT * FROM `Pausenraum_Location` ORDER BY id ASC';
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            echo"<hr>";
            $location_id=$row["id"];	
            $location_name=$row["name"];		
            $location_description=$row["description"];
            echo "<h2 style='color: green'>$location_name</h2>";	
            echo "<p style='color: green'>Beschreibung: $location_description</p>";
            //DURCHSCHNITTLICHES RATING
            $sqlBewertung = 'SELECT * FROM `Pausenraum_Location_Bewertung` WHERE `fk_location` = ' . $location_id . ' ORDER BY id desc';
            $resultBewertung = $conn->query($sqlBewertung);
            $durchschnittZaehler = 0;
            $durchschnittNenner = 0;
            while (!empty($rowBewertung = $resultBewertung->fetch_assoc())) {
                $bewertungSterne=$rowBewertung["sterne"];
                $durchschnittZaehler = $durchschnittZaehler + $bewertungSterne;
                $durchschnittNenner++;
            }	
            $durchschnitt = $durchschnittZaehler / $durchschnittNenner;
            echo "<p>Durchschnittliche Bewertung: $durchschnitt &#9733;</p>";	    
            ?>
                <form method='post' action='#bierball_locations_bewertungen'>
                    <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>Top-Bewertungen</button>
                    <input type='hidden' name='location_id' value='<?php echo $location_id ?>'/>
                    <input type='hidden' name='location_name' value='<?php echo $location_name ?>'/>
                    <input type='hidden' name='location_description' value='<?php echo $location_description ?>'/>
                    <input type='hidden' name='accountId' value='<?php echo $accountId ?>'/>
                </form>
            <?php	
        }
        ?>
    </ul>
    <p><br/></p> 
    <form method='post' action='#bierball_locations_hinzufuegen'>
        <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>Location hinzufügen</button>
        <input type='hidden' name='location_id' value='<?php echo $location_id ?>'/>
        <input type='hidden' name='location_name' value='<?php echo $location_name ?>'/>
        <input type='hidden' name='location_description' value='<?php echo $location_description ?>'/>
        <input type='hidden' name='accountId' value='<?php echo $accountId ?>'/>
    </form>
    <ul class="actions">
            <li><a href="#pausenraum" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>

<!-- bierball_locations_bewertungen -->
<article id="bierball_locations_bewertungen">
    <ul class="alt">
        <?php
        if($_POST['location_id']){
            $location_id = $_POST['location_id'];
            $location_name = $_POST['location_name'];
            echo "<h1 style='color: green'>Bewertung für $location_name</h1>";
            echo "<p></p>";
            $location_description = $_POST['location_description'];
            $sqlBewertung = 'SELECT * FROM `Pausenraum_Location_Bewertung` WHERE `fk_location` = ' . $location_id . ' ORDER BY id desc';  
            $resultBewertung = $conn->query($sqlBewertung);
            while (!empty($rowBewertung = $resultBewertung->fetch_assoc())) {
                $bewertungName=$rowBewertung["name"];
                $bewertungDescription=$rowBewertung["description"];
                $bewertungSterne=$rowBewertung["sterne"];
                $bewertungAutorId=$rowBewertung["autor"];
                $bewertungAutor = "unbekannter Autor";
                //Autor herausfinde
                $sqlAutor = 'SELECT * FROM `System_Benutzer_in` WHERE `id` = ' . $bewertungAutorId . ' ORDER BY id desc';  
                $resultAutor = $conn->query($sqlAutor);
                while (!empty($rowAutor = $resultAutor->fetch_assoc())) {
                    $bewertungAutor = $rowAutor["Benutzername"];
                }
                echo "<p>$bewertungName | $bewertungDescription | $bewertungSterne &#9733; | Autor*in: $bewertungAutor </p>";
            }
            ?>
                <form method='post' action='#bierball_locations_bewertungen_hinzufuegen'>
                    <button style='<background-color:yellow;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>Bewertung hinzufügen</button>
                    <input type='hidden' name='location_id' value='<?php echo $location_id ?>'/>
                    <input type='hidden' name='location_name' value='<?php echo $location_name ?>'/>
                    <input type='hidden' name='location_description' value='<?php echo $location_description ?>'/>
                    <input type='hidden' name='accountId' value='<?php echo $accountId ?>'/>
                </form>
            <?php
        }
        ?>
    </ul>
    <ul class="actions">
            <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>

<!-- bierball_locations_bewertungen_hinzufuegen -->
<article id="bierball_locations_hinzufuegen">
    <p></p>
    <ul class="alt">
        <form action="website_datachange/edit_locations.php" method="POST" onSubmit="return checkDatenschutzFuerLocation()">
        <input type="text" name="name" class="Eingabe" placeholder="Location" style="color: white" required><br/>
        <input type="text" name="description" class="Eingabe" placeholder="Beschreibung" style="color: white" required><br/>
        <p></br></p>
        <h4>Kürzel & Passwort</h4>
        <input type="text" id="luerzel" name="bn" class="Eingabe" placeholder="Benutzername" style="color: white" required><br/>
        <input type="password" id="passwort" name="pw" class="Eingabe" placeholder="Passwort" style="color: white" required><br/>
        <h5><br/></h5>
        <title>[ untitled ]</title>                                
        <script type="text/javascript">
            function checkDatenschutzFuerLocation() {
            if (document.getElementById('human_bierball_locations_hinzufuegen').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
        </script> 
        <div>
            <div class="field half">
                <input type="checkbox" id="human_bierball_locations_hinzufuegen" name="human_bierball_locations_hinzufuegen" unchecked>
                <label for="human_bierball_locations_hinzufuegen">Ich stimme der <a style="color: white" href="#datenschutzerklaerung">Datenschutzerklärung</a> zu und möchte diese Bewertung posten...</label>
            </div>
        </div>
        <p></br></p>
        <input type='hidden' name='action' value='new_location'/>
        <input type='hidden' name='accountId' value='<?php echo $accountId ?>'/>
        <input type='hidden' name='TurnierID' value='<?php echo $TurnierID ?>'/>
        <p><button id="btn_login_Absenden" value="Absenden" type="submit">Posten</button></p>
        </form>
    </ul>
    <ul class="actions">
            <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>

<!-- bierball_locations_bewertungen_hinzufuegen -->
<article id="bierball_locations_bewertungen_hinzufuegen">
    <p></p>
    <ul class="alt">
        <?php
        if($_POST['location_id']){
            $location_id = $_POST['location_id'];
            $location_name = $_POST['location_name'];
            $location_description = $_POST['location_description'];
            $accountId = $_POST['accountId'];
            ?>
            <form action="website_datachange/edit_locations.php" method="POST" onSubmit="return checkDatenschutzFuerBewertung()">
            <input type="text" name="name" class="Eingabe" placeholder="Titel" style="color: white" required><br/>
            <input type="number" min="0" max="5" type="text" name="sterne" class="Eingabe" placeholder="sterne &#9733;" style="color: black" required><br/>
            <h4>Sterne &#9733;</h4>
            </br>
            <input type="text" name="description" class="Eingabe" placeholder="beschreibung" style="color: white" required><br/>
            <p></br></p>
            <h4>Kürzel & Passwort</h4>
            <input type="text" id="luerzel" name="bn" class="Eingabe" placeholder="Benutzername" style="color: white" required><br/>
            <input type="password" id="passwort" name="pw" class="Eingabe" placeholder="Passwort" style="color: white" required><br/>
            <h5><br/></h5>
            <title>[ untitled ]</title>                                
            <script type="text/javascript">
                function checkDatenschutzFuerBewertung() {
                if (document.getElementById('human_bierball_locations_bewertungen_hinzufuegen').checked) {
                    return true;
                }
                alert('Du musst unten noch das Häkchen setzen, du Hermann!');
                return false;
            }
            </script> 
            <div>
                <div class="field half">
                    <input type="checkbox" id="human_bierball_locations_bewertungen_hinzufuegen" name="human_bierball_locations_bewertungen_hinzufuegen" unchecked>
                    <label for="human_bierball_locations_bewertungen_hinzufuegen">Ich stimme der <a style="color: white" href="#datenschutzerklaerung">Datenschutzerklärung</a> zu und möchte diese Bewertung posten...</label>
                </div>
            </div>
            <p></br></p>
            <input type='hidden' name='action' value='new_rating'/>
            <input type='hidden' name='fk_location' value='<?php echo $location_id ?>'/>
            <input type='hidden' name='location_name' value='<?php echo $location_name ?>'/>
            <input type='hidden' name='accountId' value='<?php echo $accountId ?>'/>
            <p><button id="btn_login_Absenden" value="Absenden" type="submit">Posten</button></p>
            </form>
            <?php
        }
        
        ?>
    </ul>
    <ul class="actions">
            <li><a href="#bierball_locations" class="button">Zurück</a></li>
    </ul> 
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>

<!-- bierball_locations_bewertungen_hinzufuegen -->
<article id="bierball_locations_bewertungen_hinzufuegen_failure">
    <p></p>
    <h2>Der Login war leider nicht erfolgreich und deine Bewertung wurde nicht eingetragen!</h2>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>                 
</article>

<!-- STERNI ZÄHLER -->
<article id="sterni_zeahler">
    <?php 
    //ACCOUNT ID
    $sql = "SELECT * FROM System_Benutzer_in WHERE Benutzername = '$bn' AND Passwort = '$pw'";
    $result = $conn->query($sql);
    while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
        $accountId = $row['id'];
    }
    if($_POST['accountId']){
        $accountId = $_POST['accountId'];
    }
    //ZEAHLER
    $sql = "SELECT * FROM Pausenraum_Sterni_Zaehler WHERE Pausenraum_Sterni_Zaehler.fk_account = '$accountId'";
    $result = $conn->query($sql);
    $zaehler = 0;
    while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
        $zaehler++;
    }
    echo "<div style='text-align: center'>";
    echo" <h1>Sterni Zähler</h1>
    <h3>Behalte immer den Überblick wann du wie viel Sterni trinkst</h3>
    <p></br></p>";
    function incrementCounter($conn, $accountId){
        $drink_type= "Sterni";
        $sql = "INSERT INTO Pausenraum_Sterni_Zaehler (fk_account, drink_type) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $accountId, $drink_type);
        $stmt->execute();
    }
    if($_SERVER['REQUEST_METHOD']=='POST' && $_POST['action'] =='sterni_zaehler_increment'){
        incrementCounter($conn, $accountId);
        $zaehler++; //weil nach dem Posten immer 1 zu wenig angezeigt wird weil die function ja jetzt erst ausgeführt wird
    } 
    //RESET
    function resetSterniZeahler($conn, $accountId){
        $sql = "DELETE FROM Pausenraum_Sterni_Zaehler WHERE fk_account = (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $accountId);
        $stmt->execute();
    }
    if($_SERVER['REQUEST_METHOD']=='POST' && $_POST['action'] =='sterni_zaehler_reset'){
        resetSterniZeahler($conn, $accountId);
        $zaehler = 0; //weil nach dem Posten immer noch der vorherige Zustand angezeigt wird weil die function ja jetzt erst ausgeführt wird
    } 
    //STATISTIK
    if($zaehler == 1){ //erstes Bier getrunken
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type) VALUES (?, ?)";
        $typeId = 1;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $accountId, $typeId);
        $stmt->execute();
    }
    if($zaehler == 20){ //20. Bier getrunken
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type) VALUES (?, ?)";
        $typeId = 3;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $accountId, $typeId);
        $stmt->execute();
    }
    if($zaehler == 50){ //50. Bier getrunken
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type) VALUES (?, ?)";
        $typeId = 4;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $accountId, $typeId);
        $stmt->execute();
    }
    if($zaehler == 100){ //100. Bier getrunken
        $sql = "INSERT INTO Pausenraum_Achievement (fk_account, fk_type) VALUES (?, ?)";
        $typeId = 5;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $accountId, $typeId);
        $stmt->execute();
    }
    echo " 
    <form style='color:#00FF00' method='post' action='/#sterni_zeahler'>      
        <button style='width:auto; height:auto;'><img src='images/hermann_logo/export.png' width='200' height='200' border='5' alt='Home'></button>   
        <input type='hidden' name='action' value='sterni_zaehler_increment'/>
        <input type='hidden' name='accountId' value='$accountId'/>
        <input type='hidden' name='drink_type' value='$drink_type'/>
    </form>
    ";
    echo "<h2 style='color: red'>Du hast <b>$zaehler</b> Sternis getrunken!</h2>";
    //STATISTIK
    echo "
        <a href='#sterni_zeahler_statistik' class='button primary'>Statistik</a>
        <p></p>
    ";
    
    //RESET
    echo "
    <form style='color:#00FF00' method='post' action='/#sterni_zeahler'>      
        <button style='width:auto; height:auto;'>Reset</button>   
        <input type='hidden' name='action' value='sterni_zaehler_reset'/>
        <input type='hidden' name='accountId' value='$accountId'/>
        <input type='hidden' name='drink_type' value='$drink_type'/>
    </form>
    ";
    
    //echo "<p>Account: $accountId<p>";
    ?>
    <a href="#pausenraum" class="button">Zurück</a>
    </div>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>

<!-- STERNI ZÄHLER -->
<article id="sterni_zeahler_statistik">
    <p></br></p>
    <h2>Deine Sterni-Zähler-Statistik</h2>
    <a href="#sterni_zeahler" class="button">Zurück</a>
    <p></p>
    <ul class="alt">
        <?php
        $sql = "SELECT * FROM Pausenraum_Sterni_Zaehler WHERE fk_account = '$accountId' ORDER BY id desc";
        $result = $conn->query($sql);
        while ( !empty( $row = $result->fetch_assoc() ) ){ // wichtig für Felder, für die es keine Begegnung gibt
            $timestamp = $row['timestamp'];
            $drink_type = $row['drink_type'];
            echo "<li>$timestamp : 1 $drink_type</li>";
        }
        ?>
    </ul>
    <a href="#sterni_zeahler" class="button">Zurück</a>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
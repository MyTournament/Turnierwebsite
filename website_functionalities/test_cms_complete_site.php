<?php
include_once '../database/db_connection.php';
include_once '../variables.php';
foreach (glob("../website_print_functions/*.php") as $filename){
    include_once $filename;
}
//ANMELDUNG FÜR CMS
$bn = $_POST["bn"];
$pw = $_POST["pw"];
$LoggedIn = False;
    foreach ($conn->query("SELECT * FROM System_Benutzer_in WHERE fk_rechte <= 5") as $row) {
        if ($bn == $row["Benutzername"] && $pw == $row["Passwort"]) {
            $LoggedIn = True;
        }
    }
if ($LoggedIn) {
    echo "<h3 style='color: green'><i>Login erfolgreich - Alle Bereiche der Website die sich bearbeiten lassen, werden dir jetzt als Button dargestellt, den du anklicken kannst um den Beitrag zu bearbeiten. Außerdem existieren Buttons, die dich neue Beiträge hinzufügen lassen.</i></h3>";
    echo "<ul class='actions'>
            <li><a style='background-color: green' href='' class='button'>Abmelden</a></li>
        </ul>";                          
}

$siteID = 2; // SITE ID (Für CMS)
cmsPrintSection($siteID, $TurnierID, 19, $conn, $LoggedIn, $gameEditMode, $testTurnierMode);
?>

<!-- LOGIN - FÜR WORDPRESS -->
<article id="login">
    <title>Adressbuch</title>
    <div id="LogIn">
    <h2>Content-Management-System</h2>
    <form action="" method="POST">
        <input type="text" id="benutzer" name="bn" class="Eingabe" placeholder="username" style="color: white" required>
        <input type="password" id="passwd" class="Eingabe" name="pw" placeholder="password" style="color: white" required>
    <!--<input type="submit" value="Absenden" style="color: black"/> -->
    <p></br></p>
    <button id="btn_login_Anmelden" value="Anmelden" type="submit">Anmelden</button>
    </form>
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- CHANGE OR DELETE CONTENT -->
<article id="changecontent">
    <h2>Content ändern</h2>
    <p></p>
    <?php //cmsPrintSection($siteID, $TurnierID, 3, $conn, $LoggedIn, $gameEditMode, $testTurnierMode); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <?php changeContent($conn, $_POST['contentID'], $_POST['content'], $_POST['content_style_tag'], $_POST['function'], $_POST['content_order_in_group']); ?>
    <?php cmsPrintSection($siteID, $TurnierID, 9, $conn, $LoggedIn, $gameEditMode, $testTurnierMode); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
<!-- ADD CONTENT -->
<article id="addcontent">
    <h2>Content hinzufügen</h2>
    <p></p>
    <?php addContent($_POST['contentID']); ?>
    <?php cmsPrintSection($siteID, $TurnierID, 9, $conn, $LoggedIn, $gameEditMode, $testTurnierMode); ?> <!--##### ALS PARAMETER SECTION ID ÜBERGEBEN (Für CMS) #####-->
    <p></br></p> <!-- Abstände unten damit Button auf Handys nicht von Cookiewarnung überdeckt wird -->
    <p></br></p>
</article>
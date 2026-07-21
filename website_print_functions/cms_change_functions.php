<?php

function changeContent($conn, $TurnierID, $contentID, $content, $content_style_tag, $function, $content_order_in_group, $bn = '', $pw = ''){
    // Zugriff auf globale Testturnier-ID absichern
    global $test_turnier_id;
    if (!isset($test_turnier_id)) { $test_turnier_id = 0; }
    //$contentID = $_POST['contentID'];
    //$content = $_POST['content']; //?id=$contentID
    echo "<i>Content mit der ID $contentID wird bearbeitet</i>";
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='website_datachange/edit_content.php' method='POST' onSubmit='return checkAGB5()' class='cms-form'>";
        }else{ //Testturniere
            echo "<form action='website_datachange/edit_content.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGB5()' class='cms-form'>";
        }
        // SICHERHEIT: (int)-Cast fuer die aktuelle Function-ID, bevor sie unten roh in zwei SQL-
        // Strings verkettet wird (WHERE id = .../WHERE NOT id = ...).
        $functionSafe = $function ? (int)$function : null;
        echo"
        <p class='cms-style-tag-warnung'>&#9888; Immer doppelte Anführungszeichen verwenden, niemals einfache!</p>
        <div class='cms-form-field'>
            <textarea type='text' id='changecontent1' name='content' placeholder='Inhalt'  rows='10' style='color: white'>$content</textarea>
            <span class='cms-form-hint'>Inhalt (freilassen wenn Function ausgeführt werden soll)</span>
        </div>

        <div class='cms-form-field'>
        <select name='function' id='function' >";

            if($functionSafe){ //Fall, dass aktuell eine Function ausgewählt ist
                //aktuelle Function als erstes anzeigen
                $sqlFunction = 'SELECT * FROM `CMS_Function` WHERE id = '. $functionSafe .' ORDER BY id';
                $resultFunction = $conn->query($sqlFunction);
                while (!empty ($rowFunction = $resultFunction->fetch_assoc())) {
                    $functionId = $rowFunction['id'];
                    $functionName = $rowFunction['name'];
                    $functionDescription = $rowFunction['description'];
                    echo "<option value=$functionId>#aktuell: $functionName ($functionDescription)</option>";
                }
                echo "<option value='NULL'><i>###keine Function###</i></option>";
                //restliche Functions anzeigen
                $sqlFunction = 'SELECT * FROM `CMS_Function` WHERE NOT id = '. $functionSafe .' ORDER BY id';
                $resultFunction = $conn->query($sqlFunction);
                while (!empty ($rowFunction = $resultFunction->fetch_assoc())) {
                    $functionId = $rowFunction['id'];
                    $functionName = $rowFunction['name'];
                    $functionDescription = $rowFunction['description'];
                    echo "<option value=$functionId>$functionName ($functionDescription)</option>";
                }
            }else{ //Fall, dass aktuell keine Function ausgewählt ist
                echo "<option value='NULL'><i>###keine Function###</i></option>";
                $sqlFunction = 'SELECT * FROM `CMS_Function` ORDER BY id';
                $resultFunction = $conn->query($sqlFunction);
                while (!empty ($rowFunction = $resultFunction->fetch_assoc())) {
                    $functionId = $rowFunction['id'];
                    $functionName = $rowFunction['name'];
                    $functionDescription = $rowFunction['description'];
                    echo "<option value=$functionId>$functionName ($functionDescription)</option>";
                }
            }


        echo"</select>
            <span class='cms-form-hint'>Function (nur ausfüllen falls Function ausgeführt werden soll)</span>
        </div>

        <div class='cms-form-field'>
            <input type='text' id='change_style_tag' name='content_style_tag' class='Eingabe' placeholder='Style-Tag*' value='$content_style_tag' style='color: white'>
            <span class='cms-form-hint'>Style-Tag* (Erklärung unten)</span>
        </div>

        <div class='cms-form-field'>
            <input type='text' id='change_content_order_in_group' name='content_order_in_group' class='Eingabe' placeholder='Reihenfolge auf Seite' value='$content_order_in_group' style='color: white'>
            <span class='cms-form-hint'>Reihenfolge auf Seite</span>
        </div>

        <input type='hidden' name='contentID' value='$contentID'/>
        <input type='hidden' name='TurnierID' value='$TurnierID'/>
        <!-- Man ist beim Bearbeiten schon eingeloggt (CMS-Bearbeitungsmodus) - die Anmeldedaten
             erneut abzufragen war unnoetige Reibung, deshalb jetzt einfach die bereits bekannten
             Werte als verstecktes Feld mitschicken statt sie noch einmal eintippen zu lassen. -->
        <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . "'/>
        <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES, 'UTF-8') . "'/>
        " . csrf_field() . "
    <script type='text/javascript'>
        function checkAGB5() {
            if (document.getElementById('demo-human-changecontent').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
    </script>
    <div class='cms-form-field'>
        <div class='field half'>
            <input type='checkbox' id='demo-human-changecontent' name='demo-human-changecontent' unchecked>
            <label for='demo-human-changecontent'>Ich verstehe, dass veränderte Elemente nicht wiederhergestellt werden können.</label>
        </div>
    </div>
        <input type='submit' name='action' value='Ändern'/>
    </form>
    ";
}


function addContent($contentID, $TurnierID, $bn = '', $pw = ''){
    //$contentID = $_POST['contentID'];
    echo "<form action='website_datachange/edit_content.php' method='POST' onSubmit='return checkAGB4()' class='cms-form'>

            <p class='cms-style-tag-warnung'>&#9888; Immer doppelte Anführungszeichen verwenden, niemals einfache!</p>
            <div class='cms-form-field'>
                <textarea type='text' id='addcontent1' name='content' placeholder='Inhalt'  rows='10' style='color: white' required></textarea>
                <span class='cms-form-hint'>Inhalt</span>
            </div>

            <div class='cms-form-field'>
                <input type='text' id='style_tag1' name='content_style_tag' placeholder='Style-Tag*' value='' class='Eingabe' style='color: white'>
                <span class='cms-form-hint'>Style-Tag* (Erklärung unten)</span>
            </div>

            <input type='hidden' name='contentID' value='$contentID'/>
            <input type='hidden' name='TurnierID' value='$TurnierID'/>
            <!-- Man ist beim Hinzufuegen schon eingeloggt (CMS-Bearbeitungsmodus) - Anmeldedaten
                 nicht erneut abfragen, sondern die bereits bekannten Werte versteckt mitschicken. -->
            <input type='hidden' name='bn' value='" . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . "'/>
            <input type='hidden' name='pw' value='" . htmlspecialchars($pw, ENT_QUOTES, 'UTF-8') . "'/>
            " . csrf_field() . "
    <script type='text/javascript'>
        function checkAGB4() {
            if (document.getElementById('demo-human-addcontent').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du !');
            return false;
        }
    </script>
    <div class='cms-form-field'>
        <div class='field half'>
            <input type='checkbox' id='demo-human-addcontent' name='demo-human-addcontent' unchecked>
            <label for='demo-human-addcontent'>Veröffentlichen</label>
        </div>
    </div>
        <input type='submit' name='action' value='Hinzufügen'/>
    </form>
    ";
}


?>

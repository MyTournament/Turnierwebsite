<?php

function changeContent($conn, $TurnierID, $contentID, $content, $content_style_tag, $function, $content_order_in_group, $bn = '', $pw = ''){
    // Zugriff auf globale Testturnier-ID absichern
    global $test_turnier_id;
    if (!isset($test_turnier_id)) { $test_turnier_id = 0; }
    //$contentID = $_POST['contentID'];
    //$content = $_POST['content']; //?id=$contentID
    echo "<i>Content mit der ID $contentID wird bearbeitet</i>";
        if($test_turnier_id==0){ //Fall: normales Turnier
            echo "<form action='website_datachange/edit_content.php' method='POST' onSubmit='return checkAGB5()'>";
        }else{ //Testturniere
            echo "<form action='website_datachange/edit_content.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGB5()'>";
        }
        echo"    
        <div class='field'>
            <textarea type='text' id='changecontent1' name='content' placeholder='Inhalt'  rows='10' style='color: white'>$content</textarea> 
        </div>

        <p>Inhalt (freilassen wenn Function ausgeführt werden soll)</p>
        <select name='function' id='function' >";
            
            if($function){ //Fall, dass aktuell eine Function ausgewählt ist
                //aktuelle Function als erstes anzeigen
                $sqlFunction = 'SELECT * FROM `CMS_Function` WHERE id = '. $function .' ORDER BY id';
                $resultFunction = $conn->query($sqlFunction);
                while (!empty ($rowFunction = $resultFunction->fetch_assoc())) {
                    $functionId = $rowFunction['id'];
                    $functionName = $rowFunction['name'];
                    $functionDescription = $rowFunction['description'];
                    echo "<option value=$functionId>#aktuell: $functionName ($functionDescription)</option>";					
                }
                echo "<option value='NULL'><i>###keine Function###</i></option>";
                //restliche Functions anzeigen
                $sqlFunction = 'SELECT * FROM `CMS_Function` WHERE NOT id = '. $function .' ORDER BY id';
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
        
        <p>Function (nur ausfüllen falls Function ausgeführt werden soll)</p>
        <input type='text' id='change_style_tag' name='content_style_tag' class='Eingabe' placeholder='Style-Tag* <i>(Erklärung unten)</i>' value='$content_style_tag' style='color: white'>
        <p>Style-Tag* <i>(Erklärung unten)</i></p>
        <input type='text' id='change_content_order_in_group' name='content_order_in_group' class='Eingabe' placeholder='Reihenfolge auf Seite' value='$content_order_in_group' style='color: white'>
        <p>Reihenfolge auf Seite</p>
        <input type='hidden' name='contentID' value='$contentID'/>
        <input type='hidden' name='TurnierID' value='$TurnierID'/>
        <!-- Man ist beim Bearbeiten schon eingeloggt (CMS-Bearbeitungsmodus) - die Anmeldedaten
             erneut abzufragen war unnoetige Reibung, deshalb jetzt einfach die bereits bekannten
             Werte als verstecktes Feld mitschicken statt sie noch einmal eintippen zu lassen. -->
        <input type='hidden' name='bn' value='$bn'/>
        <input type='hidden' name='pw' value='$pw'/>
    <h5><br/></h5>
    <script type='text/javascript'>
        function checkAGB5() {
            if (document.getElementById('demo-human-changecontent').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du Hermann!');
            return false;
        }
        // Zweite, explizite Bestaetigung nur fuers Loeschen (nicht fuers Aendern) - vorher reichte
        // ein einziger Klick auf Loeschen, um einen Baustein unwiderruflich zu entfernen.
        function confirmDelete() {
            return confirm('Diesen Baustein wirklich unwiderruflich löschen? Das kann nicht rückgängig gemacht werden.');
        }
    </script>
    <div>
        <div class='field half'>
            <input type='checkbox' id='demo-human-changecontent' name='demo-human-changecontent' unchecked>
            <label for='demo-human-changecontent'>Ich verstehe, dass gelöschte oder veränderte Elemente nicht wiederhergestellt werden können.</label>
        </div>
    </div>
    <h5><br/></h5>
        <input type='submit' name='action' value='Ändern'/>
        <input type='submit' name='action' value='Löschen' onclick='return confirmDelete();'/>
    </form>
    ";
}


function addContent($contentID, $TurnierID, $bn = '', $pw = ''){
    //$contentID = $_POST['contentID'];
    echo "<form action='website_datachange/edit_content.php' method='POST' onSubmit='return checkAGB4()''>
            
            <div class='field'>
                <textarea type='text' id='addcontent1' name='content' placeholder='Inhalt'  rows='10' style='color: white' required></textarea> 
            </div>

            <!-- <input type='text' id='addcontent1' name='content' placeholder='Inhalt' class='Eingabe' style='color: white' required>-->
            <p>Inhalt</p>
            <input type='text' id='style_tag1' name='content_style_tag' placeholder='Style-Tag* <i>(Erklärung unten)</i>' value='' class='Eingabe' style='color: white'>
            <p>Style-Tag* <i>(Erklärung unten)</i></p>
            <input type='hidden' name='contentID' value='$contentID'/>
            <input type='hidden' name='TurnierID' value='$TurnierID'/>
            <!-- Man ist beim Hinzufuegen schon eingeloggt (CMS-Bearbeitungsmodus) - Anmeldedaten
                 nicht erneut abfragen, sondern die bereits bekannten Werte versteckt mitschicken. -->
            <input type='hidden' name='bn' value='$bn'/>
            <input type='hidden' name='pw' value='$pw'/>

    <h5><br/></h5>
    <script type='text/javascript'>
        function checkAGB4() {
            if (document.getElementById('demo-human-addcontent').checked) {
                return true;
            }
            alert('Du musst unten noch das Häkchen setzen, du !');
            return false;
        }
    </script>  
    <div>
        <div class='field half'>
            <input type='checkbox' id='demo-human-addcontent' name='demo-human-addcontent' unchecked>
            <label for='demo-human-addcontent'>Veröffentlichen</label>
        </div>
    </div>
    <h5><br/></h5>
        <input type='submit' name='action' value='Hinzufügen'/>
    </form>
    ";
}
    

?>

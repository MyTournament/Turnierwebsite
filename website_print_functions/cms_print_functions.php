<?php 
    function cmsPrintSection($websiteId, $siteID, $TurnierID, $section, $conn, $LoggedIn, $gameEditMode, $testTurnierMode){
        //SITE
        //checken ob es eine Site mit dieser ID gibt
        $sqlW = 'SELECT * FROM System_Website WHERE id = '. $websiteId .'';
        $resultW = $conn->query($sqlW);
        //wenn es die Site noch nicht gibt -> erstellen
        if (empty ($rowW = $resultW->fetch_assoc())){
            //TODO: 
        }
        
        //SITE
        //checken ob es eine Site mit dieser ID gibt
        $sqlSite = 'SELECT * FROM CMS_Content_Site WHERE CMS_Content_Site.id = '. $siteID .'';
        $resultSite = $conn->query($sqlSite);
        //wenn es die Site noch nicht gibt -> erstellen
        if (empty ($rowSite = $resultSite->fetch_assoc())){
            $description = "auto generated";
            $stmt = $conn->prepare('INSERT INTO CMS_Content_Section (`id`, `description`) VALUES (?, ?)');
            $stmt->bind_param("sss", $siteID, $description);
            $stmt->execute();
        }

        //SECTION
        //checken ob es eine Section mit dieser ID gibt
        $sqlSection = 'SELECT * FROM CMS_Content_Section WHERE CMS_Content_Section.id = '. $section .'';
        $resultSection = $conn->query($sqlSection);
        //wenn es die Section noch nicht gibt -> erstellen
        if (empty ($rowSection = $resultSection->fetch_assoc())){
            $description = "auto generated";
            $stmt = $conn->prepare('INSERT INTO CMS_Content_Section (`id`, `fk_site`, `description`) VALUES (?, ?, ?)');
            $stmt->bind_param("sss", $section, $siteID, $description);
            $stmt->execute();
        }
        
        //GROUP
        //Alle Gruppen dieser Section suchen
        $sqlGroup = 'SELECT * FROM CMS_Content_Group WHERE CMS_Content_Group.fk_section = '. $section .' ORDER BY CMS_Content_Group.order_in_section ASC';
        $resultGroup = $conn->query($sqlGroup);
        //Sollte es noch keine Gruppe geben, wird die erste erstellt
        if (empty ($rowGroup = $resultGroup->fetch_assoc())){
            $div = "div";
            $desciption = "auto generated";
            $order_in_section = 1;
            $stmt = $conn->prepare('INSERT INTO CMS_Content_Group (fk_section, order_in_section, style_tag, description) VALUES (?, ?, ?, ?)');
            $stmt->bind_param("ssss", $section, $order_in_section, $div, $desciption);
            $stmt->execute();
        }

        //GROUPS PRINTEN
        //Nochmal: Alle Gruppen dieser Section suchen, weil gerade eben schon der erste aus der Queue gepickt wurde
        $sqlGroup2 = 'SELECT * FROM CMS_Content_Group WHERE CMS_Content_Group.fk_section = '. $section .' ORDER BY CMS_Content_Group.order_in_section ASC';
        $resultGroup2 = $conn->query($sqlGroup2);

        //Alle Gruppen durchgehen und Content printen
        while ($rowGroup = $resultGroup2->fetch_assoc()) {
            // TODO: damit man auch oberhalb vom ersten Objekt einfügen kann bzw. wenn noch kein Objekt da ist
            /* echo "<form method='post' action='#addcontent'>
                    <button style='color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;outline: none;border-top: none;background:none;' class='height: 1px;' name='content' value='' class='button primary'>Hier neu einfügen</button>
                    <input type='hidden' name='contentID' value='0'/>
                </form> "; */
            $groupID = $rowGroup['id'];
            $group_style_tag = $rowGroup['style_tag'];
            echo "<$group_style_tag>";

            //CONTENT
            //Alle Contents für diese Group suchen
            $sql = 'SELECT CMS_Content.id as ContentID, CMS_Content.content as ContentContent, CMS_Content.style_tag as StyleTag, CMS_Content.fk_function FROM CMS_Content, CMS_Content_Group WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = '. $section .' AND CMS_Content_Group.id= '. $groupID .' ORDER BY CMS_Content_Group.order_in_section ASC, CMS_Content.order_in_group ASC';
            $result = $conn->query($sql);
            //Falls noch kein Content da -> ersten erstellen
            if (empty ($row = $result->fetch_assoc())){
                $style_tag = "p";
                $content = "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.";
                $order_in_group = 1;
                $stmt = $conn->prepare('INSERT INTO CMS_Content (fk_group, order_in_group, content, style_tag) VALUES (?, ?, ?, ?)');
                $stmt->bind_param("ssss", $groupID, $order_in_group, $content, $style_tag);
                $stmt->execute();
            }

            //CONTENT
            //Nochmal: Alle Contents für diese Group suchen, weil gerade eben schon der erste aus der Queue gepickt wurde
            $sql = 'SELECT CMS_Content.id as ContentID, CMS_Content.content as ContentContent, CMS_Content.style_tag as StyleTag, CMS_Content.fk_function, CMS_Content.order_in_group FROM CMS_Content, CMS_Content_Group WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = '. $section .' AND CMS_Content_Group.id= '. $groupID .' ORDER BY CMS_Content_Group.order_in_section ASC, CMS_Content.order_in_group ASC';
            $result = $conn->query($sql);

            if($test_turnier_id==0){ //Fall: normales Turnier
                echo "<form action='website_datachange/edit_content.php' method='POST' onSubmit='return checkAGB5()'>";
            }else{ //Testturniere
                echo "<form action='website_datachange/edit_content.php?test_turnier_id=$test_turnier_id' method='POST' onSubmit='return checkAGB5()'>";
            }

            while ($row = $result->fetch_assoc()) {
                $content_id=$row["ContentID"];
                $content_style_tag=$row["StyleTag"];
                $content_text=$row["ContentContent"];
                $content_fk_function=$row["fk_function"];
                $content_order_in_group=$row["order_in_group"];
                //entscheiden ob Content ein Text oder eine Function ist
                //FALL FUNCTION
                if($content_text == NULL && $content_fk_function != NULL){
                    //Function-Namen finden
                    $sqlFunction = 'SELECT * FROM CMS_Function WHERE id = '. $content_fk_function .'';
                    $resultFunction = $conn->query($sqlFunction);
                    while ($rowFunction = $resultFunction->fetch_assoc()) {
                        $function = $rowFunction['function'];
                    }
                    //Login überprüfen und je nachdem in Buttons oder normal anzeigen
                    if ($LoggedIn) { //<a style='color: green' href='#'>Bearbeiten</a> |||| color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;outline: none;border-top: none;
                        //BEARBEITEN
                        echo "<form method='post' action='#changecontent' style='display: inline;margin: 0 0 0 0;'>
                                <button style='background-color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;display: inline;' class='height: 1px;' name='content' value='' class='button primary'>&#9998;</button>
                                <input type='hidden' name='contentID' value='$content_id'/>
                                <input type='hidden' name='function' value='$content_fk_function'/>
                                <input type='hidden' name='content_order_in_group' value='$content_order_in_group'/>
                                <p style='display: inline;'>&#8595;$content_order_in_group</p>
                                <!-- <button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'>$content_order_in_group</button> -->
                            </form> "; 
                        //NEU EINFÜGEN
                        echo "<form method='post' action='#addcontent' style='margin: 0 0 0 0;display: inline;'>
                            <button style='background-color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;outline: none;border-top: none;' class='height: 1px;' name='content' value='' class='button primary'>&#8595;+</button>
                            <input type='hidden' name='contentID' value='$content_id'/>
                        </form> ";
                        echo "<h3 style='color: green;margin: 0 0 0 0;'>function -> $function ()</h3>";
                        echo "<hr style='border-top: 3px solid green;margin: 0 0 0 0;'>";
                    }else{
                        //echo "Funktionsausführung aus DB: $function<br>";
                        call_user_func($function, $TurnierID, $conn, $LoggedIn, $gameEditMode, $testTurnierMode);
                        //call_user_func(helloHermann($TurnierID, $conn, $LoggedIn));
                    }
                }else{ //FALL CONTENT
                    //Login überprüfen und je nachdem in Buttons oder normal anzeigen
                    if ($LoggedIn) { //<a style='color: green' href='#'>Bearbeiten</a> |||| color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;outline: none;border-top: none;
                        //BEARBEITEN
                        echo "<form method='post' action='#changecontent' style='display: inline;margin: 0 0 0 0;'>
                                <button style='background-color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;display: inline;' class='height: 1px;' name='content' value='' class='button primary'>&#9998;</button> <!-- width: 100%;height: auto;white-space: normal; -->
                                <input type='hidden' name='contentID' value='$content_id'/>
                                <input type='hidden' name='content' value='$content_text'/>
                                <input type='hidden' name='content_style_tag' value='$content_style_tag'/>
                                <input type='hidden' name='content_order_in_group' value='$content_order_in_group'/>
                                <p style='display: inline;'>&#8595;$content_order_in_group</p>
                                <!-- <button style='background-color:red;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;' class='height: 1px;' name='action' value='' class='button primary'></button> -->
                            </form> "; 
                        //NEU EINFÜGEN
                        echo "<form method='post' action='#addcontent' style='margin: 0 0 0 0;display: inline;'>
                            <button style='background-color: green;padding: 0 0.1rem 0 0.2rem;height: 1rem;line-height: 1rem;border:none;outline: none;border-top: none;' class='height: 1px;' name='content' value='' class='button primary'>&#8595;+</button>
                            <input type='hidden' name='contentID' value='$content_id'/>
                        </form> ";
                        //echo "<$content_style_tag>$content_text</$content_style_tag>";

                        echo"    
                        <div class='field'>
                            <textarea type='text' id='changecontent1' name='content' placeholder='Inhalt'  rows='10' style='color: white'>$content_text</textarea> 
                        </div>
                        ";

                        echo "<hr style='border-top: 3px solid green;margin: 0 0 0 0;'>";
                    }else{
                        if($content_style_tag){
                            echo "<$content_style_tag>$content_text</$content_style_tag>";
                        }else{
                            echo "$content_text";
                        }
                        
                    }
                }
                
            }
            echo"</select>
            <h5><br/></h5>
            <p>Bitte bestätige noch einmal deine Anmeldedaten:</p>
            <input type='text' id='changecontent_Login_bn' name='bn' class='Eingabe' placeholder='Dein Team-Kürzel' style='color: white' required>
            <input type='password' id='changecontent_Login_pw' name='pw' class='Eingabe' placeholder='Dein Team-Passwort' style='color: white' required>
            <h5><br/></h5>                                 
            <script type='text/javascript'>
                function checkAGB5() {
                    if (document.getElementById('demo-human-changecontent').checked) {
                        return true;
                    }
                    alert('Du musst unten noch das Häkchen setzen, du Hermann!');
                    return false;
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
            </form>
            ";
            echo "</$group_style_tag>";
        }
    }
?>
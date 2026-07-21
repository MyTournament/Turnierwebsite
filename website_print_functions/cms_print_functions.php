<?php
    // ================================================================================================
    // GEMEINSAMER TEIL DER CMS-EDIT-TOOLBAR: Hoch/Runter/Hinzufügen/Löschen - ersetzt den früheren
    // einzelnen "Danach einfügen"-Button. Hoch/Runter tauschen die Reihenfolge (order_in_group) mit
    // dem jeweiligen Nachbar-Baustein in derselben Gruppe (Aktion "Verschieben" in edit_content.php).
    // Löschen steht jetzt HIER in der Hauptansicht (nicht mehr im Bearbeiten-Formular) und postet
    // direkt an edit_content.php statt erst ein Formular zu öffnen - braucht deshalb bn/pw + CSRF-
    // Token direkt an dieser Stelle. bn/pw kommen bewusst per global (wie auch test_turnier_id an
    // anderer Stelle in dieser Datei) statt als zusätzlicher Parameter, um nicht alle ~34 Aufrufstellen
    // von cmsPrintSection() in index.php anpassen zu müssen.
    // ================================================================================================
    function cmsToolbarBewegenUndLoeschen($content_id, $TurnierID){
        global $bn, $pw, $test_turnier_id;
        $bnSafe = htmlspecialchars((string)$bn, ENT_QUOTES, 'UTF-8');
        $pwSafe = htmlspecialchars((string)$pw, ENT_QUOTES, 'UTF-8');
        $ttid = isset($test_turnier_id) ? (int)$test_turnier_id : 0;
        $actionUrl = 'website_datachange/edit_content.php' . ($ttid != 0 ? "?test_turnier_id=$ttid" : '');
        echo "
            <form method='post' action='$actionUrl' style='display:inline;margin:0;'>
                <input type='hidden' name='contentID' value='$content_id'/>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='bn' value='$bnSafe'/>
                <input type='hidden' name='pw' value='$pwSafe'/>
                " . csrf_field() . "
                <input type='hidden' name='action' value='Verschieben'/>
                <input type='hidden' name='richtung' value='hoch'/>
                <button type='submit' class='cms-edit-btn' title='Baustein nach oben verschieben'>&#8593;</button>
            </form>
            <form method='post' action='$actionUrl' style='display:inline;margin:0;'>
                <input type='hidden' name='contentID' value='$content_id'/>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='bn' value='$bnSafe'/>
                <input type='hidden' name='pw' value='$pwSafe'/>
                " . csrf_field() . "
                <input type='hidden' name='action' value='Verschieben'/>
                <input type='hidden' name='richtung' value='runter'/>
                <button type='submit' class='cms-edit-btn' title='Baustein nach unten verschieben'>&#8595;</button>
            </form>
            <form method='post' action='#addcontent' style='display:inline;margin:0;'>
                <input type='hidden' name='contentID' value='$content_id'/>
                <button type='submit' class='cms-edit-btn' title='Neuen Baustein direkt danach einfügen'>&#8595;&#43; Danach einfügen</button>
            </form>
            <form method='post' action='$actionUrl' style='display:inline;margin:0;' onsubmit=\"return confirm('Diesen Baustein wirklich unwiderruflich löschen? Das kann nicht rückgängig gemacht werden.');\">
                <input type='hidden' name='contentID' value='$content_id'/>
                <input type='hidden' name='TurnierID' value='$TurnierID'/>
                <input type='hidden' name='bn' value='$bnSafe'/>
                <input type='hidden' name='pw' value='$pwSafe'/>
                " . csrf_field() . "
                <input type='hidden' name='action' value='Löschen'/>
                <button type='submit' class='cms-edit-btn cms-edit-btn-delete' title='Diesen Baustein löschen'>&#128465; Löschen</button>
            </form>
        ";
    }

    function cmsPrintSection($websiteId, $siteID, $TurnierID, $section, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $testTurnierMode){
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
                    if ($LoggedIn) {
                        // CMS-EDIT-TOOLBAR: einheitlich mit dem restlichen Backstage-Look (violett statt
                        // grün), Buttons mit erklärenden Beschriftungen statt kryptischer Symbole allein,
                        // Position klar beschriftet statt nacktem "↓3". CSS siehe main.css (.cms-edit-*).
                        echo "<div class='cms-edit-toolbar'>
                                <form method='post' action='#changecontent'>
                                    <input type='hidden' name='contentID' value='$content_id'/>
                                    <input type='hidden' name='function' value='$content_fk_function'/>
                                    <input type='hidden' name='content_order_in_group' value='$content_order_in_group'/>
                                    <button type='submit' class='cms-edit-btn' title='Diesen Baustein bearbeiten'>&#9998; Bearbeiten</button>
                                </form>
                                <span class='cms-edit-position'>Position $content_order_in_group</span>";
                        cmsToolbarBewegenUndLoeschen($content_id, $TurnierID);
                        echo "</div>";
                        echo "<h3 class='cms-edit-function-label'>Funktion: $function()</h3>";
                        echo "<hr class='cms-edit-divider'>";
                    }else{
                        //echo "Funktionsausführung aus DB: $function<br>";
                        call_user_func($function, $TurnierID, $conn, $LoggedIn, $gameEditMode, $expertenmodus, $testTurnierMode);
                        //call_user_func(helloHermann($TurnierID, $conn, $LoggedIn));
                    }
                }else{ //FALL CONTENT
                    //Login überprüfen und je nachdem in Buttons oder normal anzeigen
                    if ($LoggedIn) {
                        // CMS-EDIT-TOOLBAR: siehe Kommentar im FUNCTION-Zweig oben, gleiches Prinzip.
                        echo "<div class='cms-edit-toolbar'>
                                <form method='post' action='#changecontent'>
                                    <input type='hidden' name='contentID' value='$content_id'/>
                                    <input type='hidden' name='content' value='$content_text'/>
                                    <input type='hidden' name='content_style_tag' value='$content_style_tag'/>
                                    <input type='hidden' name='content_order_in_group' value='$content_order_in_group'/>
                                    <button type='submit' class='cms-edit-btn' title='Diesen Baustein bearbeiten'>&#9998; Bearbeiten</button>
                                </form>
                                <span class='cms-edit-position'>Position $content_order_in_group</span>";
                        cmsToolbarBewegenUndLoeschen($content_id, $TurnierID);
                        echo "</div>";
                        echo "<$content_style_tag>$content_text</$content_style_tag>";
                        echo "<hr class='cms-edit-divider'>";
                    }else{
                        if($content_style_tag){
                            echo "<$content_style_tag>$content_text</$content_style_tag>";
                        }else{
                            echo "$content_text";
                        }
                        
                    }
                }
                
            }
            echo "</$group_style_tag>";
        }
    }
?>

<?php
//WEITERLEITUNG ZURÜCK - mit eventueller TestTurnierID
$test_turnier_id = $_GET['test_turnier_id'];
if($test_turnier_id==NULL){
    header("Location: /");
}else{
    header("Location: /?test_turnier_id=$test_turnier_id");
}

function makeSpaceInOrder($conn, $contentID, $order_in_group, $group, $section, $site){
  //Achtung: ID != ORDER !
  
  //$order_in_group++; //das ist dann genau die Order, wo wir einfügen wollen
  
  //while-Schleife für order_in_group, bis es einen freien Platz gibt -> diese order_in_group speichern
  $orderCounter = $order_in_group+1;
  while(true){
    $sql_order_in_group = "SELECT * FROM CMS_Content, CMS_Content_Group, CMS_Content_Section, CMS_Content_Site ";
    $sql_order_in_group .= "WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = CMS_Content_Section.id AND CMS_Content_Section.fk_site = CMS_Content_Site.id ";
    $sql_order_in_group .= "AND CMS_Content.order_in_group = '$orderCounter' AND CMS_Content.fk_group = '$group'";
    $result_order_in_group = $conn->query($sql_order_in_group);
    if (empty( $row_order_in_group = $result_order_in_group->fetch_assoc() ) ){ //falls empty -> abbrechen
      $orderCounter--;
      break;
    }
    $orderCounter++;
  }
  
  //while-Schleife für order_in_group von hinten und für jedes gefundene Objekt die order_in_group++ bis bei UrsprungsID angekommen
  while(true){
    if($order_in_group == $orderCounter || $orderCounter == 0){ //falls bei alter OrderID angekommen, dann abbrechen
      break;
    }
    $newOrder = $orderCounter+1;
    //ID des aktuellen Contents rausfinden
    $sql = "SELECT CMS_Content.id as ContentID FROM CMS_Content, CMS_Content_Group, CMS_Content_Section, CMS_Content_Site ";
    $sql .= "WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = CMS_Content_Section.id AND CMS_Content_Section.fk_site = CMS_Content_Site.id ";
    $sql .= "AND CMS_Content.order_in_group = '$orderCounter' AND CMS_Content.fk_group = '$group'";
    $result = $conn->query($sql);
    while (!empty( $row = $result->fetch_assoc() ) ){ //falls empty -> abbrechen
      $contentID = $row['ContentID'];
    }
    echo "<script>console.log('ID des Contents der verschoben wird: $contentID, neue OrderID: $newOrder')</script>";
    //Order des Contents mit der gerade gefundenen ID um 1 erhöhen
    /*$stmt = $conn->prepare("UPDATE CMS_Content ";
    $stmt .= "SET order_in_group = '$newOrder' ";
    $stmt .= "FROM CMS_Content, CMS_Content_Group, CMS_Content_Section, CMS_Content_Site ";
    $stmt .= "WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = CMS_Content_Section.id AND CMS_Content_Section.fk_site = CMS_Content_Site.id ";
    $stmt .= "AND fk_group = '$group' AND fk_section = '$section' AND fk_site = '$site' ";
    $stmt .= "AND order_in_group = '$orderCounter'";
    $stmt->execute();*/
    
    //Order des Contents mit der gerade gefundenen ID um 1 erhöhen
    $stmt = $conn->prepare("UPDATE CMS_Content SET CMS_Content.order_in_group = '$newOrder' WHERE CMS_Content.id = '$contentID'");
    $stmt->execute();

    $orderCounter--;
  }

  return ($order_in_group+1);
}

//########################
include_once '../database/db_connection.php';
include_once 'edit_interface.php';
//########################

//LOGIN
$bn = $_POST['bn'];
$pw = $_POST['pw'];
$TurnierID = $_POST['TurnierID'];
$sqlLogin = "SELECT * FROM `System_Benutzer_in` WHERE Benutzername = '$bn' AND Passwort = '$pw' AND fk_rechte <= 5 ORDER BY ID";
$resultLogin = $conn->query($sqlLogin);
$successfulLogin = 0; //false
while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){
    $successfulLogin = 1;
    $rechte = $rowLogin['fk_rechte'];
}
if ($successfulLogin == 0){ //fehlerhafter Login
  $message = "Login leider nicht erfolgreich! Dein Ergebnis wurde nicht eingetragen. Versuch es gerne noch einmal.";
  echo "<script type='text/javascript'>alert('$message');</script>";
}else{
    //Variablen speichern
    $contentID = $_POST['contentID'];
    $content = $_POST['content'];
    $content_style_tag = $_POST['content_style_tag'];
    $function = $_POST['function'];
    $content_order_in_group = $_POST['content_order_in_group'];

    echo "<script>console.log('contentID: $contentID')</script>";
    echo "<script>console.log('content: $content')</script>";
    echo "<script>console.log('function: $function')</script>";

    $action = $_POST['action'];
    echo "<script>console.log('Action: $action')</script>";
    
    
    if ($action == 'Ändern') {
      $sql = "UPDATE CMS_Content SET content = ?, style_tag = ?, fk_function = ?, order_in_group = ? WHERE CMS_Content.id = ?;";
      myDb_execute($conn, $TurnierID, $bn, $sql, array($content, $content_style_tag, $function, $content_order_in_group, $contentID));
    }else if ($action == 'Löschen'){
      $sql = "DELETE FROM CMS_Content WHERE CMS_Content.id = ?;";
      myDb_execute($conn, $TurnierID, $bn, $sql, array($contentID));
    }else if ($action == 'Hinzufügen'){
      //Site, Section, Group & order_in_group des vorherigen Objekt rausfinden -> JOIN
      $sql_order_in_group = "SELECT * FROM CMS_Content, CMS_Content_Group, CMS_Content_Section, CMS_Content_Site ";
      $sql_order_in_group .= "WHERE CMS_Content.fk_group = CMS_Content_Group.id AND CMS_Content_Group.fk_section = CMS_Content_Section.id AND CMS_Content_Section.fk_site = CMS_Content_Site.id ";
      $sql_order_in_group .= "AND CMS_Content.id = '$contentID'";
      $result_order_in_group = $conn->query($sql_order_in_group);
      while ( !empty( $row_order_in_group = $result_order_in_group->fetch_assoc() ) ){
        $order_in_group = $row_order_in_group['order_in_group'];
        $group = $row_order_in_group['fk_group'];
        $section = $row_order_in_group['fk_section'];
        $site = $row_order_in_group['fk_site'];
        echo "<script>console.log('oldOrder: $order_in_group')</script>";
      }

      $order = makeSpaceInOrder($conn, $contentID, $order_in_group, $group, $section, $site);
      echo "<script>console.log('newOrder: $order, group: $group, section: $section, site: $site, content: $content')</script>";
      $sql = "INSERT INTO CMS_Content (fk_group, order_in_group, content, style_tag) VALUES (?, ?, ?, ?)";
      myDb_execute($conn, $TurnierID, $bn, $sql, array($group, $order, $content, $content_style_tag));
    }

}
?> 

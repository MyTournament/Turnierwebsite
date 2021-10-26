<?php
    $result = 'Generated string that is different every time. 20121107160322';
    $filename = 'blankiball_contacts.vcf';
    
    header("Content-Type: text/plain");
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    

  //require_once('config.php');

    //######################################
    include_once '../database/db_connection.php';
    //include_once '../variables.php';
    //######################################

    $TurnierID = $_POST['TurnierID'];
    $spielerId = $_POST['spielerId']; //wird nur übergeben wenn vcard von Spielerinfo aufgerufen wird


  //$sql="SELECT * FROM USER WHERE id=".$_GET['id'];
  //$result=mysql_fetch_row(mysql_query($sql));

// define here all the variable like $name,$image,$company_name & all other
  //header('Content-Type: text/x-vcard; charset=utf-8');  
  //header('Content-Disposition: inline; filename= "blankiball_contacts.vcf"');  

  $vCard;

  if($spielerId != NULL){
    $sqlTelefon = 'SELECT * FROM `Turnier_Spieler_in` WHERE id = '. $spielerId .' ORDER BY ID';
  }else{
    $sqlTelefon = 'SELECT * FROM `Turnier_Spieler_in` WHERE fk_team IN (SELECT id FROM Turnier_Team WHERE fk_turnier = '. $TurnierID .') ORDER BY ID';
  }

  
  $resultTelefon = $conn->query($sqlTelefon);
  while ($rowTelefon = $resultTelefon->fetch_assoc()) {
      $spielername = $rowTelefon['name'];
      $telefonnummer = $rowTelefon['telefonnummer'];
      $teamID = $rowTelefon['fk_team'];

      $sqlTeamname = 'SELECT * FROM `Turnier_Team` WHERE id = '. $teamID .'';
      $resultTeamname = $conn->query($sqlTeamname);
      while ($rowTeamname = $resultTeamname->fetch_assoc()) {
        $teamname = $rowTeamname['name'];
        $teamkuerzel = $rowTeamname['kuerzel'];
      }

        //Turniernamen finden
        $sqlTurnier = 'SELECT * FROM `Turnier_Main` WHERE id = '. $TurnierID .' ORDER BY ID';
        $resultTurnier = $conn->query($sqlTurnier);
        while ($rowTurnier = $resultTurnier->fetch_assoc()) {
            $turnierName = $rowTurnier['name'];
        }
      /*if($image!=""){ 
            $getPhoto               = file_get_contents($image);
            $b64vcard               = base64_encode($getPhoto);
            $b64mline               = chunk_split($b64vcard,74,"\n");
            $b64final               = preg_replace('/(.+)/', ' $1', $b64mline);
            $photo                  = $b64final;
        }*/
        $vCard .= "BEGIN:VCARD\r\n";
        $vCard .= "VERSION:3.0\r\n";
        $vCard .= "FN:" . $spielername . "🏆\r\n";
        $vCard .= "ORG: " . $turnierName . "\r\n";
        $vCard .= "TITLE: ". $teamname . " (" . $teamkuerzel . ")\r\n";

        /*if($email){
            $vCard .= "EMAIL;TYPE=internet,pref:" . $email . "\r\n";
        }*/
        /*if($getPhoto){
            $vCard .= "PHOTO;ENCODING=b;TYPE=JPEG:";
            $vCard .= $photo . "\r\n";
        }*/

        if($telefonnummer){
            $vCard .= "TEL;TYPE=work,voice:" . $telefonnummer . "\r\n"; 
        }

        $vCard .= "END:VCARD\r\n";
  }
/*
  $str = "Some pseudo-random
text spanning
multiple lines";

header('Content-Disposition: attachment; filename="sample.txt"');
header('Content-Type: text/plain'); # Don't use application/force-download - it's not a real MIME type, and the Content-Disposition header is sufficient
header('Content-Length: ' . strlen($str));
header('Connection: close');
*/
header("Content-Length: " . strlen($vCard));
echo $vCard;
exit;
//echo $vCard;
  //$vCard->->write('test.vcf', false);
?>
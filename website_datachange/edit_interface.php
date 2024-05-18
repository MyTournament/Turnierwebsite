<?php
function myDb_execute($conn, $TurnierID, $bn,  $sql, $argArray) {
    //echo "<hr>";
    //echo "NEUE DB-INTERFACE-AUSFUEHRUNG";
    //echo "<br/><br/>SQL: \"$sql\" <br/>";
    $stmt = $conn->prepare($sql);
    
    //zählen wie viele Parameter ich habe
    $argCount = count($argArray); //weil erster Parameter ja der sql Befehl ist
    //echo "Anzahl der Parameter: $argCount <br/>";

    //den "ssss"-String erstellen
    $types = "";
    while($argCount>0){
        $types .= "s";
        $argCount--;
    }
    //echo "types: $types<br/>";
    $stmt->bind_param($types, ...$argArray); //This is called "argument unpacking", and is available since PHP 5.6
    $stmt->execute();


    // TODO andere antwort, falls sql befehl auf der db fehlschlägt 

    $sqlType = substr($sql, 0, 6);
    if($sqlType == "INSERT" || $sqlType == "UPDATE" || $sqlType == "DELETE"){
        $insert_id = $conn->insert_id;

        //DB-VERLAUF
        $values = " |||| bind_param(";
        foreach ($argArray as &$value) {
            //echo "Parameter: $value<br/>";
            $values .= "$value, ";
        }
        $values = substr($values, 0, -2);
        $values .= ")";
        $content = $sql;
        $content .= $values;
        //echo "<br/>DB_VERLAUF:<br/>";
        //echo "content: \"$content\"<br/>";


        $stmtDbVerlauf = $conn->prepare('INSERT INTO System_Data_DB_Verlauf (fk_who, content, fk_website) VALUES (?, ?, ?)');
        $paramArr = [$bn, $content, 1];
        $stmtDbVerlauf->bind_param("sss", ...$paramArr);
        $stmtDbVerlauf->execute();


        //echo "<br/>";
        //printf("SQL: Datenaetze eingefuegt: %d.\n", $stmt->affected_rows); //echo "<br/>";
        //printf("DB-Verlauf: Datenaeetze eingefuegt: %d.\n", $stmtDbVerlauf->affected_rows);
        //echo "<hr>";
        return $insert_id;
    }else if($sqlType == "SELECT"){
        // $stmt -> bind_result($name,$description,$fk_random);
        return $stmt;
    }   
}

//DB_UPDATE
//TODO: den Part hier wieder includen
// TODO keinen absoluten Pfad benutzen, sondern dynamisch Pfad der db_update abrufen. Dadurch kann die Website in verschiedenen Umgebungen laufen. (https://stackoverflow.com/questions/7835948/include-once-relative-path-in-php)
/*include_once '/mnt/web508/d1/34/510124634/htdocs/REDACTED/website/database/db_update.php';
try{
    db_update($conn, $TurnierID);
}catch(Throwable $e){
    //do nothing
    //the error will be sent when index.php is loaded, db_update gets executed then too
}*/

//TODO: BACKUP
// TODO keinen absoluten Pfad benutzen, sondern dynamisch Pfad der db_update abrufen. Dadurch kann die Website in verschiedenen Umgebungen laufen. (https://stackoverflow.com/questions/7835948/include-once-relative-path-in-php)
//include_once '/mnt/web508/d1/34/510124634/htdocs/REDACTED/website/database/db_backup.php';

?>
<?php
include_once '../database/db_backup.php';

function myDb_execute($conn, $TurnierID, $bn, $ort_auf_website, $sql, $argArray) {
    //DATABASE_BACKUP
    echo "<script>console.log('myDb_execute before backup')</script>";
    try{
        backup_main($conn);
    }catch(Throwable $e){
        error_log("backup_main failed: ".$e->getMessage());
        echo "<script>console.error('backup_main failed: " . addslashes($e->getMessage()) . "');</script>";
    }
    echo "<script>console.log('myDb_execute after backup')</script>";

    echo "<script>console.log('myDb_execute start')</script>";
    // SICHERHEIT: NIE die gebundenen Werte ($argArray) loggen/ausgeben - die enthalten je nach Aktion
    // Klartext-Passwoerter (Registrierung, Passwort_Aendern, Team-Anmeldung, ...) und landeten vorher
    // bei JEDER Schreibaktion unconditionally im HTTP-Response (<script>console.log(...)</script>,
    // fuer jede/n einsehbar per Seitenquelltext/Devtools) UND im Server-Error-Log. Das reine SQL-
    // Statement (nur die Vorlage mit "?"-Platzhaltern, keine Werte) ist unbedenklich und bleibt daher
    // fuers Debugging im Server-Log.
    error_log("myDb_execute start | SQL: $sql");
    /*
    //zählen wie viele Parameter ich habe
    $argCount = count($argArray); //weil erster Parameter ja der sql Befehl ist
    //echo "Anzahl der Parameter: $argCount <br/>";

    echo"<script>console.log('myDb_execute Checkpoint 1b')</script>";
    //den "ssss"-String erstellen
    $types = "";
    while($argCount>0){
        $types .= "s";
        $argCount--;
    }
    echo"<script>console.log('myDb_execute Checkpoint 1c')</script>";
    //echo "types: $types<br/>";
    //$stmt->bind_param($types, ...$argArray); //This is called "argument unpacking", and is available since PHP 5.6
    if (!$stmt->bind_param($types, ...$argArray)) {
        die("Fehler beim bind_param: " . $stmt->error);
    }
    echo"<script>console.log('myDb_execute Checkpoint 1d')</script>";

    //$stmt->execute();
    if (!$stmt->execute()) {
        die("execute fehlgeschlagen: " . $stmt->error);
    }
        */
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $err = $conn->error;
            echo "<script>console.error('myDb_execute prepare failed: " . addslashes($err) . "');</script>";
            echo "<pre>SQL-Fehler (prepare): " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</pre>";
            die("SQL-Fehler (prepare): " . $err);
        }
        $argCount = count($argArray);
        $types = str_repeat("s", $argCount);
        $stmt->bind_param($types, ...$argArray);
        $stmt->execute();
        echo "<script>console.log('myDb_execute Checkpoint 2')</script>";
        error_log("myDb_execute ok | SQL: $sql");
    } catch (mysqli_sql_exception $e) {
        // SICHERHEIT: Args/gebundene Werte bewusst NICHT mehr geloggt/ausgegeben (siehe Kommentar
        // weiter oben) - nur noch Fehlermeldung und SQL-Vorlage ohne Werte.
        error_log("Fehler bei SQL: " . $e->getMessage() . " | SQL: " . $sql);
        echo "<script>console.error('myDb_execute SQL error: " . addslashes($e->getMessage()) . "');</script>";
        echo "<pre>SQL-Fehler: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
        die("SQL-Fehler: " . $e->getMessage());
    }


    echo"<script>console.log('myDb_execute Checkpoint 2')</script>";

    // TODO andere antwort, falls sql befehl auf der db fehlschlägt 

    $sqlType = substr($sql, 0, 6);
    if($sqlType == "INSERT" || $sqlType == "UPDATE" || $sqlType == "DELETE"){
        $insert_id = $conn->insert_id;

        //DB-VERLAUF
        // SICHERHEIT: Statements, die ein Passwort-Feld setzen (Registrierung, Team-Anmeldung,
        // Passwort_Aendern, ...) loggen die tatsaechlichen Werte NICHT im Klartext - der DB-Verlauf ist
        // dauerhaft und fuer Admins/Co-Admins einsehbar, damit waeren sonst alle je vergebenen
        // Passwoerter fuer immer im Klartext archiviert gewesen.
        $enthaeltPasswortFeld = (stripos($sql, 'password') !== false) || (stripos($sql, 'passwort') !== false);
        if ($enthaeltPasswortFeld) {
            $values = " |||| bind_param(*** Werte nicht geloggt, Statement enthaelt ein Passwort-Feld ***)";
        } else {
            $values = " |||| bind_param(";
            foreach ($argArray as &$value) {
                //echo "Parameter: $value<br/>";
                $values .= "$value, ";
            }
            $values = substr($values, 0, -2);
            $values .= ")";
        }
        $content = $sql;
        $content .= $values;
        //echo "<br/>DB_VERLAUF:<br/>";
        //echo "content: \"$content\"<br/>";

        echo"<script>console.log('myDb_execute Checkpoint 3')</script>";

        //SQL-Befehle werden mit prepare und bind_param ausgeführt. Dies ist der empfohlene Ansatz, um SQL-Injections zu verhindern
        try {
            $stmtDbVerlauf = $conn->prepare('INSERT INTO System_Data_DB_Verlauf (fk_who, ort_auf_website, content, fk_website) VALUES (?, ?, ?, ?)');
            $paramArr = [$bn, $ort_auf_website, $content, 1];
            $stmtDbVerlauf->bind_param("ssss", ...$paramArr);
            $stmtDbVerlauf->execute();

            echo"<script>console.log('myDb_execute Checkpoint 4')</script>";
        } catch (mysqli_sql_exception $e) {
            echo "<script>console.error('DB_Verlauf insert failed: " . addslashes($e->getMessage()) . "')</script>";
        }

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
/*include_once '/mnt/web508/d1/34/510124634/htdocs/Turnierwebsite/tourna-dev/database/db_update.php';
try{
    db_update($conn, $TurnierID);
}catch(Throwable $e){
    //do nothing
    //the error will be sent when index.php is loaded, db_update gets executed then too
}*/

//TODO: BACKUP
// TODO keinen absoluten Pfad benutzen, sondern dynamisch Pfad der db_update abrufen. Dadurch kann die Website in verschiedenen Umgebungen laufen. (https://stackoverflow.com/questions/7835948/include-once-relative-path-in-php)
//include_once '/mnt/web508/d1/34/510124634/htdocs/Turnierwebsite/tourna-dev/database/db_backup.php';

?>

<?php
$cfg = [
    'db_server' => getenv('DB_SERVER') ?: '',
    'db_username' => getenv('DB_USERNAME') ?: '',
    'db_password' => getenv('DB_PASSWORD') ?: '',
    'db_name' => getenv('DB_NAME') ?: '',
];
$local_cfg_path = __DIR__ . '/../local_secrets/db_connection.local.php';
if (file_exists($local_cfg_path)) {
    $local_cfg = include $local_cfg_path;
    if (is_array($local_cfg)) {
        $cfg = array_merge($cfg, $local_cfg);
    }
}

$dbhost = $cfg['db_server'] ?? '';
$dbuser = $cfg['db_username'] ?? '';
$dbpassword = $cfg['db_password'] ?? '';
$dbname = $cfg['db_name'] ?? '';

//Gibt Pfad von Server aus:
//echo getcwd();


/*
//NORMALE DATEI
$dumpfile = "db_backups/" . $dbname . "_" . date("Y-m-d_H-i-s") . ".sql";
 
//echo "Start dump\n";
exec("mysqldump --user=$dbuser --password=$dbpassword --host=$dbhost $dbname > $dumpfile");
//echo "-- Dump completed -- ";
//echo $dumpfile;
*/

//ZIP DATEI
// TODO keinen absoluten Pfad benutzen, sondern dynamisch Pfad zu db_backups abrufen. Dadurch kann die Website in verschiedenen Umgebungen laufen. (https://stackoverflow.com/questions/7835948/include-once-relative-path-in-php)
$dumpfile = '/mnt/web508/d1/34/510124634/htdocs/Turnierwebsite/tourna-dev/database/db_backups/' . $dbname . '_' . date("Y-m-d_H-i-s") . '.sql.gz';
//Pfad nicht relativ sondern von backstage.php aus!

//echo "Start dump\n";
passthru("mysqldump --user=$dbuser --password=$dbpassword --host=$dbhost $dbname | gzip -c  > $dumpfile");
passthru("mysqldump -u $dbuser -p $dbpassword -h $dbhost $dbname > gzip -c  > dump.sql");

//echo "-- Dump completed -- ";
/*$sql = 'SELECT * FROM `System_Website`';
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $name = $row['name'];
    echo "-- Backup erstellt für website" + $name;
}*/
////echo $dumpfile;


//PER MAIL VERSENDEN
function mail_att($to, $from, $subject, $message, $file) {
    // $to Empfänger
    // $from Absender ("email@domain.de" oder "Name <email@domain.de>")
    // $subject Betreff
    // $message Inhalt der Email
    // $file Pfad zur Datei die versendet werden soll

    $mime_boundary = "-----=" . md5(uniqid(rand(), 1));

    $header = "From: ".$from."\r\n";
    $header.= "MIME-Version: 1.0\r\n";
    $header.= "Content-Type: multipart/mixed;\r\n";
    $header.= " boundary=\"".$mime_boundary."\"\r\n";

    $content = "This is a multi-part message in MIME format.\r\n\r\n";
    $content.= "--".$mime_boundary."\r\n";
    $content.= "Content-Type: text/plain charset=\"iso-8859-1\"\r\n";
    $content.= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $content.= $message."\r\n";

    //Datei anhaengen     
    $name = basename($file);
    $data = chunk_split(base64_encode(file_get_contents($file)));
    $len = filesize($file);
    $content.= "--".$mime_boundary."\r\n";
    $content.= "Content-Disposition: attachment;\r\n";
    $content.= "\tfilename=\"$name\";\r\n";
    $content.= "Content-Length: .$len;\r\n";
    $content.= "Content-Type: application/x-gzip; name=\"".$file."\"\r\n";
    $content.= "Content-Transfer-Encoding: base64\r\n\r\n";
    $content.= $data."\r\n"; 

    return mail($to, $subject, $content, $header);
}  

mail_att("backup_collector@blankiball.de", "backup_collector@blankiball.de", "Backup ".$dumpfile, "Hier kommt mal wieder ein Update. Greetings Hermann", $dumpfile);
//echo "<br>sent to backup_collector@blankiball.de";

//DATEIEN AUS BACKUP VERZEICHNIS WIEDER LÖSCHEN
function deleteFilesFromDirectory($ordnername){
    //überprüfen ob das Verzeichnis überhaupt existiert
    if (is_dir($ordnername)) {
        //Ordner öffnen zur weiteren Bearbeitung
        if ($dh = opendir($ordnername)) {
            //Schleife, bis alle Files im Verzeichnis ausgelesen wurden
            while (($file = readdir($dh)) !== false) {
                //Oft werden auch die Standardordner . und .. ausgelesen, diese sollen ignoriert werden
                if ($file!="." AND $file !="..") {
                    //Files vom Server entfernen
                    unlink("".$ordnername."".$file."");
                }
            }
            //geöffnetes Verzeichnis wieder schließen
            closedir($dh);
        }
    }
}
//Funktionsaufruf - Directory immer mit endendem / angeben
// TODO keinen absoluten Pfad benutzen, sondern dynamisch Pfad zu db_backups abrufen. Dadurch kann die Website in verschiedenen Umgebungen laufen. (https://stackoverflow.com/questions/7835948/include-once-relative-path-in-php)
//deleteFilesFromDirectory("/mnt/web508/d1/34/510124634/htdocs/Turnierwebsite/tourna-dev/database/db_backups/");

?>

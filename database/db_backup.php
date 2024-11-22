<?php
// Einbindung der db_connection.php für die Datenbankzugangsdaten
include_once 'db_connection.php';

//WICHTIG: BERECHTIGUNGEN DER VERZEICHNISSE MÜSSEN STIMMEN (IN TRELLO DOKUMENTIERT)


// Hauptfunktionalität
$backupDir = '/var/www/html/blankiball/automatic_db_backups';
// Backup erstellen und aufräumen
try {
    backupDatabase($conn, $backupDir);
    cleanupBackups($backupDir);
} catch (Exception $e) {
    echo "<script>console.log('Fehler: Datenbankzugangsdaten konnten nicht abgerufen werden.')</script>";
    die();
}



// Backup-Funktion
function backupDatabase($conn, $backupDir) {
    //file_put_contents('backup_log_test.txt', 'Testinhalt');

    // Holen der Zugangsdaten aus der Datenbankverbindung
    //$db_host = $conn->host_info; // Host-Info enthält auch den Port
    //$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0]; // Aktuelle Datenbank
    //$db_user = $conn->user; // Benutzername
    //$db_pass = $conn->passwd; // Passwort
    $db_host = "45.9.63.16";
    $db_user = "backenduser";
    $db_pass = "239wdjSjDEd21";
    $db_name = "blankiball";
    
    // Prüfen, ob die Zugangsdaten korrekt gelesen wurden
    if (!$db_host || !$db_name || !$db_user) {
        die("Fehler: Datenbankzugangsdaten konnten nicht abgerufen werden.");
    }
    
    // Name der Backup-Datei
    //$backupFile = "../../automatic_db_backups/backup_" . $db_name . "_" . date("Ymd_His") . ".sql";
    // Name der Backup-Datei mit vollständigem Pfad
    $backupFile = $backupDir . "/backup_" . $db_name . "_" . date("Ymd_His") . ".sql";

    // Befehl zum Erstellen des MySQL-Dumps
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg(explode(':', $db_host)[0]), // Nur der Host, ohne Port
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_name),
        escapeshellarg($backupFile)
    );

    // Debugging und Logging
    $output = [];
    $return_var = null;

    // Führen Sie den Befehl aus
    exec($command . " 2>&1", $output, $return_var);

    // Loggen Sie die Ausgabe in eine Datei
    //file_put_contents('backup_log.txt', implode("\n", $output));

    // Prüfen, ob das Backup erfolgreich war
    if ($return_var === 0) {
        //echo "<script>console.log('Backup erfolgreich: $backupFile')</script>";
        echo "<script>console.log('Backup erfolgreich')</script>";
    } else {
        echo "<script>console.log('Backup fehlgeschlagen. Details siehe backup_log.txt')</script>";
    }
}






function cleanupBackups($backupDir) {
    $backups = glob($backupDir . "/backup_*.sql");
    $now = time();

    // Konfiguration für die Anzahl der Backups
    $keep = [
        'hourly' => 1000,       // Backups, die weniger als 1 Stunde alt sind
        'daily' => 500,         // Backups, die weniger als 24 Stunden alt sind
        'weekly' => 10,         // Backups der letzten 7 Tage
        'forever' => 1          // Mindestens ein Backup pro Tag
    ];

    // Gruppierte Backups nach Alterskategorien
    $groupedBackups = [
        'hourly' => [],
        'daily' => [],
        'weekly' => [],
        'forever' => []
    ];

    foreach ($backups as $file) {
        preg_match('/backup_(.+?)_(\d{8}_\d{6})\.sql/', basename($file), $matches);
        if (!$matches) {
            continue; // Datei hat nicht das erwartete Namensschema
        }

        $timestamp = DateTime::createFromFormat("Ymd_His", $matches[2])->getTimestamp();
        $age = $now - $timestamp;
        $date = date("Y-m-d", $timestamp);

        if ($age < 3600) { // Weniger als 1 Stunde
            $groupedBackups['hourly'][] = $file;
        } elseif ($age < 86400) { // Weniger als 24 Stunden
            $groupedBackups['daily'][] = $file;
        } elseif ($age < 604800) { // Weniger als 7 Tage
            $groupedBackups['weekly'][$date][] = $file;
        } else { // Älter als 7 Tage
            if (!isset($groupedBackups['forever'][$date])) {
                $groupedBackups['forever'][$date] = $file; // Nur ein Backup pro Tag
            }
        }
    }

    // Backups bereinigen nach Kategorien
    foreach ($groupedBackups as $key => $files) {
        if ($key === 'weekly' || $key === 'forever') {
            continue; // Diese Kategorien werden separat behandelt
        }

        if (count($files) > $keep[$key]) {
            $toDelete = array_slice($files, 0, count($files) - $keep[$key]);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }

    // Wochenbackups reduzieren
    foreach ($groupedBackups['weekly'] as $date => $files) {
        if (count($files) > $keep['weekly']) {
            $toDelete = array_slice($files, 0, count($files) - $keep['weekly']);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }

    // Ältere Backups bereinigen (nur ein Backup pro Tag behalten)
    foreach ($groupedBackups['forever'] as $date => $file) {
        if (!in_array($file, $groupedBackups['hourly']) && !in_array($file, $groupedBackups['daily']) && !isset($groupedBackups['weekly'][$date])) {
            // Ein Backup behalten, alle anderen löschen
            $toDelete = array_slice($groupedBackups['forever'], 1);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
}
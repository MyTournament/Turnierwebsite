<?php
date_default_timezone_set('Europe/Berlin');
echo "<script>console.log('Aktuelle PHP-Zeitzone: " . date_default_timezone_get() . "')</script>";


// Einbindung der db_connection.php für die Datenbankzugangsdaten
include_once 'db_connection.php';

//WICHTIG: BERECHTIGUNGEN DER VERZEICHNISSE MÜSSEN STIMMEN (IN TRELLO DOKUMENTIERT)

// Konfigurierbares Backup-Limit-Array: Definiert, wie viele Backups pro Zeitperiode behalten werden sollen.
$backupLimits = [
    'hourly' => 100,     // < 1 Stunde - Behalte bis zu 100 Backups, gleichmäßig über die letzten 60 Minuten verteilt.
    'daily' => 100,      // < 24 Stunden - Behalte bis zu 100 Backups, gleichmäßig über die letzten 24 Stunden verteilt.
    'weekly' => 100,     // < 7 Tage - Behalte bis zu 100 Backups, gleichmäßig über die letzten 7 Tage verteilt.
    'monthly' => 3,      // < 1 Jahr - Für Backups älter als 7 Tage, aber jünger als ein Jahr: Behalte 3 Backups pro Tag, gleichmäßig verteilt.
    'yearly' => 1,       // < 3 Jahre - Für Backups älter als 1 Jahr, aber jünger als 3 Jahre: Behalte 1 Backup pro Monat.
    'older' => 1         // > 3 Jahre - Für Backups älter als 3 Jahre: Behalte 1 Backup pro Woche (das neueste in jeder Woche).
];

// Hauptfunktionalität
$backupDir = '/var/www/html/REDACTED/automatic_db_backups';
// Backup erstellen und aufräumen
try {
    backupDatabase($conn, $backupDir);
    cleanupBackups($backupDir, $backupLimits);
} catch (Exception $e) {
    echo "<script>console.log('Fehler: Datenbankzugangsdaten konnten nicht abgerufen werden.')</script>";
    die();
}



/*
Details zur Backup-Bereinigung:

1. 'hourly':
   - Backups, die weniger als 1 Stunde alt sind.
   - Bis zu 100 Backups werden beibehalten, verteilt über die letzten 60 Minuten.

2. 'daily':
   - Backups, die zwischen 1 Stunde und 24 Stunden alt sind.
   - Bis zu 100 Backups werden beibehalten, gleichmäßig über die letzten 24 Stunden verteilt.

3. 'weekly':
   - Backups, die zwischen 24 Stunden und 7 Tagen alt sind.
   - Bis zu 100 Backups werden beibehalten, gleichmäßig über die letzten 7 Tage verteilt.

4. 'monthly':
   - Backups, die zwischen 7 Tagen und 1 Jahr alt sind.
   - Bis zu 3 Backups pro Tag werden beibehalten, gleichmäßig verteilt.

5. 'yearly':
   - Backups, die zwischen 1 Jahr und 3 Jahren alt sind.
   - Behalte 1 Backup pro Monat.

6. 'older':
   - Backups, die älter als 3 Jahre sind.
   - Behalte 1 Backup pro Woche (das neueste in jeder Woche).

Die Bereinigung erfolgt gleichmäßig über den jeweiligen Zeitraum, um eine sinnvolle Verteilung der Backups zu gewährleisten.
*/


// Backup-Funktion
function backupDatabase($conn, $backupDir) {
    //file_put_contents('backup_log_test.txt', 'Testinhalt');

    // Holen der Zugangsdaten aus der Datenbankverbindung
    //$db_host = $conn->host_info; // Host-Info enthält auch den Port
    //$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0]; // Aktuelle Datenbank
    //$db_user = $conn->user; // Benutzername
    //$db_pass = $conn->passwd; // Passwort
    $db_host = "REDACTED";
    $db_user = "REDACTED";
    $db_pass = "REDACTED";
    $db_name = "REDACTED";
    
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



function cleanupBackups($backupDir, $backupLimits) {
    $backups = glob($backupDir . "/backup_*.sql");
    $now = time();

    $groups = [
        'hourly' => [],
        'daily' => [],
        'weekly' => [],
        'monthly' => [],
        'yearly' => [],
        'older' => []
    ];

    foreach ($backups as $file) {
        preg_match('/backup_(.+?)_(\d{8}_\d{6})\.sql/', basename($file), $matches);
        if (!$matches) {
            echo "<script>console.log('Ungültiges Backup-Format: $file')</script>";
            continue;
        }

        $timestamp = DateTime::createFromFormat("Ymd_His", $matches[2])->getTimestamp();
        $age = $now - $timestamp;

        echo "<script>console.log('Backup: $file | Timestamp: $timestamp | Alter: $age Sekunden')</script>";

        if ($age < 3600) {
            $groups['hourly'][] = ['file' => $file, 'timestamp' => $timestamp];
        } elseif ($age < 86400) {
            $groups['daily'][] = ['file' => $file, 'timestamp' => $timestamp];
        } elseif ($age < 604800) {
            $groups['weekly'][] = ['file' => $file, 'timestamp' => $timestamp];
        } elseif ($age < 31536000) {
            $groups['monthly'][] = ['file' => $file, 'timestamp' => $timestamp];
        } elseif ($age < 157680000) {
            $groups['yearly'][] = ['file' => $file, 'timestamp' => $timestamp];
        } else {
            $groups['older'][] = ['file' => $file, 'timestamp' => $timestamp];
        }
    }

    echo "<script>console.log('Gruppierte Backups: " . json_encode($groups) . "')</script>";

    foreach (['hourly', 'daily', 'weekly'] as $key) {
        // Validierung des Wertes in $backupLimits
        if (!isset($backupLimits[$key]) || !is_numeric($backupLimits[$key]) || $backupLimits[$key] <= 0) {
            echo "<script>console.log('Ungültiger Backup-Limitwert für Gruppe: $key')</script>";
            continue;
        }

        if (count($groups[$key]) > $backupLimits[$key]) {
            $groups[$key] = evenlyDelete($groups[$key], $backupLimits[$key], $key);
        }
    }

    foreach ($groups['monthly'] as $files) {
        if (count($files) > $backupLimits['monthly']) {
            evenlyDelete($files, $backupLimits['monthly'], 'monthly');
        }
    }

    foreach ($groups['yearly'] as $files) {
        if (count($files) > $backupLimits['yearly']) {
            evenlyDelete($files, $backupLimits['yearly'], 'yearly');
        }
    }

    foreach ($groups['older'] as $files) {
        if (count($files) > $backupLimits['older']) {
            evenlyDelete($files, $backupLimits['older'], 'older');
        }
    }
}



function evenlyDelete($files, $keep, $group) {
    // Sortiere Dateien nach Zeitstempel (älteste zuerst)
    usort($files, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    $total = count($files);

    // Schutz vor leeren oder ungültigen $keep-Werten
    if (!is_numeric($keep) || $keep <= 0) {
        echo "<script>console.log('Ungültiger Wert für $keep in Gruppe: $group. Keine Dateien werden gelöscht.')</script>";
        return $files;
    }

    $deleteCount = $total - $keep;

    // Debugging-Ausgabe
    echo "<script>console.log('Gruppe: $group | Total: $total | Behalte: $keep | Löschen: $deleteCount')</script>";

    // Wenn keine Löschung erforderlich ist
    if ($deleteCount <= 0) {
        echo "<script>console.log('Keine Löschung erforderlich für Gruppe: $group')</script>";
        return $files; // Rückgabe der unveränderten Liste
    }

    // Vermeide Division durch Null und Berechnung des Schritts
    $step = max(1, (int) floor($total / $deleteCount));
    $deleted = 0;

    foreach ($files as $index => $file) {
        // Wenn genügend Dateien gelöscht wurden, abbrechen
        if ($deleted >= $deleteCount) {
            break;
        }

        // Nur Dateien mit einem bestimmten Schritt löschen
        if ($index % $step === 0) {
            unlink($file['file']);
            echo "<script>console.log('Gelöschtes Backup ($group): {$file['file']}')</script>";
            $deleted++;
        }
    }

    // Rückgabe der verbleibenden Dateien
    return array_values(array_filter($files, fn($file) => file_exists($file['file'])));
}

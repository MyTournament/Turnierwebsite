<?php
    $websiteId = 1; // Always stays 1 for this site.
    include_once 'database/db_connection.php';

    // Current tournament
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 1 ORDER BY order_on_website DESC';
    $result = $conn->query($sql);
    $TurnierID = 0;
    while ($row = $result->fetch_assoc()) {
        $TurnierID = $row['id'];
        $TurnierName = $row['name'];
        break;
    }

    // Test tournaments
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 2 ORDER BY id DESC';
    $result = $conn->query($sql);
    $testTurniere = array();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $testTurnierID = $row['id'];
        $testTurnierName = $row['name'];
        $testTurniere[$index] = array($index, $testTurnierID, $testTurnierName);
        $index++;
    }

    // Past tournaments
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 3 ORDER BY startdatum DESC, order_on_website DESC, id DESC';
    $result = $conn->query($sql);
    $history = array();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $turnierID = $row['id'];
        $turnierName = $row['name'];
        $history[$index] = array($index, $turnierID, $turnierName);
        $index++;
    }
?>

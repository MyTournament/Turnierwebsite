<?php
function getBenutzerListe($conn) {

    $stmt = $conn->prepare("SELECT * FROM `System_Benutzer_in` ORDER BY ID");
    $stmt->execute();
    $result = $stmt->get_result();

    return $result;
}
function getTeamsListeFuerTurnier($conn, $TurnierID){
    $stmt = $conn->prepare("SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = '$TurnierID' ORDER BY ID");
    $stmt->execute();
    $result = $stmt->get_result();

    return $result;
}
?>
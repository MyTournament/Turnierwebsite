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

// Mehrfach-Rollen-System: ein Benutzer bekommt seine Rechte ausschließlich über die Rollen,
// die ihm in System_Benutzer_in_Relation_Rolle zugeordnet sind (System_Benutzer_in_Rolle
// definiert pro Rolle die einzelnen Rechte-Flags). Ein Benutzer kann mehrere Rollen gleichzeitig
// haben, effektive Rechte = ODER-Verknüpfung aller zugewiesenen Rollen-Flags.
// fk_rechte auf System_Benutzer_in wird hierfür nicht mehr verwendet.
function getUserRollenInfo($conn, $bn, $pw) {
    $stmt = $conn->prepare("SELECT id FROM System_Benutzer_in WHERE Benutzername = ? AND Passwort = ?");
    $stmt->bind_param("ss", $bn, $pw);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { return null; }

    $benutzerId = (int)$row['id'];
    $rolleIds = [];

    try {
        $stmtRel = $conn->prepare("SELECT fk_rolle FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ?");
        $stmtRel->bind_param("i", $benutzerId);
        $stmtRel->execute();
        $resultRel = $stmtRel->get_result();
        while ($rowRel = $resultRel->fetch_assoc()) {
            $rolleIds[] = (int)$rowRel['fk_rolle'];
        }
    } catch (Throwable $e) {
        // Relation-Tabelle nicht erreichbar - der Benutzer hat dann schlicht keine Rolle
    }
    $rolleIds = array_values(array_unique($rolleIds));

    $flagNamen = ['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'];
    $flags = array_fill_keys($flagNamen, false);
    $rollenNamen = [];

    if (count($rolleIds) > 0) {
        try {
            $platzhalter = implode(',', array_fill(0, count($rolleIds), '?'));
            $types = str_repeat('i', count($rolleIds));
            $stmtRollen = $conn->prepare("SELECT * FROM System_Benutzer_in_Rolle WHERE id IN ($platzhalter)");
            $stmtRollen->bind_param($types, ...$rolleIds);
            $stmtRollen->execute();
            $resultRollen = $stmtRollen->get_result();
            while ($rowRolle = $resultRollen->fetch_assoc()) {
                $rollenNamen[(int)$rowRolle['id']] = $rowRolle['name'];
                foreach ($flagNamen as $f) {
                    if (isset($rowRolle['rechte_' . $f]) && (int)$rowRolle['rechte_' . $f] === 1) {
                        $flags[$f] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Rollen-Tabelle nicht erreichbar - keine Rollen-Details verfügbar
        }
    }

    return [
        'benutzer_id' => $benutzerId,
        'rolle_ids' => $rolleIds,
        'rollen_namen' => $rollenNamen,
        'flags' => $flags,
        'ist_admin' => in_array(1, $rolleIds, true),
        'ist_co_admin' => in_array(2, $rolleIds, true),
    ];
}
?>
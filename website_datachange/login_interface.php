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

// Mehrfach-Rollen-System: ein Benutzer kann mehrere Rollen aus System_Benutzer_in_Rolle haben
// (zugeordnet über System_Benutzer_in_Relation_Rolle), zusätzlich zur alten fk_rechte-Rolle
// aus System_Benutzer_in (die als "Legacy-Rolle" weiterhin mitzählt, u.a. für Abwärtskompatibilität).
// Effektive Rechte = ODER-Verknüpfung aller Rollen-Flags.
// Falls die neuen Tabellen (noch) nicht existieren oder anders aufgebaut sind, wird auf das alte
// Schwellenwert-Verhalten zurückgefallen, damit der Login nie komplett kaputtgeht.
function getUserRollenInfo($conn, $bn, $pw) {
    $stmt = $conn->prepare("SELECT id, fk_rechte FROM System_Benutzer_in WHERE Benutzername = ? AND Passwort = ?");
    $stmt->bind_param("ss", $bn, $pw);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) { return null; }

    $benutzerId = (int)$row['id'];
    $legacyRolle = (int)$row['fk_rechte'];
    $rolleIds = [$legacyRolle];

    try {
        $stmtRel = $conn->prepare("SELECT fk_rolle FROM System_Benutzer_in_Relation_Rolle WHERE fk_benutzer_in = ?");
        $stmtRel->bind_param("i", $benutzerId);
        $stmtRel->execute();
        $resultRel = $stmtRel->get_result();
        while ($rowRel = $resultRel->fetch_assoc()) {
            $rolleIds[] = (int)$rowRel['fk_rolle'];
        }
    } catch (Throwable $e) {
        // Relation-Tabelle (noch) nicht vorhanden/nicht erreichbar - nur Legacy-Rolle verwenden
    }
    $rolleIds = array_values(array_unique($rolleIds));

    $flagNamen = ['neue_admins','neue_co_admins','restliche_rollen_vergeben','turnier_settings','cms','teams','backstage','alle_spiele'];
    $flags = array_fill_keys($flagNamen, false);
    $rollenNamen = [];
    $rollenTabelleOk = true;

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
        $rollenTabelleOk = false;
    }

    if (!$rollenTabelleOk) {
        // Fallback: altes Schwellenwert-Verhalten nachbilden
        $flags['cms'] = ($legacyRolle <= 5);
        $flags['teams'] = ($legacyRolle <= 10);
        $flags['backstage'] = ($legacyRolle <= 15);
        $flags['alle_spiele'] = ($legacyRolle <= 20);
        $flags['turnier_settings'] = ($legacyRolle == 1 || $legacyRolle == 2);
        $flags['neue_admins'] = ($legacyRolle == 1);
        $flags['neue_co_admins'] = ($legacyRolle == 1);
        $flags['restliche_rollen_vergeben'] = ($legacyRolle == 1 || $legacyRolle == 2);
    }

    return [
        'benutzer_id' => $benutzerId,
        'legacy_rolle' => $legacyRolle,
        'rolle_ids' => $rolleIds,
        'rollen_namen' => $rollenNamen,
        'flags' => $flags,
        'ist_admin' => in_array(1, $rolleIds, true),
        'ist_co_admin' => in_array(2, $rolleIds, true),
    ];
}
?>
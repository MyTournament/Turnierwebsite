<?php
include_once '../database/rollen_definitionen.php';

// ================================================================================================
// ACCOUNT-AVATARE: kuratierte Emoji-Liste statt Bilder-Upload (kein Missbrauchspotential/Speicher-
// platz-Thema, sofort auf jeder Website konsistent). WICHTIG: die Spalte `avatar` auf
// `System_Benutzer_in` existiert nicht zwangsläufig schon in jeder Datenbank (diese Website hat kein
// Migrations-System, Schema-Änderungen werden manuell nachgezogen - siehe Chat) - deshalb läuft JEDER
// Zugriff auf diese Spalte hier bewusst über eine eigene, defensiv try/catch-gekapselte Query statt
// über ein `SELECT avatar, ...`/`UPDATE ... SET avatar = ...` mitten in einer der zentralen,
// sicherheitskritischen Login-Abfragen weiter unten - schlägt die Spalte fehl (weil sie fehlt), bricht
// dadurch niemals der Login/die Registrierung, sondern nur das rein kosmetische Avatar-Feature selbst.
// ================================================================================================
function getProfilAvatarOptionen() {
    return ['🍺','🍻','🎉','🎊','🏆','🥇','🥈','🥉','⚽','🏐','🏀','🎯','🎱','😎','🤙','🕺','💃','🐻','🦁','🐯','🐸','🐵','🦄','🐶','🐱','🦊','🐼','🐨','🐔','🦉','🦅','🐺','🐙','🦀','🌟','🔥','⚡','🎸','🎧','🚀','🛸','👑','🎃','👻','🤖','🥳','🍀'];
}

// Liefert den anzuzeigenden Avatar für einen Account: der explizit gespeicherte Wert hat Vorrang,
// sonst deterministisch aus der Nutzer-ID abgeleitet (wirkt "zufällig zugelost" - siehe Chat -, ohne
// dass bei der Registrierung extra ein Schreibzugriff auf die evtl. noch fehlende Spalte nötig wäre,
// und bleibt über mehrere Seitenaufrufe hinweg stabil statt bei jedem Laden neu zu würfeln).
function ermittleAnzeigeAvatar($conn, $benutzerId) {
    $optionen = getProfilAvatarOptionen();
    $gespeichert = null;
    try {
        $stmt = $conn->prepare("SELECT avatar FROM System_Benutzer_in WHERE id = ?");
        $stmt->bind_param("i", $benutzerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $gespeichert = $row['avatar'] ?? null;
    } catch (Throwable $e) {
        // Spalte "avatar" (noch) nicht vorhanden - Fallback unten greift automatisch.
    }
    if (!empty($gespeichert) && in_array($gespeichert, $optionen, true)) { return $gespeichert; }
    return $optionen[((int)$benutzerId) % count($optionen)];
}

// Speichert einen neu gewählten Avatar - $avatar MUSS vorher gegen getProfilAvatarOptionen() geprüft
// sein (siehe Eigenes_Profil_Speichern in edit_account.php), damit hier nicht ungeprüft beliebige
// Zeichenketten in die DB gelangen. Gibt true/false zurück, wirft aber nie - siehe Kommentar oben.
function nutzerAvatarSpeichern($conn, $benutzerId, $avatar) {
    try {
        $stmt = $conn->prepare("UPDATE System_Benutzer_in SET avatar = ? WHERE id = ?");
        if ($stmt === false) { return false; }
        $stmt->bind_param("si", $avatar, $benutzerId);
        return $stmt->execute();
    } catch (Throwable $e) {
        return false;
    }
}

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

// ================================================================================================
// TEAM-LOGIN (Gegenstück zu getUserRollenInfo() für Teams statt Accounts)
// ================================================================================================
// Prüft Kürzel+Passwort gegen Turnier_Team fürs aktuelle Turnier (bzw. Testturnier, je nachdem was
// als $TurnierID übergeben wird) und gibt bei Erfolg die komplette Team-Zeile zurück (u.a. id,
// bearbeitungsrechte), sonst null - sicherer Default wie bei getUserRollenInfo().
function getTeamLoginInfo($conn, $TurnierID, $bn, $pw) {
    if ($bn === null || $pw === null || $bn === '' || $pw === '') { return null; }
    $stmt = $conn->prepare("SELECT * FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ? AND kuerzel = ? AND password = ?");
    $tid = (int)$TurnierID;
    $stmt->bind_param("iss", $tid, $bn, $pw);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

// ================================================================================================
// GENAUERE LOGIN-FEHLERMELDUNGEN: unterscheidet "Kürzel/Benutzername existiert nicht" von
// "existiert, aber Passwort falsch" - reine Existenz-Checks (ignorieren das Passwort komplett),
// damit ein fehlgeschlagener Login-Versuch gezielt sagen kann, WAS falsch war.
// ================================================================================================
function teamKuerzelExistiertInTurnier($conn, $TurnierID, $bn) {
    if ($bn === null || $bn === '') { return false; }
    $stmt = $conn->prepare("SELECT id FROM Turnier_Team WHERE geloescht = 0 AND fk_turnier = ? AND kuerzel = ?");
    $tid = (int)$TurnierID;
    $stmt->bind_param("is", $tid, $bn);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}
// ================================================================================================
// TEAM-LOGIN GEGEN VERGANGENE TURNIERE (type = 3) PRÜFEN - auf ausdrücklichen Wunsch, siehe Chat:
// wenn Kürzel+Passwort im AKTUELLEN Turnier nicht existieren, aber exakt zu einem Team aus einem
// vergangenen Turnier derselben Website passen, soll der Login-Fehler gezielt auf "Vergangene
// Turniere" verweisen statt nur zu sagen "gibt es nicht" - das Team hat ja mitgespielt, nur eben
// nicht in diesem Turnier. Reiner Lese-Check, ändert nichts, wird nur für die Fehlermeldung gebraucht.
// ================================================================================================
function getTeamLoginInfoAusVergangenemTurnier($conn, $websiteId, $bn, $pw) {
    if ($bn === null || $pw === null || $bn === '' || $pw === '') { return null; }
    $stmt = $conn->prepare("SELECT t.id, t.fk_turnier, m.name AS turnier_name FROM Turnier_Team t JOIN Turnier_Main m ON m.id = t.fk_turnier WHERE t.geloescht = 0 AND m.fk_website = ? AND m.type = 3 AND t.kuerzel = ? AND t.password = ? ORDER BY m.startdatum DESC LIMIT 1");
    $wid = (int)$websiteId;
    $stmt->bind_param("iss", $wid, $bn, $pw);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}
function benutzernameExistiert($conn, $bn) {
    if ($bn === null || $bn === '') { return false; }
    $stmt = $conn->prepare("SELECT id FROM System_Benutzer_in WHERE Benutzername = ?");
    $stmt->bind_param("s", $bn);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

// ================================================================================================
// ZENTRALE FUNKTION DES NEUEN MEHRFACH-ROLLEN-SYSTEMS (ersetzt das alte fk_rechte-Schwellenwert-System)
// ================================================================================================
// Ein Benutzer bekommt seine Rechte ausschließlich über die Rollen, die ihm in
// System_Benutzer_in_Relation_Rolle zugeordnet sind (System_Benutzer_in_Rolle definiert pro Rolle
// die einzelnen Rechte-Flags: neue_admins, neue_co_admins, restliche_rollen_vergeben,
// turnier_settings, cms, teams, backstage, alle_spiele). Ein Benutzer kann mehrere Rollen
// gleichzeitig haben, effektive Rechte = ODER-Verknüpfung aller zugewiesenen Rollen-Flags.
// fk_rechte auf System_Benutzer_in wird hierfür nicht mehr verwendet und soll in einer der
// nächsten Versionen aus der Datenbank entfernt werden. Gibt bei falschem Login oder wenn die
// Rollen-Tabellen (noch) nicht erreichbar sind bewusst "keine Rechte" zurück (sicherer Default),
// nie einen Fallback auf die alte fk_rechte-Logik.
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
            // Nur noch id+name aus der DB (reine Anzeige-Metadaten) - die eigentlichen Rechte-Flags
            // kommen jetzt aus getRollenFlags() (rollen_definitionen.php), nicht mehr aus den
            // rechte_*-Spalten dieser Tabelle.
            $stmtRollen = $conn->prepare("SELECT id, name FROM System_Benutzer_in_Rolle WHERE id IN ($platzhalter)");
            $stmtRollen->bind_param($types, ...$rolleIds);
            $stmtRollen->execute();
            $resultRollen = $stmtRollen->get_result();
            while ($rowRolle = $resultRollen->fetch_assoc()) {
                $rollenNamen[(int)$rowRolle['id']] = $rowRolle['name'];
            }
        } catch (Throwable $e) {
            // Rollen-Tabelle nicht erreichbar - keine Rollen-Namen verfügbar
        }
    }
    foreach ($rolleIds as $rid) {
        $rollenFlags = getRollenFlags($rid);
        foreach ($flagNamen as $f) {
            if (!empty($rollenFlags['rechte_' . $f])) { $flags[$f] = true; }
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
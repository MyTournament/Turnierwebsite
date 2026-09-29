<?php
function insert_traffic($conn, $websiteID, $bn, $kategorie, $text) {
    //Kategorien:
    //  1 = Button
    //  2 = Teilnahmeurkunde
    //  3 = Website-Besuch (nur noch Altdaten - neue Aufrufe landen in System_Seitenaufruf)
    $sql = "INSERT INTO `System_Traffic` (`fk_who`, `fk_kategorie`, `text`, `fk_website`) VALUES (?, ?, ?, ?);";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $bn, $kategorie, $text, $websiteID); //This is called "argument unpacking", and is available since PHP 5.6
    $stmt->execute();
}

// ================================================================================================
// SEITENAUFRUFE & GERÄTE
// ================================================================================================
// Jeder Seitenaufruf wird mit Zeitpunkt und einer anonymen Geräte-ID gespeichert. Die Geräte-ID ist
// ein zufälliger Wert in einem Cookie (kein Bezug zu IP, Name o.ä.) - dadurch lassen sich
// "Aufrufe insgesamt" (inkl. Reloads/wiederkehrender Geräte) und "verschiedene Geräte" getrennt
// auswerten. Bekannte Bots/Crawler werden nicht gezählt.

const GERAET_COOKIE = 'bb_geraet';

function traffic_geraet_id() {
    $id = $_COOKIE[GERAET_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        $id = bin2hex(random_bytes(16));
    }
    // Bei jedem Aufruf erneuern, damit die ID bei regelmäßigen Besuchen nicht abläuft
    if (!headers_sent()) {
        setcookie(GERAET_COOKIE, $id, [
            'expires' => time() + 60 * 60 * 24 * 730,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $id;
}

function traffic_ist_bot() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') { return true; }
    return (bool)preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python-requests|headless|lighthouse/i', $ua);
}

function insert_seitenaufruf($conn, $websiteId) {
    if (traffic_ist_bot()) { return; }
    $geraetId = traffic_geraet_id();
    try {
        $stmt = $conn->prepare("INSERT INTO `System_Seitenaufruf` (`fk_website`, `geraet_id`) VALUES (?, ?)");
        $websiteIdInt = (int)$websiteId;
        $stmt->bind_param("is", $websiteIdInt, $geraetId);
        $stmt->execute();
    } catch (Throwable $e) {
        // Tabelle noch nicht angelegt o.ä. - das Zählen darf die Seite nie blockieren
    }
}

// Liefert Kennzahlen und Zeitreihen (Tag/Woche/Monat) für die Statistik-Seite. Altdaten aus
// System_Traffic (Kategorie 3) zählen als Aufrufe mit, Geräte gibt es dort nicht (NULL).
function get_besuchsstatistik($conn, $websiteId) {
    $w = (int)$websiteId;
    $stat = [
        'aufrufe_gesamt' => 0, 'geraete_gesamt' => 0,
        'aufrufe_heute' => 0, 'geraete_heute' => 0,
        'aufrufe_7' => 0, 'geraete_7' => 0,
        'aufrufe_30' => 0, 'geraete_30' => 0,
        'geraete_seit' => null,
        'reihen' => ['tag' => [], 'woche' => [], 'monat' => []],
    ];
    try {
        $row = $conn->query("SELECT COUNT(*) AS c FROM `System_Traffic` WHERE fk_kategorie = 3 AND fk_website = $w")->fetch_assoc();
        $altAufrufe = (int)($row['c'] ?? 0);

        $row = $conn->query("SELECT COUNT(*) AS a, COUNT(DISTINCT geraet_id) AS g, MIN(zeitpunkt) AS seit,
                SUM(zeitpunkt >= CURDATE()) AS a_heute,
                COUNT(DISTINCT CASE WHEN zeitpunkt >= CURDATE() THEN geraet_id END) AS g_heute,
                SUM(zeitpunkt >= NOW() - INTERVAL 7 DAY) AS a_7,
                COUNT(DISTINCT CASE WHEN zeitpunkt >= NOW() - INTERVAL 7 DAY THEN geraet_id END) AS g_7,
                SUM(zeitpunkt >= NOW() - INTERVAL 30 DAY) AS a_30,
                COUNT(DISTINCT CASE WHEN zeitpunkt >= NOW() - INTERVAL 30 DAY THEN geraet_id END) AS g_30
            FROM `System_Seitenaufruf` WHERE fk_website = $w")->fetch_assoc();
        $stat['aufrufe_gesamt'] = $altAufrufe + (int)$row['a'];
        $stat['geraete_gesamt'] = (int)$row['g'];
        $stat['geraete_seit'] = $row['seit'];
        foreach (['heute', '7', '30'] as $k) {
            $stat['aufrufe_' . $k] = (int)$row['a_' . $k];
            $stat['geraete_' . $k] = (int)$row['g_' . $k];
        }
        // Altdaten auch in die Zeitfenster einrechnen (nur Aufrufe)
        $row = $conn->query("SELECT SUM(`timestamp` >= CURDATE()) AS h, SUM(`timestamp` >= NOW() - INTERVAL 7 DAY) AS s,
                SUM(`timestamp` >= NOW() - INTERVAL 30 DAY) AS d
            FROM `System_Traffic` WHERE fk_kategorie = 3 AND fk_website = $w")->fetch_assoc();
        $stat['aufrufe_heute'] += (int)$row['h'];
        $stat['aufrufe_7'] += (int)$row['s'];
        $stat['aufrufe_30'] += (int)$row['d'];

        $formate = ['tag' => '%Y-%m-%d', 'woche' => '%x-W%v', 'monat' => '%Y-%m'];
        foreach ($formate as $name => $fmt) {
            $sql = "SELECT p, SUM(aufrufe) AS aufrufe, SUM(geraete) AS geraete FROM (
                        SELECT DATE_FORMAT(`timestamp`, '$fmt') AS p, COUNT(*) AS aufrufe, NULL AS geraete
                        FROM `System_Traffic` WHERE fk_kategorie = 3 AND fk_website = $w GROUP BY p
                        UNION ALL
                        SELECT DATE_FORMAT(zeitpunkt, '$fmt') AS p, COUNT(*) AS aufrufe, COUNT(DISTINCT geraet_id) AS geraete
                        FROM `System_Seitenaufruf` WHERE fk_website = $w GROUP BY p
                    ) x GROUP BY p ORDER BY p";
            $res = $conn->query($sql);
            while ($r = $res->fetch_assoc()) {
                $stat['reihen'][$name][] = [
                    'p' => $r['p'],
                    'a' => (int)$r['aufrufe'],
                    'g' => $r['geraete'] === null ? null : (int)$r['geraete'],
                ];
            }
        }
    } catch (Throwable $e) {
        // Statistik ist optional - bei fehlender Tabelle einfach die bisher gesammelten Werte zeigen
    }
    return $stat;
}
?>

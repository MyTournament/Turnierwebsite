<?php
// ================================================================================================
// ZENTRALE SESSION-COOKIE-HÄRTUNG - MUSS VOR JEDEM session_start() DER WEBSITE EINGEBUNDEN WERDEN.
// ================================================================================================
// Vorher liefen alle session_start()-Aufrufe der Website (13 Stellen, jede für sich unabhängig) mit
// den PHP-Standardeinstellungen - kein HttpOnly/Secure/SameSite garantiert, hängt komplett von der
// jeweiligen Server-php.ini ab. Diese Datei setzt die Cookie-Parameter EINMALIG zentral, bevor der
// erste session_start() der Anfrage passiert (session_set_cookie_params() wirkt nur, wenn es vor dem
// Start der Session aufgerufen wird - ruft man es später auf, passiert nichts mehr).
//
// - httponly: true  -> das Session-Cookie ist per JavaScript (document.cookie) nicht auslesbar, auch
//   nicht durch ein eventuelles XSS. Schützt zwar nicht vor serverseitigem CSRF/direkten Requests,
//   aber verhindert, dass eingeschleuster JS-Code die Session direkt stiehlt.
// - secure: automatisch true, wenn die Anfrage über HTTPS lief (sonst würde das Cookie bei HTTP gar
//   nicht mehr mitgeschickt und die Website bräche auf einer HTTP-only-Umgebung) - erkennt HTTPS auch
//   hinter einem Reverse-Proxy (X-Forwarded-Proto), falls vorhanden.
// - samesite: 'Lax' - blockiert das Cookie bei den meisten Cross-Site-Requests (z.B. eine fremde
//   Seite, die per <img>/<form> automatisch zur Turnierwebsite postet), erlaubt aber normale
//   Linkklicks/Navigation von außen weiterhin.
// ================================================================================================
if (session_status() === PHP_SESSION_NONE) {
    $sbHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $sbHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
?>

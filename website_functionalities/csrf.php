<?php
// ================================================================================================
// CSRF-SCHUTZ (Synchronizer-Token-Pattern) - EIN Token pro Session, nicht pro Formular/Request.
// ================================================================================================
// Warum pro Session statt Einmal-Token: viele Formulare hier (Team-Anmeldung mit Bild-Captcha,
// Turnier-Settings mit "bestätigen"-Checkbox) werden nach einem fehlgeschlagenen Versuch mit
// denselben Formulardaten neu angezeigt bzw. bleiben lange offen (mehrere Browser-Tabs, Zurück-
// Button) - ein Einmal-Token würde all das kaputt machen. Ein Session-Token bleibt einfach für die
// gesamte Sitzung gültig, schützt aber trotzdem zuverlässig gegen klassisches CSRF (eine fremde
// Seite kennt den Token der Zielperson nicht und kann ihn auch nicht auslesen - Same-Origin-Policy).
//
// Deckt bewusst nicht jedes einzelne Formular der Website ab (das wären hunderte Stellen in einem
// gewachsenen, nicht auf ein zentrales Formular-Rendering ausgelegten Codebase) - sondern gezielt
// die Formulare mit dem höchsten Schadenspotenzial bei erfolgreichem CSRF: Nutzermanagement
// (Rollen vergeben/entziehen, Passwort/Benutzername ändern, Login als User, Nutzer löschen,
// Registrierung), Turnier-Settings/Turnierphase, CMS-Inhalte bearbeiten, Begegnungen anlegen/
// sperren. Diese laufen alle über die gemeinsamen Render-Funktionen (tsTextFeld/tsCheckboxFeld,
// changeContent/addContent) bzw. wenige direkt in index.php eingebettete Formulare - dadurch reicht
// es, csrf_field() an diesen wenigen Stellen einzufügen, um praktisch alle sicherheitsrelevanten
// Formulare abzudecken.
// ================================================================================================

function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . "'>";
}

// Prüft den mitgeschickten Token gegen den Session-Token. Gibt bei fehlendem/falschem Token false
// zurück - die aufrufende Datei entscheidet selbst, wie sie darauf reagiert (i.d.R. Abbruch mit
// Fehlermeldung/Redirect statt hartem die(), damit die Nutzung so nah wie möglich am bisherigen
// Verhalten bleibt).
function csrf_verify() {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $eingereicht = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $erwartet = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
    if ($erwartet === '' || $eingereicht === '') { return false; }
    return hash_equals($erwartet, $eingereicht);
}
?>

<?php
// ================================================================================================
// ZENTRALE ROLLEN-DEFINITION - HIER UND NUR HIER ändern, was jede Rolle darf.
// ================================================================================================
// Ersetzt die rechte_*-Spalten der Tabelle System_Benutzer_in_Rolle als Quelle der Wahrheit für die
// RECHTE einer Rolle. Grund: die Datenbank-Spalten sind aus einer reinen SQL-Ansicht heraus kaum zu
// lesen (eine Zeile aus lauter Einsen/Nullen) und ließen sich außerdem nur per direktem SQL-Zugriff
// ändern. Diese Datei hier kann direkt per Code-Änderung angepasst werden - keine SQL nötig.
//
// Name, Anzeige-Reihenfolge (hierarchie_ebene) und Beschreibungstext einer Rolle bleiben weiterhin in
// der Tabelle System_Benutzer_in_Rolle (dafür ist eine DB-Tabelle sinnvoll, das ändert sich selten und
// betrifft nur Anzeige, keine Rechte). Welche ROLLE welchem NUTZER zugewiesen ist, bleibt ebenfalls in
// der Datenbank (System_Benutzer_in_Relation_Rolle) - das ändert sich ja laufend über das
// Nutzermanagement und kann naturgemäß nicht im Code stehen.
//
// Die rechte_*-Spalten in System_Benutzer_in_Rolle werden ab jetzt von keinem Code mehr gelesen und
// können bei Gelegenheit aus der Tabelle entfernt werden (nicht zwingend nötig, nur zur Aufräumung).
//
// Rollen-IDs müssen exakt mit den id-Werten in System_Benutzer_in_Rolle übereinstimmen.
function getRollenDefinitionen() {
    return [
        // Admin: hat wirklich alles, inkl. neue Admins anlegen.
        1 => [
            'rechte_neue_admins' => true, 'rechte_neue_co_admins' => true, 'rechte_restliche_rollen_vergeben' => true,
            'rechte_turnier_settings' => true, 'rechte_cms' => true, 'rechte_teams' => true,
            'rechte_backstage' => true, 'rechte_alle_spiele' => true,
        ],
        // Co-Admin: alles wie Admin, außer neue Admins anlegen und Passwörter anderer einsehen/ändern
        // (Letzteres wird nicht über ein Flag hier geregelt, sondern explizit im Code auf "ist_admin"
        // geprüft - siehe Nutzermanagement/edit_account.php Passwort_Aendern).
        2 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => true, 'rechte_restliche_rollen_vergeben' => true,
            'rechte_turnier_settings' => true, 'rechte_cms' => true, 'rechte_teams' => true,
            'rechte_backstage' => true, 'rechte_alle_spiele' => true,
        ],
        // Autor*in: darf ausschließlich die Website-Inhalte im CMS bearbeiten.
        5 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => true, 'rechte_teams' => false,
            'rechte_backstage' => false, 'rechte_alle_spiele' => false,
        ],
        // Moderator*in: darf Teams bearbeiten, dafür automatisch auch Zugang zum Backstage-Bereich.
        10 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => true,
            'rechte_backstage' => true, 'rechte_alle_spiele' => false,
        ],
        // Backstage-Zugang: breiter Zugriff auf die meisten Turnier-Settings/Teams-Funktionen, aber
        // NICHT auf das, was explizit Admin/Co-Admin vorbehalten bleibt (Begegnungen bearbeiten seit
        // der Rechte-Neuordnung ausgenommen - das hängt jetzt an turnier_settings, siehe unten -,
        // Neues Turnier anlegen, Nutzermanagement, Verlauf/Traffic, Passwörter).
        15 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => true, 'rechte_cms' => false, 'rechte_teams' => true,
            'rechte_backstage' => true, 'rechte_alle_spiele' => false,
        ],
        // Schiedsrichter*in: darf beliebige Spielergebnisse eintragen/ändern, hat aber KEINEN Zugang
        // zum Backstage-Bereich (kein violetter Balken, kein Settings-/Infos-/CMS-Button).
        20 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => false,
            'rechte_backstage' => false, 'rechte_alle_spiele' => true,
        ],
        // Benutzer*in: Standardrolle für selbst registrierte Accounts, noch überhaupt keine Rechte.
        30 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => false,
            'rechte_backstage' => false, 'rechte_alle_spiele' => false,
        ],
    ];
}

// Flags für eine einzelne Rolle (alles false, falls die Rollen-ID hier nicht eingetragen ist - z.B.
// bei einer neu angelegten, in dieser Datei noch nicht ergänzten Rolle: sicherer Default statt eines
// Fehlers).
function getRollenFlags($rolleId) {
    $alle = getRollenDefinitionen();
    return $alle[(int)$rolleId] ?? [
        'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
        'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => false,
        'rechte_backstage' => false, 'rechte_alle_spiele' => false,
    ];
}

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
        // Turniermaster: darf Teams bearbeiten und hat dafür automatisch auch Zugang zum Backstage-
        // Bereich (also immer mindestens das, was Backstage-Zugang auch kann, plus Schreibrechte) -
        // damit betreibt diese Rolle über den Code (nicht dieses Flag) auch die laufende Turnier-
        // Organisation: Gruppen für die Gruppenphase generieren, Einzug ins KO-System, Green-Card-
        // Begegnungen erstellen/sperren, Bullerei kommt (siehe die jeweiligen "teams-Flag"-Prüfungen
        // in index.php/edit_variables.php/edit_games.php/edit_website_bullerei.php).
        // rechte_turnier_settings bleibt dagegen false: die grundlegenden Turnier-Einstellungen
        // (Turnierphase, Neues Turnier anlegen) UND "Gruppeneinteilung losen" (eigenes, noch strengeres
        // Admin/Co-Admin-only-Gate, kein Flag) sind exklusiv Admin/Co-Admin vorbehalten - siehe Chat.
        10 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => true,
            'rechte_backstage' => true, 'rechte_alle_spiele' => false,
        ],
        // Backstage-Zugang: REINE Lese-/Sichtbarkeits-Rolle (Infos/Verlauf im Backstage-Bereich sehen),
        // darf NICHTS bearbeiten - weder Teams noch Turnier-Settings (beide false, siehe Chat).
        15 => [
            'rechte_neue_admins' => false, 'rechte_neue_co_admins' => false, 'rechte_restliche_rollen_vergeben' => false,
            'rechte_turnier_settings' => false, 'rechte_cms' => false, 'rechte_teams' => false,
            'rechte_backstage' => true, 'rechte_alle_spiele' => false,
        ],
        // Schiedsrichter*in: darf ausschließlich beliebige Spielergebnisse eintragen/ändern - hat
        // KEINEN Zugang zum Backstage-Bereich (kein violetter Balken, kein Settings-/Infos-/CMS-Button,
        // sieht/kann dort erstmal gar nichts).
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

// ================================================================================================
// ROLLEN-ÜBERSICHT: BEWUSST SELBST FORMULIERT STATT 1:1 AUS DER DATENBANK ÜBERNOMMEN
// ================================================================================================
// Die "beschreibung"-Spalte in System_Benutzer_in_Rolle ist knapp/technisch gehalten. Hier steht
// stattdessen eine ausführliche, an den tatsächlichen Rechte-Flags orientierte Erklärung, was man mit
// der jeweiligen Rolle auf der Website konkret tun darf. Ursprünglich nur im Nutzermanagement genutzt,
// jetzt auch auf der eigenen Profilseite (#account_profil) - daher hier zentral statt inline in
// index.php, damit beide Stellen zwangsläufig denselben Text zeigen.
function getRollenErklaerungen() {
    return [
        1  => 'Hat wirklich <b>alle</b> Rechte der Website: kann neue Admins und Co-Admins anlegen, alle restlichen Rollen vergeben, Turnier Settings/Turnierphase ändern, Website-Inhalte im CMS bearbeiten, Teams bearbeiten, den Backstage-Bereich sehen und beliebige Spielergebnisse eintragen. Wer Admin ist, braucht keine weitere Rolle zusätzlich. <b>Nur Admin</b> (nicht Co-Admin) kann außerdem im Nutzermanagement die Passwörter anderer Nutzer einsehen und ändern.',
        2  => 'Hat alles, was Admin auch hat - mit zwei Ausnahmen: kann selbst keine neuen Admins anlegen (Co-Admins und alle anderen Rollen aber schon), und kann <b>nicht</b> die Passwörter anderer Nutzer einsehen oder ändern - das bleibt ausschließlich Admin vorbehalten. Ansonsten reicht diese eine Rolle allein völlig aus.',
        5  => 'Darf ausschließlich die Website-Inhalte im CMS bearbeiten ("Website Inhalte bearbeiten"-Button). Sonst nichts - wer zusätzlich Teams bearbeiten oder Spielergebnisse eintragen soll, braucht dafür eine weitere Rolle dazu.',
        10 => 'Betreibt das Turnier operativ: hat immer <b>mindestens</b> alles, was Backstage-Zugang auch hat, plus Schreibrechte für Teams und die laufende K.-o./Gruppenphasen-Organisation. Nur die grundlegenden Turnier-Einstellungen (Turnierphase, neue Turniere, Gruppeneinteilung losen) und Nutzermanagement bleiben exklusiv Admin/Co-Admin vorbehalten.
            <ul style="margin:0.4rem 0 0; padding-left:1.1rem;">
                <li>&#10003; Teams bearbeiten: Team-/Spielernamen ändern, Gruppe zuordnen, Bearbeitungsrechte vergeben/entziehen, Team abmelden</li>
                <li>&#10003; Gruppen für die Gruppenphase generieren, Einzug ins KO-System festlegen (inkl. "Gruppenphase beendet"-Umschalter)</li>
                <li>&#10003; Green-Card-Begegnungen erstellen, Begegnungen sperren/entsperren, Begegnungs-ID in der K.-o.-Phase sehen</li>
                <li>&#10003; Bullerei kommt auslösen (Website temporär offline nehmen)</li>
                <li>&#10003; Backstage-Bereich betreten inkl. Telefonnummern/Team-Passwörter/Warteliste/ER-Diagramm (violetter Balken, Settings- und Infos-Button)</li>
                <li>&#10007; Turnier Settings/Turnierphase ändern, Neues Turnier anlegen, Gruppeneinteilung losen (alles exklusiv Admin/Co-Admin)</li>
                <li>&#10007; Website-Inhalte im CMS bearbeiten</li>
                <li>&#10007; Spielergebnisse fremder Teams eintragen/ändern/finalisieren</li>
                <li>&#10007; Nutzermanagement, Rollen vergeben, Passwörter, Verlauf/Traffic/DB-Verlauf</li>
            </ul>',
        15 => 'Reine <b>Lese</b>-Rolle: darf sich im Backstage-Bereich umsehen, aber nichts bearbeiten - gedacht z.B. für Helfer*innen, die Telefonnummern/Team-Passwörter/Warteliste/ER-Diagramm einsehen sollen dürfen. Turniermaster hat automatisch immer mindestens diese Rechte mit dazu.
            <ul style="margin:0.4rem 0 0; padding-left:1.1rem;">
                <li>&#10003; Backstage-Bereich betreten und dort Telefonnummern/Team-Passwörter/Warteliste/ER-Diagramm lesen (violetter Balken, Infos-Button)</li>
                <li>&#10007; Teams bearbeiten</li>
                <li>&#10007; Turnier-Settings/Turnierphase ändern, Gruppen generieren/auslosen, Begegnungen anlegen oder sperren</li>
                <li>&#10007; Website-Inhalte im CMS bearbeiten</li>
                <li>&#10007; Spielergebnisse fremder Teams eintragen/ändern/finalisieren</li>
                <li>&#10007; Nutzermanagement, Rollen vergeben, Passwörter, Verlauf/Traffic/DB-Verlauf</li>
            </ul>',
        20 => 'Darf ausschließlich Spiele bearbeiten - sonst nichts, auch keinen Blick in den Backstage-Bereich.
            <ul style="margin:0.4rem 0 0; padding-left:1.1rem;">
                <li>&#10003; Beliebige Spielergebnisse eintragen, ändern, finalisieren/unfinalisieren - auch bei fremden Teams</li>
                <li>&#10007; <b>Kein</b> Zugang zum Backstage-Bereich (kein violetter Balken, kein Settings-/Infos-/CMS-Button)</li>
                <li>&#10007; Teams bearbeiten, Turnier-Settings, Begegnungen anlegen/sperren</li>
                <li>&#10007; Website-Inhalte im CMS bearbeiten</li>
                <li>&#10007; Nutzermanagement, Rollen vergeben, Passwörter</li>
            </ul>',
        30 => 'Standardrolle für selbst registrierte Accounts - hat noch überhaupt keine Rechte. Muss von einem Admin/Co-Admin erst eine der obigen Rollen bekommen.',
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

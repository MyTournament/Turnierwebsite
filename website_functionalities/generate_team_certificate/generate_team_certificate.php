<?php
//https://blankiball.de/website_functionalities/generate_team_certificate/generate_team_certificate.php

require('fpdf184/fpdf.php');

// Kleine Helferfunktion statt der vorher an >15 Stellen wiederholten mb_convert_encoding()-Aufrufe -
// FPDF kann von Haus aus kein UTF-8, daher muss jeder aus der DB kommende Text (Umlaute!) vor der
// Ausgabe nach Windows-1252 konvertiert werden.
function cp1252($text) {
    return mb_convert_encoding((string)$text, 'Windows-1252', 'UTF-8');
}

// Farbschema an den alten Papier-Look (pngwing.com.png) angelehnt: dunkles Braun für Fließtext, ein
// wärmeres Bordeaux/Terrakotta für Überschriften & Zahlen, gedecktes Gold für Rahmen/Linien - siehe
// Chat ("Layout aufpimpen").
define('FARBE_TEXT', [64, 44, 26]);
define('FARBE_AKZENT', [123, 40, 30]);
define('FARBE_GOLD', [150, 108, 46]);
define('FARBE_BOX_FUELLUNG', [250, 243, 226]);

class PDF extends FPDF {

    // Page header
    function Header() {

        //HINTERGRUND
        $this->Image('pngwing.com.png', 0, 0, 212);

        // Logo oben links
        $this->Image('../../images/hermann_logo/export.png', 18, 14, 30);

        // Titel
        $this->SetY(20);
        $this->SetFont('Times', 'B', 30);
        $this->SetTextColor(...FARBE_AKZENT);
        $this->Cell(0, 16, cp1252('Teilnahmeurkunde'), 0, 1, 'C');

        $this->SetFont('Times', 'I', 12);
        $this->SetTextColor(...FARBE_GOLD);
        $this->Cell(0, 8, cp1252('Blankiball-Turnier'), 0, 1, 'C');

        // Dekorative Trennlinie unter dem Titel
        $pageWidth = $this->GetPageWidth();
        $lineY = $this->GetY() + 3;
        $this->SetDrawColor(...FARBE_GOLD);
        $this->SetLineWidth(0.6);
        $this->Line($pageWidth / 2 - 30, $lineY, $pageWidth / 2 + 30, $lineY);

        $this->SetTextColor(...FARBE_TEXT);
        $this->Ln(16);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(...FARBE_GOLD);
        $this->Cell(0, 10, cp1252('Blankiball k.e.V. - no rights reserved'), 0, 0, 'C');
    }

    // Zentrierte Textzeile mit eigener Schrift/Farbe - erspart das staendige Wiederholen von
    // SetFont()/SetTextColor()/Cell() fuer jede einzelne Zeile im Body.
    function Zeile($text, $font, $style, $size, $farbe, $hoehe = 8) {
        $this->SetFont($font, $style, $size);
        $this->SetTextColor(...$farbe);
        $this->Cell(0, $hoehe, cp1252($text), 0, 1, 'C');
    }

    // Eine Statistik-"Karte": Rahmen mit dezenter Fuellung, oben ein kleines Label, darunter groß der
    // Wert - deutlich uebersichtlicher als vorher reiner Fließtext ohne jede visuelle Gliederung.
    function StatKarte($x, $y, $breite, $hoehe, $label, $wert) {
        $this->SetDrawColor(...FARBE_GOLD);
        $this->SetFillColor(...FARBE_BOX_FUELLUNG);
        $this->SetLineWidth(0.4);
        $this->Rect($x, $y, $breite, $hoehe, 'DF');

        $this->SetXY($x, $y + 4);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(...FARBE_GOLD);
        $this->Cell($breite, 5, cp1252($label), 0, 0, 'C');

        $this->SetXY($x, $y + 11);
        $this->SetFont('Courier', 'B', 15);
        $this->SetTextColor(...FARBE_AKZENT);
        $this->Cell($breite, 9, cp1252($wert), 0, 0, 'C');
    }
}

// Instantiation of FPDF class
$pdf = new PDF();

// Define alias for number of pages
$pdf->AliasNbPages();
$pdf->AddPage();

include_once '../../database/db_connection.php';

include_once '../../variables.php';

// SICHERHEIT: (int)-Cast schliesst SQL-Injection ueber diese Felder - dieses Skript ist oeffentlich
// per GET erreichbar, ganz ohne Login.
$teamId = isset($_GET['teamId']) ? (int)$_GET['teamId'] : null;
$turnierId = isset($_GET['turnierId']) ? (int)$_GET['turnierId'] : null;

$sql = 'SELECT * FROM Turnier_Main WHERE id = '. $turnierId .'';
$result = $conn->query($sql);
while (!empty($row = $result->fetch_assoc())) {
    $turnierName = $row['name'];
}

$pdf->Zeile('Vielen Dank für deine Teilnahme am:', 'Times', '', 13, FARBE_TEXT);
$pdf->Zeile($turnierName, 'Times', 'B', 22, FARBE_AKZENT, 11);
$pdf->Ln(4);

if ($teamId != NULL) {

    $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $teamId .'';
    $result = $conn->query($sql);
    $endplatzierung = null;
    $siegesquote = null;
    while (!empty($row = $result->fetch_assoc())) {
        $teamName = $row['name'];
        $teamKuerzel = $row['kuerzel'];
        $endplatzierung = $row['endplatzierung'];
        $siegesquote = $row['siegesquote'];
    }

    //TRAFFIC
    include_once '../../database/traffic_analytics.php';
    $text = ' hat sich die Teilnahmeurkunde von Team '.$teamName.' angesehen';
    insert_traffic($conn, 1, "anonym", 2 , $text);

    $pdf->Zeile('ausgestellt für das Team', 'Times', '', 12, FARBE_TEXT);
    $pdf->Zeile($teamName . ' (' . $teamKuerzel . ')', 'Courier', 'B', 18, FARBE_AKZENT, 10);
    $pdf->Ln(3);

    // TEAMMITGLIEDER
    $pdf->Zeile('mit den Teammitgliedern', 'Times', 'I', 11, FARBE_TEXT);
    $sql = 'SELECT * FROM Turnier_Spieler_in WHERE fk_team = '. $teamId .'';
    $result = $conn->query($sql);
    $spielerNamen = [];
    while (!empty($row = $result->fetch_assoc())) {
        $spielerNamen[] = $row['name'];
    }
    $pdf->Zeile(implode('  •  ', $spielerNamen), 'Times', 'B', 13, FARBE_TEXT, 9);
    $pdf->Ln(6);

    // GETRUNKENE FLASCHEN & GESPIELTE SPIELE: Turnier_Spiel führt pro Runde die von jeder Seite
    // getrunkenen Flaschen (biereheimteam/biereauswaertsteam) - je nachdem, ob das Team in der
    // jeweiligen Begegnung Heim- oder Auswärtsteam war, zählt die passende Spalte.
    $sqlStats = 'SELECT '
        . 'SUM(CASE WHEN b.fk_heimteam = ' . $teamId . ' THEN s.biereheimteam '
        . '         WHEN b.fk_auswaertsteam = ' . $teamId . ' THEN s.biereauswaertsteam '
        . '         ELSE 0 END) AS gesamtFlaschen, '
        . 'COUNT(*) AS anzahlSpiele '
        . 'FROM Turnier_Spiel s JOIN Turnier_Begegnung b ON b.id = s.fk_begegnung '
        . 'WHERE b.fk_heimteam = ' . $teamId . ' OR b.fk_auswaertsteam = ' . $teamId;
    $resultStats = $conn->query($sqlStats);
    $gesamtFlaschen = 0;
    $anzahlSpiele = 0;
    if ($resultStats !== false && !empty($rowStats = $resultStats->fetch_assoc())) {
        $gesamtFlaschen = $rowStats['gesamtFlaschen'] !== null ? (int)$rowStats['gesamtFlaschen'] : 0;
        $anzahlSpiele = (int)$rowStats['anzahlSpiele'];
    }

    // STATISTIK-KARTEN
    $pageWidth = $pdf->GetPageWidth();
    $rand = 25;
    $anzahlKarten = 4;
    $abstand = 4;
    $kartenBreite = ($pageWidth - 2 * $rand - ($anzahlKarten - 1) * $abstand) / $anzahlKarten;
    $kartenHoehe = 26;
    $kartenY = $pdf->GetY();

    $statistiken = [
        ['label' => 'Endplatzierung', 'wert' => $endplatzierung !== null ? 'Platz ' . $endplatzierung : '-'],
        ['label' => 'Siegesquote', 'wert' => $siegesquote !== null ? round($siegesquote) . ' %' : '-'],
        ['label' => 'Flaschen getrunken', 'wert' => (string)$gesamtFlaschen],
        ['label' => 'Spiele gespielt', 'wert' => (string)$anzahlSpiele],
    ];

    foreach ($statistiken as $i => $stat) {
        $x = $rand + $i * ($kartenBreite + $abstand);
        $pdf->StatKarte($x, $kartenY, $kartenBreite, $kartenHoehe, $stat['label'], $stat['wert']);
    }

    $pdf->SetY($kartenY + $kartenHoehe + 14);

    // UNTERSCHRIFT
    $pdf->Zeile('gezeichnet', 'Times', '', 11, FARBE_TEXT, 7);
    $pdf->Image('hermann_unterschrift.png', 85, $pdf->GetY(), 40);
    $pdf->Ln(16);
    $pdf->Zeile('Hermann Blankenstein', 'Times', 'I', 11, FARBE_TEXT, 6);
}

$pdf->Output();

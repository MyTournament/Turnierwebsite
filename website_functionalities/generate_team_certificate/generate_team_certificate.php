<?php
//https://blankiball.de/website_functionalities/generate_team_certificate/generate_team_certificate.php



require('fpdf184/fpdf.php');
  
class PDF extends FPDF {
  
    // Page header
    function Header() {
          

        //HINTERGRUND
        $this->Image('pngwing.com.png',0,0,212);

        // Add logo to page
        $this->Image('../../images/hermann_logo/export.png',20,18,33);
          
        // Set font family to Arial bold 
        $this->SetFont('Courier','B',20);
        //$this->AddFont('DejaVu','','DejaVuSans.ttf');
        //$this->SetFont('DejaVu','',20);
          
        // Move to the right
        $this->Cell(80);
          
        // Header
        $this->Cell(36,10,' ',0,1,'C');
        $this->Cell(36,10,' ',0,1,'C');
        $this->Cell(0,10,'Teilnahmeurkunde',0,1,'C');
          
        // Line break
        $this->Ln(20);
    }
  
    // Page footer
    function Footer() {
          
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
          
        // Arial italic 8
        $this->SetFont('Arial','I',8);
          
        // Page number
        //$this->Cell(0,10,'Page ' . 
        //   $this->PageNo() . '/{nb}',0,0,'C');
            
        $this->Cell(0,10,'Blankiball k.e.V. - no rights reserved',0,0,'C');   
    }
}
  
// Instantiation of FPDF class
$pdf = new PDF();
  
// Define alias for number of pages
$pdf->AliasNbPages();
$pdf->AddPage();

//$str = iconv('UTF-8', 'windows-1252', $str);
$txt = iconv('utf-8', 'cp1252', $txt);

//mb_convert_encoding();

include_once '../../database/db_connection.php';

include_once '../../variables.php';

$teamId = $_GET['teamId'];
$turnierId = $_GET['turnierId'];

$sql = 'SELECT * FROM Turnier_Main WHERE id = '. $turnierId .'';
$result = $conn->query($sql);
while (!empty($row = $result->fetch_assoc())) {
    $turnierName = $row['name'];
}

$pdf->Cell(0, 10, '' , 0, 1, 'C');
$pdf->SetFont('Courier','',14);
$pdf->Cell(0, 10, mb_convert_encoding('Vielen Dank für deine Teilnahme am:', 'Windows-1252', 'UTF-8') , 0, 1, 'C');
$pdf->SetFont('Courier','B',21);
$pdf->Cell(0, 10, $turnierName , 0, 1, 'C');
$pdf->Cell(0, 10, '' , 0, 1, 'C');

$pdf->SetFont('Arial','',12);
//$pdf->Cell(0,10, mb_convert_encoding("Test mit Umlauten: ÄÖÜ äöü ß"), 0, 1);
//$pdf->Cell(0,10, mb_convert_encoding("Test mit Umlauten2: ÄÖÜ äöü ß", 'UTF-8', 'UTF-8'), 0, 1);


if($teamId != NULL){
    
    $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND id = '. $teamId .'';
    $result = $conn->query($sql);
    while (!empty($row = $result->fetch_assoc())) {
        $teamName = $row['name'];
        $teamKuerzel = $row['kuerzel'];
        $gruppeId = $row['fk_gruppe'];
        $endplatzierung = $row['endplatzierung'];

        $pdf->SetFont('Times','',14);
        $pdf->Cell(0, 10, mb_convert_encoding("Zertifikat ausgestellt für das Team:", 'Windows-1252', 'UTF-8') , 0, 1, 'C');
        $pdf->SetFont('Courier','B',14);
        $pdf->Cell(0, 10, mb_convert_encoding($teamName, 'Windows-1252', 'UTF-8') . ' (' . mb_convert_encoding($teamKuerzel, 'Windows-1252', 'UTF-8'). ')' , 0, 1, 'C');
    }

    //TRAFFIC //TODO: WebsiteID hier einfügen
    include_once '../../database/traffic_analytics.php';
    $text = ' hat sich die Teilnahmeurkunde von Team '.$teamName.' angesehen';
    insert_traffic($conn, 1, "anonym", 2 , $text);
    

    $pdf->Cell(0, 10, '' , 0, 1, 'C');
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'mit den Teammitgliedern' , 0, 1, 'C');

    $sql = 'SELECT * FROM Turnier_Spieler_in WHERE fk_team = '. $teamId .'';
    $result = $conn->query($sql);
    $zaehler = 1;
    while (!empty($row = $result->fetch_assoc())) {
        $spielerName = $row['name'];
        $pdf->SetFont('Courier','B',14);
        $pdf->Cell(0, 10, mb_convert_encoding($spielerName, 'Windows-1252', 'UTF-8') , 0, 1, 'C');
        $zaehler++;
    }

    //$pdf->Cell(0, 10, '' , 0, 1, 'C');
    $pdf->SetFont('Times','',14);
    //$pdf->Cell(0, 10, 'Sie bestritten das Turnier in der Gruppe', 0, 1, 'C');
    
    if($gruppeId != NULL){
        $sql = 'SELECT * FROM Turnier_Gruppe WHERE id = ' . $gruppeId . ' ORDER BY id';
        $result = $conn->query($sql);
        $gruppenName = " ";
        while (!empty($row = $result->fetch_assoc())) {
            $gruppenName = $row['name'];
        }
        $pdf->SetFont('Courier','B',14);
        //$pdf->Cell(0, 10, $gruppenName, 0, 1, 'C');
    }else{
        //echo "<p><i>Noch keiner Gruppe zugeteilt</i></p>";
    }
    
    $siege = 0; //für SIEGESQUOTE
    $niederlagen = 0;
    $sql = 'SELECT * FROM Turnier_Team WHERE geloescht = 0 AND `id` = ' . $teamId . ';';
    $result = $conn->query($sql);
    while (!empty($row = $result->fetch_assoc())) {
        $siegesquote = $row['siegesquote'];
    }
    

    //ENDPLATZIERUNG
    $pdf->Cell(0, 10, '' , 0, 1, 'C');
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'Das Team erreichte den Platz', 0, 1, 'C');

    $pdf->SetFont('Courier','B',14);
    $pdf->Cell(0, 10, $endplatzierung, 0, 1, 'C');

    //SIEGESQUOTE
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'mit einer unglaublichen Siegesquote von', 0, 1, 'C');

    $pdf->SetFont('Courier','B',14);
    
    if($siegesquote !== NULL){
        $pdf->Cell(0, 10, round($siegesquote) . ' %', 0, 1, 'C');
    }else{
        $pdf->Cell(0, 10, "noch keine Spiele gespielt", 0, 1, 'C');
    }

    //HERMANN BLANKENSTEIN
    $pdf->Cell(0, 10, '', 0, 1, 'C');
    $pdf->SetFont('Times','',12);
    $pdf->Cell(0, 10, 'gezeichnet', 0, 1, 'C');
    $pdf->SetFont('Times','I',12);
    $pdf->Image('hermann_unterschrift.png',85,242,40);
    $pdf->Cell(0, 10, '', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Hermann Blankenstein', 0, 1, 'C');
    


}


//for($i = 1; $i <= 30; $i++)
//$pdf->Cell(0, 10, 'line number ' . $i, 0, 1);




$pdf->Output();



?>
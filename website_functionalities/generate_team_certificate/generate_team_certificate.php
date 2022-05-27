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
            
        $this->Cell(0,10,'Blankiball e.V. - no rights reserved',0,0,'C');   
    }
}
  
// Instantiation of FPDF class
$pdf = new PDF();
  
// Define alias for number of pages
$pdf->AliasNbPages();
$pdf->AddPage();

$str = iconv('UTF-8', 'windows-1252', $str);
//utf8_decode();



$pdf->Cell(0, 10, '' , 0, 1, 'C');
//$pdf->SetFont('Courier','',14);
$pdf->Cell(0, 10, 'Vielen dank fuer deine Teilnahme am:' , 0, 1, 'C');
//$pdf->SetFont('Courier','B',18);
$pdf->Cell(0, 10, 'BLANKIBALL-TURNIER 2021' , 0, 1, 'C');
$pdf->Cell(0, 10, '' , 0, 1, 'C');

include_once '../../database/db_connection.php';

include_once '../../variables.php';

$teamId = $_GET['teamId'];
if($teamId != NULL){
    
    $sql = 'SELECT * FROM Turnier_Team WHERE id = '. $teamId .'';
    $result = $conn->query($sql);
    while (!empty($row = $result->fetch_assoc())) {
        $teamName = $row['name'];
        $gruppeId = $row['fk_gruppe'];
        $endplatzierung = $row['endplatzierung'];

        $pdf->SetFont('Times','',14);
        $pdf->Cell(0, 10, 'Zertifikat ausgestellt fuer das Team:' , 0, 1, 'C');
        $pdf->SetFont('Courier','B',14);
        $pdf->Cell(0, 10, $teamName , 0, 1, 'C');
    }

    $pdf->Cell(0, 10, '' , 0, 1, 'C');
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'mit den Teammitgliedern' , 0, 1, 'C');

    $sql = 'SELECT * FROM Turnier_Spieler_in WHERE fk_team = '. $teamId .'';
    $result = $conn->query($sql);
    $zaehler = 1;
    while (!empty($row = $result->fetch_assoc())) {
        $spielerName = $row['name'];
        $pdf->SetFont('Courier','B',14);
        $pdf->Cell(0, 10, $spielerName , 0, 1, 'C');
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
    $sql = 'SELECT * FROM Turnier_Begegnung WHERE `status` <> 3 AND (fk_heimteam = ' . $teamId . ' OR fk_auswaertsteam = ' . $teamId . ') ORDER BY id';
    $result = $conn->query($sql);
    while (!empty($row = $result->fetch_assoc())) {
        $begegnungId = $row['id'];
        $heimteamID=$row["fk_heimteam"];
        $auswaertsteamID=$row["fk_auswaertsteam"];        
        //SIEGESQUOTE AUSRECHNEN
            $sqlSiegesquote = 'SELECT * FROM `Turnier_Spiel` WHERE fk_begegnung = ' . $begegnungId . ' ORDER BY ID';
            $resultSiegesquote = $conn->query($sqlSiegesquote); 
            while ($rowSiegesquote = $resultSiegesquote->fetch_assoc()) {
                $biereheimteam = $rowSiegesquote['biereheimteam'];
                $biereauswaertsteam = $rowSiegesquote['biereauswaertsteam'];

                if($teamId == $heimteamID){
                    if($biereheimteam > $biereauswaertsteam){
                        $siege++;
                    }else if($biereheimteam < $biereauswaertsteam){
                        $niederlagen++;
                    }
                }else if($teamId == $auswaertsteamID){
                    if($biereheimteam > $biereauswaertsteam){
                        $niederlagen++;
                    }else if($biereheimteam < $biereauswaertsteam){
                        $siege++;
                    }
                }
            }
    }
    $siegesquote = ($siege/($siege+$niederlagen))*100;


    //ENDPLATZIERUNG
    $pdf->Cell(0, 10, '' , 0, 1, 'C');
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'Sie erreichten den Platz', 0, 1, 'C');

    $pdf->SetFont('Courier','B',14);
    $pdf->Cell(0, 10, $endplatzierung, 0, 1, 'C');

    //SIEGESQUOTE
    $pdf->SetFont('Times','',14);
    $pdf->Cell(0, 10, 'mit einer unglaublichen Siegesquote von', 0, 1, 'C');

    $pdf->SetFont('Courier','B',14);
    $pdf->Cell(0, 10, $siegesquote . ' %', 0, 1, 'C');

    //HERMANN BLANKENSTEIN
    $pdf->Cell(0, 10, '', 0, 1, 'C');
    $pdf->SetFont('Times','',12);
    $pdf->Cell(0, 10, 'gezeichnet', 0, 1, 'C');
    $pdf->SetFont('Times','I',12);
    $pdf->Image('hermann_unterschrift.png',85,242,40);
    $pdf->Cell(0, 10, '', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Hermann Blankenstein', 0, 1, 'C');
    


}


for($i = 1; $i <= 30; $i++)
$pdf->Cell(0, 10, 'line number ' . $i, 0, 1);




$pdf->Output();

?>
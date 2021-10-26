<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
  

include_once '../../database/db_connection.php';
include_once '../objects/spiel.php';
include_once '../objects/team.php';

// instantiate database team object
$database = new Database();
$PDOconn = $database->getConnection();

$team = new Team($PDOconn); //using PDOconn variable from Database object from db_connection.php
$spiel = new Spiel($conn, $PDOconn); //using MySQLiconn variable from db_connection.php
  
// get posted data
$data = json_decode(file_get_contents("php://input"));
  
// make sure data is not empty
if(
    isset($data->fk_begegnung) &&
    isset($data->biereheimteam) &&
    isset($data->biereauswaertsteam) &&
    isset($data->who_inserted_or_updated_last)
){
    $spiel->fk_begegnung = $data->fk_begegnung;
    $spiel->biereheimteam = $data->biereheimteam;
    $spiel->biereauswaertsteam = $data->biereauswaertsteam;
    $spiel->who_inserted_or_updated_last = $data->who_inserted_or_updated_last;

    // explanation: $data->who_inserted_or_updated_last should be in this format: "NAME@KUERZEL, so we need the part after the @"
    if (($pos = strpos($data->who_inserted_or_updated_last, "@")) !== FALSE){ // char @ found
        $kuerzel_string_after_at_sign = substr($data->who_inserted_or_updated_last, $pos + 1);
        $team->kuerzel = $kuerzel_string_after_at_sign;
    // char @ not found, the whole string should be the kuerzel
    } else {
        $team->kuerzel = $data->who_inserted_or_updated_last;
    }
    
    // check if Team is authorized for editing
    $team_is_authorized = $team->has_bearbeitungsrechte();
    if($team_is_authorized){
  
        // create the Spiel
        if($spiel->create()){
            
            http_response_code(201);
            echo json_encode(array("message" => "Spiel was created."));
        }
    
        // if unable to create the Spiel, tell the user
        else{
            
            http_response_code(503);
            echo json_encode(array("message" => "Unable to create Spiel."));
        }
    } else {

        http_response_code(403);
        echo json_encode(array("message" => "Unable to create Spiel. Team is not authorized to add or edit data on website."));
    }
}

else{
  
    http_response_code(400);
    echo json_encode(array("message" => "Unable to create Spiel. Data is incomplete."));
}
?>
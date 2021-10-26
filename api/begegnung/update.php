<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
  
include_once '../../database/db_connection.php';
include_once '../objects/begegnung.php';
include_once '../objects/team.php';

// instantiate database with the two connections (one for writing, one for reading)
$database = new Database();
$PDOconn = $database->getConnection();
$begegnung = new Begegnung($conn, $PDOconn);
$team = new Team($PDOconn);


// get posted data
$data = json_decode(file_get_contents("php://input"));
  
// make sure data is not empty
if (
    isset($_GET['id']) &&
    isset($_GET['changing_team_and_user_tag']) &&
    !empty($data->status)
){
    $begegnung->id = $_GET['id'];
    $changing_team_and_user_tag = $_GET['changing_team_and_user_tag'];

    $begegnung->status = $data->status;

    // explanation: $data->changing_team_and_user_tag should be in this format: "NAME@KUERZEL, so we need the part after the @"
    if (($pos = strpos($changing_team_and_user_tag, "@")) !== FALSE){ // char @ found
        $kuerzel_string_after_at_sign = substr($changing_team_and_user_tag, $pos + 1);
        $team->kuerzel = $kuerzel_string_after_at_sign;
    // char @ not found, the whole string should be the kuerzel
    } else {
        $team->kuerzel = $changing_team_and_user_tag;
    }

    $team_is_authorized = $team->has_bearbeitungsrechte();
    if($team_is_authorized){
        // update the Begegnung
        if($begegnung->update($changing_team_and_user_tag)){
            
            http_response_code(201);
            echo json_encode(array("message" => "Begegnung was updated."));
        }
      
        // if unable to update the Begegnung, tell the user
        else{
      
            http_response_code(503);
            echo json_encode(array("message" => "Unable to update Begegnung."));
        }
    } else {

        http_response_code(403);
        echo json_encode(array("message" => "Unable to update Begegnung. Team is not authorized to add or edit data on website."));
    }
  
}
  
// tell the user data is incomplete
else{
  
    http_response_code(400);

    echo json_encode(array("message" => "Unable to update Begegnung. No id or no changing_team_and_user_tag was given in arguments or no status was given in body."));
}
?>
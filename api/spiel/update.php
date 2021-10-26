<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
  
// get database connection
include_once '../../database/db_connection.php';
  
// instantiate product object
include_once '../objects/spiel.php';
  
$spiel = new Spiel($conn); //using conn variable from db_connection.php
  
// get data in body
$data = json_decode(file_get_contents("php://input"));
  
// make sure data is not empty
if(
    isset($_GET['id']) &&
    !empty($data->fk_begegnung) &&
    !empty($data->biereheimteam) &&
    !empty($data->biereauswaertsteam) &&
    !empty($data->who_inserted_or_updated_last)
    ){
    $spiel->id = $_GET['id'];
    $spiel->fk_begegnung = $data->fk_begegnung;
    $spiel->biereheimteam = $data->biereheimteam;
    $spiel->biereauswaertsteam = $data->biereauswaertsteam;
    $spiel->who_inserted_or_updated_last = $data->who_inserted_or_updated_last;
  
    // update the product
    if($spiel->update()){
        // set response code - 201 updated
        http_response_code(201);
  
        // tell the user
        echo json_encode(array("message" => "Spiel was updated."));
    }
  
    // if unable to update the Spiel, tell the user
    else{
  
        // set response code - 503 service unavailable
        http_response_code(503);
  
        // tell the user
        echo json_encode(array("message" => "Unable to update Spiel."));
    }
}
  
// tell the user data is incomplete
else{
  
    // set response code - 400 bad request
    http_response_code(400);
  
    // tell the user
    echo json_encode(array("message" => "Unable to update Spiel. Data is incomplete or id is not given."));
}
?>
<?php
// required headers
header("Access-Control-Allow-Origin: *"); // anyone can read the data
header("Content-Type: application/json; charset=UTF-8"); // return type is json

//######################################
//VARIABLEN EINBINDEN (DB_Login und TURNIERNUMMER)
include_once '../../database/db_connection.php';
//BEGEGNUNG KLASSE EINBINDEN
include_once '../objects/begegnung.php';
//######################################
  
$database = new Database();
$PDOconn = $database->getConnection();
 
$begegnung = new Begegnung($conn, $PDOconn);

$team_id = $_GET['team_id'];
if (isset($team_id)){

    // query Begegnungen
    $stmt = $begegnung->read($team_id);

    $answer=array();
    $answer["records"]=array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        
        $begegnung_item = Begegnung::copy_row_to_array($row); // static access of method "copy_row_to_array" in object class
        
        array_push($answer["records"], $begegnung_item);
    }

    http_response_code(200);

    // show Begegnungen in json format
    echo json_encode($answer);

} else {
    
    http_response_code(404);
    
    // tell the user no ID was given
    echo json_encode(
        array(
            "message" => "No ID was given for the Team for which the Begegnungen were requested."
            )
    );
}
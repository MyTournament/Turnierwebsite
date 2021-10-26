<?php
// required headers
header("Access-Control-Allow-Origin: *"); // anyone can read the data
header("Content-Type: application/json; charset=UTF-8"); // return type is json

//######################################
//VARIABLEN EINBINDEN (DB_Login und TURNIERNUMMER)
include_once '../../database/db_connection.php';
//SPIEL KLASSE EINBINDEN
include_once '../objects/spiel.php';
//######################################
  
$database = new Database();
$PDOconn = $database->getConnection();
 
$spiel = new Spiel($MySQLiconn, $PDOconn);

$body = json_decode(file_get_contents("php://input"));

if (!empty($body) && !empty($body->begegnung_ids)){

    $begegnung_ids = $body->begegnung_ids;

    // query Spiele
    $stmt = $spiel->read($begegnung_ids);

    $answer=array();
    $answer["records"]=array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        
        $spiel_item = Spiel::copy_row_to_array($row); // static access of method "copy_row_to_array" in object class
        
        array_push($answer["records"], $spiel_item);
    }

    http_response_code(200);
    echo json_encode($answer);

} else {
    
    http_response_code(400);
    
    // tell the user no IDs were given
    echo json_encode(
        array(
            "message" => "No IDs were given for the Begegnungen for which the Spiele were requested."
            )
    );
}
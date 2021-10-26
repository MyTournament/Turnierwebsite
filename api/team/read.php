<?php
// required headers
header("Access-Control-Allow-Origin: *"); // anyone can read the data
header("Content-Type: application/json; charset=UTF-8"); // return type is json

//######################################
//VARIABLEN EINBINDEN (DB_Login und TURNIERNUMMER)
include_once '../../database/db_connection.php';
//TEAM KLASSE EINBINDEN
include_once '../objects/team.php';
//######################################
  
// instantiate database and team object
$database = new Database();
$PDOconn = $database->getConnection();
  
// instantiate object
$team = new Team($PDOconn);

// TODO add if clauses for unwanted input and unexpected behavior from db

// get parameters
$current_tournament = filter_var($_GET['current_tournament'], FILTER_VALIDATE_BOOLEAN); // convert current_tournament argument to boolean
// get data in body
$body = json_decode(file_get_contents("php://input"));

$team_ids = array();
if(!empty($body)){
    $team_ids = $body->team_ids;
}

// query teams
$stmt = $team->read($current_tournament, $team_ids);

$teams_arr=array();
$teams_arr["records"]=array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    
    $team_item = Team::copy_row_to_array($row); // static access of method "copy_row_to_array" in object class
    
    array_push($teams_arr["records"], $team_item);
}

// set response code - 200 OK
http_response_code(200);

// show teams data in json format
echo json_encode($teams_arr);
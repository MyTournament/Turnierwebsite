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

$team = new Team($PDOconn);

// set ID property of team to read
$team->kuerzel = isset($_GET['kuerzel']) ? $_GET['kuerzel'] : die();
$team->password = isset($_GET['pw']) ? $_GET['pw'] : die();
// $team->password = isset($_GET['pw']) ? $_GET['pw'] : die();
  
// query teams that are opponents in upcoming matches
$teams = $team->check_password();
  
// teams array
$answer=array();
$answer["records"]=$teams;

// set response code - 200 OK
http_response_code(200);

// show teams data in json format
echo json_encode($answer);
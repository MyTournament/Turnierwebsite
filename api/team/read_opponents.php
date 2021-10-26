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
if (isset($_GET['id'])){
    $team->id = $_GET['id'];
  
    // query teams that are opponents in upcoming matches
    $stmt = $team->read_opponents();
    $num = $stmt->rowCount();
    
    // check if more than 0 record found
    if($num>0){
    
        // teams array
        $teams_arr=array();
        $teams_arr["records"]=array();
    
        // retrieve our table contents
        // fetch() is faster than fetchAll()
        // http://stackoverflow.com/questions/2770630/pdofetchall-vs-pdofetch-in-a-loop
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){

            $team_item = Team::copy_row_to_array($row); // static access of method "copy_row_to_array" in class "Team"
    
            array_push($teams_arr["records"], $team_item);
        }
    
        // set response code - 200 OK
        http_response_code(200);
    
        // show teams data in json format
        echo json_encode($teams_arr);
    }
    
    else{
    
        // set response code - 404 Not found
        http_response_code(404);
    
        // tell the user no products found
        echo json_encode(
            array(
                "message" => "No opponent teams found."
                )
        );
    }
} else {
    // set response code - 404 Not found
    http_response_code(404);
    
    // tell the user no products found
    echo json_encode(
        array(
            "message" => "Unable to find Teams. No id was given."
            )
    );
}
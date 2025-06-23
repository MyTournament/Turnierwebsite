<?php

include_once '../../website_datachange/edit_interface.php';
include_once '../../variables.php';

class Begegnung{
  
    // database connection and table name
    private $MySQLiconn;
    private $PDOconn;
    private $table_name = "Begegnung";
  
    // object properties
    public $id;
    public $fk_heimteam;
    public $fk_auswaertsteam;
    public $fk_siegerteam;
    public $ko_finallevel;
    public $ko_turnierbaumposition;
    public $status;
  
    // constructor with $PDOconn as database connection
    public function __construct($MySQLiconn, $PDOconn){
        $this->MySQLiconn = $MySQLiconn;
        $this->PDOconn = $PDOconn;
    }

    public static function copy_row_to_array(array $row){
        // extract row
        // this will make $row['name'] to
        // just $name only
        extract($row);

        $begegnung_item=array(
            "id" => $id,
            "fk_heimteam" => $fk_heimteam,
            "fk_auswaertsteam" => $fk_auswaertsteam,
            "fk_siegerteam" => $fk_siegerteam,
            "ko_finallevel" => $ko_finallevel,
            "ko_turnierbaumposition" => $ko_turnierbaumposition,
            "status" => $status
        );

        return $begegnung_item;
    }

    // read Begegnung
    function read(string $team_id){
        
        $query = "SELECT * FROM " . $this->table_name . " WHERE (fk_heimteam = ?) OR (fk_auswaertsteam = ?)";
    
        // prepare query statement
        $stmt = $this->PDOconn->prepare($query);       
        $stmt->bindParam(1, $team_id);
        $stmt->bindParam(2, $team_id);
        
        $stmt->execute();
    
        return $stmt;
    }

    // update Begegnung
    function update(string $changing_team_and_user_tag){

        global $TurnierID;
    
        // query to insert record
        $sql = "UPDATE " . $this->table_name . " SET `status` = ? WHERE id = ?"; // change status of begegnung to given status
        myDb_execute($this->MySQLiconn, $TurnierID, $changing_team_and_user_tag, "begegnung.php", $sql, array($this->status, $this->id));
        
        // TODO return false if db command in myDb_execute() was not successfull
        return true;
        
    }
}
?>
<?php

include_once '../../website_datachange/edit_interface.php';
include_once '../../variables.php';

class Spiel{
  
    // database connection and table name
    private $MySQLiconn;
    private $PDOconn;
    private $table_name = "Spiel";
  
    // object properties
    public $id;
    public $timestamp;
    public $fk_begegnung;
    public $biereheimteam;
    public $biereauswaertsteam;
    public $austragungsdatum;
    public $who_inserted_or_updated_last;
  
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
            "fk_begegnung" => $fk_begegnung,
            "biereheimteam" => $biereheimteam,
            "biereauswaertsteam" => $biereauswaertsteam,
            "austragungsdatum" => $austragungsdatum,
            "who_inserted_or_updated_last" => $who_inserted_or_updated_last
        );

        return $begegnung_item;
    }

    // read Spiel
    function read(array $begegnung_ids){
        
        // select all Spiele with given begegnung_ids
        if (!empty($begegnung_ids)){
            
            // TODO improve security with prepared statement instead of manual string building
            $begegnung_ids_string = join(", ", $begegnung_ids);   
            $query = "SELECT DISTINCT id, fk_begegnung, biereheimteam, biereauswaertsteam, austragungsdatum, who_inserted_or_updated_last FROM " . $this->table_name . " WHERE fk_begegnung IN (" . $begegnung_ids_string . ")";
            
            // prepare query statement
            $stmt = $this->PDOconn->prepare($query);
            
            $stmt->execute();
        
            return $stmt;
        } else {
            return null;
        }
        
    }

    // create Spiel
    function create(){
        global $TurnierID;
    
        // TODO use $this->table_name
        // query to insert record
        $sql = "INSERT INTO Spiel (fk_begegnung, biereheimteam, biereauswaertsteam, who_inserted_or_updated_last) VALUES (?, ?, ?, ?)";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($this->MySQLiconn, $TurnierID, $this->who_inserted_or_updated_last, "spiel.php", $sql, array($this->fk_begegnung, $this->biereheimteam, $this->biereauswaertsteam, $this->who_inserted_or_updated_last));
        
        // TODO return false if creation with myDb_execute() was not successfull
        return true;
        
    }
    
    // update Spiel
    function update(){
        global $TurnierID;
        
        // TODO use $this->table_name
        // query to insert record
        $sql = "UPDATE Spiel SET fk_begegnung = ?, biereheimteam = ?, biereauswaertsteam = ?, who_inserted_or_updated_last = ? WHERE id = ?";
        //DEAKTIVIERT WEIL AKTUELL NICHT GENUTZT: myDb_execute($this->MySQLiconn, $TurnierID, $this->who_inserted_or_updated_last, "spiel.php 2", $sql, array($this->fk_begegnung, $this->biereheimteam, $this->biereauswaertsteam, $this->who_inserted_or_updated_last, $this->id));
    
        // TODO return false if creation with myDb_execute() was not successfull
        return true;
        
    }
}
?>
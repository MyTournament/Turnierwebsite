<?php

include_once '../../variables.php';
include_once '../tools/collation_helper.php';

class Team{
  
    // database connection and table name
    private $PDOconn;
    private $table_name = "Team";
  
    // object properties
    public $id;
    public $password;
    public $fk_turnier;
    public $fk_gruppe;
    public $name;
    public $name_link;
    public $kuerzel;
    public $bearbeitungsrechte;
    public $gruppenphase_spiele;
    public $gruppenphase_flaschen;
    public $gruppenphase_punkte;
    public $endplatzierung;
  
    // constructor with $PDOconn as database connection
    public function __construct($PDOconn){
        $this->PDOconn = $PDOconn;
    }

    public static function copy_row_to_array(array $row){

        $collation_helper = new Collation_Helper();

        // extract row
        // this will make $row['name'] to
        // just $name only
        extract($row);

        $team_item=array(
            "id" => $id,
            "fk_turnier" => $fk_turnier,
            "fk_gruppe" => $fk_gruppe,
            "name" => $collation_helper->fix_characters_emojis($name),
            "name_link" => $collation_helper->fix_characters($name_link),
            "kuerzel" => $collation_helper->fix_characters_emojis($kuerzel),
            "bearbeitungsrechte" => $bearbeitungsrechte,
            "gruppenphase_spiele" => $gruppenphase_spiele,
            "gruppenphase_flaschen" => $gruppenphase_flaschen,
            "gruppenphase_punkte" => $gruppenphase_punkte,
            "endplatzierung" => $endplatzierung
        );

        return $team_item;
    }

    // when using this function: always CALL REMOVE_SECRETS before sending to client!
    private static function copy_row_to_array_secret(array $row){

        $collation_helper = new Collation_Helper();

        // extract row
        // this will make $row['name'] to
        // just $name only
        extract($row);

        $team_item=array(
            "id" => $id,
            "password" => $collation_helper->fix_characters_emojis($password),
            "fk_turnier" => $fk_turnier,
            "fk_gruppe" => $fk_gruppe,
            "name" => $collation_helper->fix_characters_emojis($name),
            "name_link" => $collation_helper->fix_characters($name_link),
            "kuerzel" => $collation_helper->fix_characters_emojis($kuerzel),
            "bearbeitungsrechte" => $bearbeitungsrechte,
            "gruppenphase_spiele" => $gruppenphase_spiele,
            "gruppenphase_flaschen" => $gruppenphase_flaschen,
            "gruppenphase_punkte" => $gruppenphase_punkte,
            "endplatzierung" => $endplatzierung
        );

        return $team_item;
    }

    private static function remove_secrets(array &$team){
        unset($team["password"]); // DO NOT SEND PASSWORD!!
        return $team;
    }

    function read(bool $current_tournament, array $team_ids){
        
        // select all teams with given id
        if (!empty($team_ids)){
            // TODO improve security with prepared statement instead of manual string building
            $team_ids_string = join(", ", $team_ids);   
            
            $query = "SELECT DISTINCT * FROM " . $this->table_name . " WHERE id IN (" . $team_ids_string . ")";
        }

        // select all teams from the current tournament
        else if ($current_tournament == true){
            global $TurnierID;
            $query = "SELECT id, fk_turnier, fk_gruppe, name, name_link, kuerzel, bearbeitungsrechte, gruppenphase_spiele, gruppenphase_flaschen, gruppenphase_punkte, endplatzierung FROM " . $this->table_name . " WHERE fk_turnier = " . $TurnierID;
        }
        
        // select all teams
        else {
            $query = "SELECT id, fk_turnier, fk_gruppe, name, name_link, kuerzel, bearbeitungsrechte, gruppenphase_spiele, gruppenphase_flaschen, gruppenphase_punkte, endplatzierung FROM " . $this->table_name;
        }
    
        // prepare query statement
        $stmt = $this->PDOconn->prepare($query);
    
        // execute query
        $stmt->execute();
    
        return $stmt;
    }

    function read_opponents(){
  
        // select all opponents in Begegnungen query
        $query  = "SELECT DISTINCT Gegnerteam.id, Gegnerteam.fk_turnier, Gegnerteam.fk_gruppe, Gegnerteam.name, Gegnerteam.name_link, Gegnerteam.kuerzel, Gegnerteam.bearbeitungsrechte, Gegnerteam.gruppenphase_spiele, Gegnerteam.gruppenphase_flaschen, Gegnerteam.gruppenphase_punkte, Gegnerteam.endplatzierung ";
        $query .= "FROM " . $this->table_name . " AS Gegnerteam, " . $this->table_name . " AS Eigenesteam, Begegnung ";
        $query .= "WHERE Eigenesteam.id = ? AND ((Begegnung.fk_heimteam = Eigenesteam.id AND Begegnung.fk_auswaertsteam = Gegnerteam.id) OR (Begegnung.fk_heimteam = Gegnerteam.id AND Begegnung.fk_auswaertsteam = Eigenesteam.id)) ";
        $query .= "AND Begegnung.fk_siegerteam IS NULL AND Begegnung.status = 1"; // status 1 entspricht "nicht veraltet, frisch berechnet" (Stand 2021-07-13) 
        
        // prepare query statement
        $stmt = $this->PDOconn->prepare($query);

        // bind id of of team for which opponents are retrieved
        $stmt->bindParam(1, $this->id);
        
        // execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    function check_password(){
        
        // get all teams: id, kuerzel, password
        $query  = "SELECT id, password, fk_turnier, fk_gruppe, name, name_link, kuerzel, bearbeitungsrechte, gruppenphase_spiele, gruppenphase_flaschen, gruppenphase_punkte, endplatzierung FROM " . $this->table_name;
        $stmt = $this->PDOconn->prepare($query);
        $stmt->execute();
        
        // get teams in array
        $all_teams=array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            
            $team_item = Team::copy_row_to_array_secret($row); // get teams with FIXED COLLATION ON KUERZEL AND PASSWORD
            
            array_push($all_teams, $team_item);
        }

        // TODO: remove second loop and move content to upper loop
        $chosen_teams = array();
        foreach ($all_teams as $team){
            if ($team["kuerzel"] == $this->kuerzel && $team["password"] == $this->password){
                // remove secret data (password), client should not see this data
                Team::remove_secrets($team);
                
                array_push($chosen_teams, $team);
            }
        }
        
        return $chosen_teams;
    }
    
    function has_bearbeitungsrechte(){
        $has_bearbeitungsrechte = False;
        $stmt = null;
        $teams_arr = array();

        if(!empty($this->id)){
            $query = "SELECT bearbeitungsrechte FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->PDOconn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();
        } elseif(!empty($this->kuerzel)){
            // FIXME:   with only knowing the kuerzel, multiple teams from different tournaments can get selected.
            // TODO:    find the one wanted team using the tournament id
            //          $query = "SELECT bearbeitungsrechte FROM " . $this->table_name . " WHERE kuerzel = ? AND fk_turnier = ? ";
            //          the using function should get the fk_turnier via the fk_begegnung or other data that leads to the fk_turnier  
            $query = "SELECT bearbeitungsrechte FROM " . $this->table_name . " WHERE kuerzel = ?";
            $stmt = $this->PDOconn->prepare($query);
            $stmt->bindParam(1, $this->kuerzel);
            $stmt->execute();
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){

            $team_item = Team::copy_row_to_array($row); // static access of method "copy_row_to_array" in class "Team"
            array_push($teams_arr, $team_item);
        }

        // in case there are multiple teams found, we take the first one in the list
        // FIXME: multiple teams should not be possible, fix FIXME above. TODO: when fixed, throw a error message here when more than one entry
        $has_bearbeitungsrechte = filter_var($teams_arr[0]["bearbeitungsrechte"], FILTER_VALIDATE_BOOLEAN);
        return $has_bearbeitungsrechte;
    }
}
?>
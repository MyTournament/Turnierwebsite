<?php
    $websiteId = 1; //Wird nie geändert! Diese Website wird immer immer die Website mit der Id 1 bleiben, auch in den Folgejahren
    //TurnierIDs aus der DB abfragen
    include_once 'database/db_connection.php'; //Datenbanklogin

    //AKTUELLES TURNIER
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 1 ORDER BY order_on_website DESC';
    $result = $conn->query($sql);
    $TurnierID = 0; //Platzhalter - nur für den Fall dass es kein passendes Turnier gibt
    while ($row = $result->fetch_assoc()) {
        $TurnierID = $row['id'];
        $TurnierName = $row['name'];
        break; //nur erstes Turnier wird das aktuelle Turnier
    }

    //TEST-TURNIERE
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 2 ORDER BY order_on_website, id DESC';
    $result = $conn->query($sql);
    $testTurniere = array();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $testTurnierID = $row['id'];
        $testTurnierName = $row['name'];
        $testTurniere[$index] = array($index, $testTurnierID, $testTurnierName);
        $index++;
    }


    //VERGANGENE TURNIERE
    $sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' AND type = 3 ORDER BY order_on_website DESC, id DESC';
    $result = $conn->query($sql);
    $history = array();
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $turnierID = $row['id'];
        $turnierName = $row['name'];
        $history[$index] = array($index, $turnierID, $turnierName);
        $index++;
    }
    
//TODO: gesamten Db-Abschnitt hier läschen, nachdem Jonas auf die neue Datei umgestellt hat
    /*
    $db_server = "REDACTED";
    $db_benutzer = "REDACTED";
    $db_passwort = "";
    $db_name = "REDACTED";

    // Create connection
    $conn = new mysqli($db_server, $db_benutzer, $db_passwort,$db_name);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    */
    /*
    class Database{

        // specify your own database credentials
        private $db_server = "REDACTED";
        private $db_username = "REDACTED";
        private $db_password = "REDACTED";
        private $db_name = "REDACTED";
        
        public $PDOconn;

        public function getConnectionMySQLi(){ //Für Website
            // Create connection
            $mysqliconn = new mysqli($this->db_server, $this->db_username, $this->db_password,$this->db_name);
            // Check connection
            if ($mysqliconn->connect_error) {
                die("Connection failed: " . $mysqliconn->connect_error);
            }

            return $mysqliconn;
        }
        

        // get the PDO database connection
        public function getConnection(){
    
            $this->PDOconn = null;
    
            try{
                $this->PDOconn = new PDO("mysql:host=" . $this->db_server . ";dbname=" . $this->db_name, $this->db_username, $this->db_password);
                $this->PDOconn->exec("set names utf8");
            }catch(PDOException $exception){
                echo "Connection error: " . $exception->getMessage();
            }
    
            return $this->PDOconn;
        }
    }

    $sqliDB = new Database();
    $conn = $sqliDB->getConnectionMySQLi(); //Für Website
    */
?>
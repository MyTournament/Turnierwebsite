<?php
    /*
    $db_server = "rdbms.strato.de";
    $db_benutzer = "U4247673";
    $db_passwort = "";
    $db_name = "DB4247673";

    // Create connection
    $conn = new mysqli($db_server, $db_benutzer, $db_passwort,$db_name);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    */
    class Database{

        // specify your own database credentials
        private $db_server = "rdbms.strato.de";
        private $db_username = "dbu1612112";
        private $db_password = "HF?A=k%CWdDxc*8Jr4()bmF";
        private $db_name = "dbs4154474";
        
        public $PDOconn;

        public function getConnectionMySQLi(){ //Für Website
            // Create connection
            $mysqliconn = new mysqli($this->db_server, $this->db_username, $this->db_password,$this->db_name);
            // Check connection
            if ($mysqliconn->connect_error) {
                die("Connection failed: " . $mysqliconn->connect_error);
            }

            // Will NOT affect $mysqli->real_escape_string();
            $mysqliconn->query("SET NAMES utf8mb4");

            // Will NOT affect $mysqli->real_escape_string();
            $mysqliconn->query("SET CHARACTER SET utf8mb4");

            // But, this will affect $mysqli->real_escape_string();
            $mysqliconn->set_charset('utf8mb4');

            // But, this will NOT affect it (UTF-8 vs utf8mb4) -- don't use dashes here
            $mysqliconn->set_charset('UTF-8');

            return $mysqliconn;
        }
        

        // get the PDO database connection
        public function getConnection(){
    
            $this->PDOconn = null;
    
            try{
                $this->PDOconn = new PDO("mysql:host=" . $this->db_server . ";dbname=" . $this->db_name, $this->db_username, $this->db_password);
                $this->PDOconn->exec("set names utf8");
            }catch(PDOException $exception){
                //echo "Connection error: " . $exception->getMessage();
            }
    
            return $this->PDOconn;
        }
    }

    $temp_db = new Database();
    $conn = $temp_db->getConnectionMySQLi(); //Für Richards Website-Teile. Jonas inizialisiert jeweils an der nutzenden Klasse mit ->getConnection()

?>
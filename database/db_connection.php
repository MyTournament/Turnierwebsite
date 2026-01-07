<?php
    $cfg = [
        'db_server' => getenv('DB_SERVER') ?: '',
        'db_username' => getenv('DB_USERNAME') ?: '',
        'db_password' => getenv('DB_PASSWORD') ?: '',
        'db_name' => getenv('DB_NAME') ?: '',
    ];

    $local_cfg_path = __DIR__ . '/../local_secrets/db_connection.local.php';
    if (file_exists($local_cfg_path)) {
        $local_cfg = include $local_cfg_path;
        if (is_array($local_cfg)) {
            $cfg = array_merge($cfg, $local_cfg);
        }
    }

    class Database{
        private $db_server;
        private $db_username;
        private $db_password;
        private $db_name;

        public $PDOconn;

        public function __construct(array $cfg){
            $this->db_server = $cfg['db_server'] ?? '';
            $this->db_username = $cfg['db_username'] ?? '';
            $this->db_password = $cfg['db_password'] ?? '';
            $this->db_name = $cfg['db_name'] ?? '';
        }

        public function getConnectionMySQLi(){ //Fuer Website
            $mysqliconn = new mysqli(
                $this->db_server,
                $this->db_username,
                $this->db_password,
                $this->db_name
            );
            if ($mysqliconn->connect_error) {
                die("Connection failed: " . $mysqliconn->connect_error);
            }

            $mysqliconn->query("SET NAMES utf8mb4");
            $mysqliconn->query("SET CHARACTER SET utf8mb4");
            $mysqliconn->set_charset('utf8mb4');

            return $mysqliconn;
        }

        public function getConnection(){
            $this->PDOconn = null;

            try{
                $this->PDOconn = new PDO(
                    "mysql:host=" . $this->db_server . ";dbname=" . $this->db_name,
                    $this->db_username,
                    $this->db_password
                );
                $this->PDOconn->exec("set names utf8");
            }catch(PDOException $exception){
                //echo "Connection error: " . $exception->getMessage();
            }

            return $this->PDOconn;
        }
    }

    $temp_db = new Database($cfg);
    $conn = $temp_db->getConnectionMySQLi();

?>

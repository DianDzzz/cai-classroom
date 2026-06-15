<?php
define('BASE_URL', '/cai-classroom');

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'sql308.infinityfree.com';
    private $user = 'if0_42184685'; 
    private $pass = 'Ambatukam6967';
    private $name = 'if0_42184685_cai_db';

    private function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->name);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>
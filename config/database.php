<?php
define('BASE_URL', '/cai-classroom');

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $user = 'cai_user';
    private $pass = 'caipass2024';
    private $name = 'cai_db';

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
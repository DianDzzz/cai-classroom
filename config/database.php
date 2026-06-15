<?php
/* ── Load .env ─────────────────────────────────────────────── */
$_env = __DIR__ . '/../.env';
if (file_exists($_env)) {
    foreach (file($_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_ENV[trim($_k)] = trim($_v);
    }
}

define('BASE_URL', rtrim($_ENV['APP_BASE_URL'] ?? '/cai-classroom', '/'));

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'sql308.infinityfree.com';
    private $user = 'if0_42184685'; 
    private $pass = 'Ambatukam6967';
    private $name = 'if0_42184685_cai_db';

    private function __construct() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $name = $_ENV['DB_NAME'] ?? 'cai_db';

        $this->conn = new mysqli($host, $user, $pass, $name);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
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
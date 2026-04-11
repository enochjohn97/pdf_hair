<?php
// ============================================================
//  OrderPro — Database Configuration
//  Edit these values to match your server environment
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'orderpro_db');
define('DB_USER', 'root');       // change to your DB user
define('DB_PASS', 'Master@123');           // change to your DB password
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function conn(): PDO
    {
        return $this->pdo;
    }
}

/** Shorthand helper */
function db(): PDO
{
    return Database::getInstance()->conn();
}

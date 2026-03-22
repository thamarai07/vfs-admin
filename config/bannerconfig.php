<?php
/**
 * Banner System Database Configuration
 * Delegates to the main db.php so there is only ONE place to manage credentials.
 * The $conn PDO instance is available after this include.
 */

require_once __DIR__ . '/env.php';

// Override DB_NAME for banner system if it uses a separate database
// (Currently pointing to banner_system – kept for backward compatibility)
// If your banner tables are in the main DB, remove the override below.
$_ENV['DB_NAME'] = env('BANNER_DB_NAME', env('DB_NAME', 'banner_system'));

require_once __DIR__ . '/db.php';

// Error Reporting – never show errors to clients in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// -----------------------------------------------------------------------
// Database singleton class (kept for BannerAPIController compatibility)
// -----------------------------------------------------------------------
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        global $conn;
        $this->connection = $conn;
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
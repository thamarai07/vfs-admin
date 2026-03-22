<?php
/**
 * Database Configuration
 * Reads credentials from .env – no hardcoded values here.
 */

require_once __DIR__ . '/env.php';

$dbHost    = env('DB_HOST', 'localhost');
$dbName    = env('DB_NAME', 'vfsportal');
$dbUser    = env('DB_USER', 'root');
$dbPass    = env('DB_PASS', '');
$isDevMode = env('APP_ENV', 'production') !== 'production';

try {
    $conn = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    if ($isDevMode) {
        // Only show details in development
        die(json_encode([
            'status'  => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]));
    }

    // In production – safe generic message only
    header('Content-Type: application/json');
    die(json_encode([
        'status'  => 'error',
        'message' => 'Service temporarily unavailable. Please try again later.'
    ]));
}

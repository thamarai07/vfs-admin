<?php
/**
 * Database Configuration
 * Update these values according to your setup
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'fruit_shop');  // or 'vfsportal' if you used that
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password (usually empty for XAMPP)

// Create PDO connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("
        <div style='font-family: Arial; max-width: 600px; margin: 100px auto; padding: 30px; background: #fee; border: 2px solid #fcc; border-radius: 10px;'>
            <h2 style='color: #c00; margin: 0 0 10px 0;'>❌ Database Connection Failed</h2>
            <p style='color: #666; margin: 0 0 20px 0;'>Unable to connect to the database. Please check:</p>
            <ul style='color: #666; margin: 0 0 20px 20px;'>
                <li>MySQL server is running</li>
                <li>Database name is correct: <code style='background: #fff; padding: 2px 6px; border-radius: 3px;'>" . DB_NAME . "</code></li>
                <li>Username and password are correct</li>
                <li>Database exists (run the SQL schema first)</li>
            </ul>
            <details style='color: #999; font-size: 12px;'>
                <summary style='cursor: pointer; color: #c00;'>Show Technical Details</summary>
                <pre style='background: #fff; padding: 10px; margin-top: 10px; border-radius: 5px; overflow-x: auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>
            </details>
        </div>
    ");
}
?>  
<?php
// db.php
$host = "localhost";
$dbname = "vfsportal";
$user = "root";
$pass = "";

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}
?>

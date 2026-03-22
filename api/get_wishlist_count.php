<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

// user_id from query string (JWT skipped per user request)
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id < 1) {
    echo json_encode(["status" => "success", "count" => 0]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM favorites
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "status"  => "success",
        "count"   => (int) $result['count'],
        "user_id" => $user_id
    ]);
} catch (Exception $e) {
    error_log("get_wishlist_count.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "An error occurred.", "count" => 0]);
}
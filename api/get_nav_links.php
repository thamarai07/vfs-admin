<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "../config/db.php";

try {
    $stmt = $conn->query("SELECT id, link_name, link_url FROM nav_links_master WHERE is_active = 1 ORDER BY display_order ASC");
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($links && count($links) > 0) {
        echo json_encode([
            "status" => "success",
            "data" => $links
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No active links found"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>

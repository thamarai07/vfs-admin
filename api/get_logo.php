<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "../config/db.php";

try {
    $stmt = $conn->query("SELECT logo_name, logo_path FROM logo_master WHERE is_active = 1 LIMIT 1");
    $logo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($logo) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'];
        $fullUrl = $baseUrl . "/vfs_portal/vfs-admin/" . $logo['logo_path'];

        echo json_encode([
            "status" => "success",
            "logo_name" => $logo['logo_name'],
            "logo_url" => $fullUrl
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "No active logo found"]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Get Cart Count API - USER-SPECIFIC
 * Returns the count of ACTIVE cart items for a specific user
 */

require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

try {
    // ✅ Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Only GET method allowed",
            "count" => 0
        ]);
        exit;
    }

    // 🔥 Get user_id from query parameter
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    
    // If no user_id provided, try session (fallback)
    if (!$user_id) {
        session_start();
        $user_id = $_SESSION['user_id'] ?? 1;  // Default to 1 for backward compatibility
    }
    
    // Validate user_id
    if (empty($user_id) || $user_id < 1) {
        echo json_encode([
            "status" => "success",
            "count" => 0,
            "message" => "No user specified",
            "user_id" => null
        ]);
        exit;
    }

    $session_id = 'user_' . $user_id;
    
    error_log("🔍 Getting cart count for user_id: $user_id (session: $session_id)");

    // 🔥 COUNT ONLY ACTIVE ITEMS for this specific user
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(c.quantity * p.price_per_kg), 0) as total_value
        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.session_id = ? 
        AND c.status = 'active'
    ");
    
    $stmt->execute([$session_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = (int) $result['count'];
    $totalValue = round((float) $result['total_value'], 2);
    
    error_log("✅ Cart count for user $user_id: $count items, total value: ₹$totalValue");
    
    echo json_encode([
        "status"      => "success",
        "count"       => $count,
        "total_value" => $totalValue,
        "user_id"     => $user_id
    ]);

} catch (PDOException $e) {
    // Database errors
    error_log("❌ Database error in get_cart_count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error",
        "count" => 0
    ]);
    
} catch (Exception $e) {
    error_log("get_cart_count.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "An error occurred. Please try again.",
        "count"   => 0
    ]);
}
?>
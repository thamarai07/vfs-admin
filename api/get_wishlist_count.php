<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../config/jwt.php';

// user_id comes from the verified JWT — never from the client.
$authUser = requireAuth();
$user_id  = (int) ($authUser['user_id'] ?? 0);

$debug = isset($_GET['debug']);   // add ?debug=1 to the URL to see internals

try {
    // Wishlist items live in the `favorites` table, not `cart`.
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM favorites
        WHERE user_id = ?
        AND status = 'active'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $response = [
        "status"  => "success",
        "count"   => (int) $result['count'],
        "user_id" => $user_id
    ];

    if ($debug) {
        $sample = $conn->prepare("SELECT id, user_id, product_id, status FROM favorites WHERE user_id = ? LIMIT 5");
        $sample->execute([$user_id]);
        $response['debug'] = [
            "resolved_user_id" => $user_id,
            "jwt_payload"      => $authUser,
            "sample_rows"      => $sample->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    echo json_encode($response);
} catch (Exception $e) {
    error_log("get_wishlist_count.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => $debug ? $e->getMessage() : "An error occurred.",
        "count"   => 0
    ]);
}
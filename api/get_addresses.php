<?php
/**
 * Get User Addresses
 * GET /api/get_addresses.php   (Bearer token required)
 * Returns every saved address for the authenticated customer.
 */

require_once __DIR__ . '/../config/cors.php';      // CORS + OPTIONS preflight (allows Authorization header)
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/address_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only GET requests are accepted.']);
    exit();
}

try {
    $userData    = requireAuth();
    $customer_id = (int) $userData['user_id'];

    $query = $conn->prepare(
        "SELECT * FROM customer_addresses
         WHERE customer_id = ?
         ORDER BY is_default DESC, updated_at DESC, created_at DESC"
    );
    $query->execute([$customer_id]);

    $addresses = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $addresses[] = formatAddressRow($row);
    }

    echo json_encode([
        'success' => true,
        'count'   => count($addresses),
        'data'    => $addresses,
    ]);

} catch (PDOException $e) {
    error_log('get_addresses.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load addresses. Please try again.']);
}

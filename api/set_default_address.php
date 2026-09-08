<?php
/**
 * Set Default Address
 * POST /api/set_default_address.php   (Bearer token required)
 * Body: { "id": <addressId> }  (also accepts "addressId")
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/address_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $data        = readJsonBody();
    $userData    = requireAuth();
    $customer_id = (int) $userData['user_id'];

    $address_id = (int) ($data['addressId'] ?? $data['id'] ?? 0);
    if ($address_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid address ID required']);
        exit();
    }

    // Verify the address belongs to this customer (parameterised — no SQL injection)
    $verify = $conn->prepare("SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $verify->execute([$address_id, $customer_id]);
    if ($verify->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Address not found or does not belong to customer']);
        exit();
    }

    $conn->beginTransaction();

    $conn->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")
         ->execute([$customer_id]);

    $conn->prepare("UPDATE customer_addresses SET is_default = 1, updated_at = NOW() WHERE id = ? AND customer_id = ?")
         ->execute([$address_id, $customer_id]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Default address updated successfully']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('set_default_address.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update default address. Please try again.']);
}

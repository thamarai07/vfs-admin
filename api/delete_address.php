<?php
/**
 * Delete Address
 * POST | DELETE  /api/delete_address.php   (Bearer token required)
 * Body: { "id": <addressId> }  (also accepts "addressId")
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/address_helpers.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
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

    // Ownership check
    $verify = $conn->prepare("SELECT is_default FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $verify->execute([$address_id, $customer_id]);
    $row = $verify->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Address not found or does not belong to customer']);
        exit();
    }

    $conn->beginTransaction();

    $conn->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?")
         ->execute([$address_id, $customer_id]);

    // If we removed the default address, promote the most recent remaining one.
    if ((int) $row['is_default'] === 1) {
        $next = $conn->prepare(
            "SELECT id FROM customer_addresses WHERE customer_id = ?
             ORDER BY updated_at DESC, created_at DESC LIMIT 1"
        );
        $next->execute([$customer_id]);
        if ($nextId = $next->fetchColumn()) {
            $conn->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = ?")
                 ->execute([$nextId]);
        }
    }

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('delete_address.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete address. Please try again.']);
}

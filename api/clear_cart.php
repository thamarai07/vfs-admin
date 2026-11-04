<?php
/**
 * Clear Cart API
 * POST /api/clear_cart.php   (Bearer token required)
 * Body: { "orderId": 123 }   (optional — links the cleared rows to that order)
 *
 * Marks the logged-in customer's active cart rows as 'ordered'.
 * - The customer comes from the verified JWT; a customerId in the body is ignored.
 * - The old hard-coded 'user_1' fallback is gone (it emptied customer #1's cart).
 * - create_order.php already does this inside its transaction, so this is
 *   only a safety net for older clients.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

$authUser   = requireAuth();
$customerId = (int) $authUser['user_id'];

try {
    $data    = json_decode(file_get_contents('php://input'), true) ?: [];
    $orderId = isset($data['orderId']) ? (int) $data['orderId'] : null;

    // Only link to an order that really belongs to this customer.
    if ($orderId) {
        $own = $conn->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ?");
        $own->execute([$orderId, $customerId]);
        if (!$own->fetch()) {
            $orderId = null;
        }
    }

    $stmt = $conn->prepare("
        UPDATE cart
        SET status = 'ordered', order_id = ?, updated_at = NOW()
        WHERE session_id = ? AND status = 'active'
    ");
    $stmt->execute([$orderId, 'user_' . $customerId]);
    $marked = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => $marked > 0 ? "Successfully marked $marked items as ordered" : 'No active cart items found',
        'data'    => ['markedItems' => $marked, 'orderId' => $orderId],
    ]);
} catch (Exception $e) {
    error_log('clear_cart.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not clear the cart. Please try again.']);
}

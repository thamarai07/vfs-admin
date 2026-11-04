<?php
/**
 * Cancel Order API
 * POST /api/cancel_order.php   (Bearer token required)
 * Body: { "order_id": 123, "reason": "..." }
 *
 * - The customer comes from the verified JWT. A customer_id in the body is
 *   ignored, so nobody can cancel another customer's order.
 * - Stock is put back only for orders whose stock was actually taken at
 *   checkout (orders.stock_deducted = 1, set by create_order.php). Older orders
 *   never reduced stock, so restoring for them would inflate stock.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/order_stock.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$authUser    = requireAuth();
$customer_id = (int) $authUser['user_id'];

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$order_id = (int) ($data['order_id'] ?? 0);
$reason   = trim((string) ($data['reason'] ?? ''));
if ($reason === '') {
    $reason = 'Customer requested cancellation';
}
$reason = mb_substr($reason, 0, 500);

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
    exit;
}

$cancellableStatuses = ['pending', 'confirmed', 'processing'];

try {
    $conn->beginTransaction();

    // Lock the row so two cancel taps (or a cancel racing an admin update) can't both run.
    $checkStmt = $conn->prepare("SELECT status, payment_status FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE");
    $checkStmt->execute([$order_id, $customer_id]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found or does not belong to you']);
        exit;
    }

    if (!in_array($order['status'], $cancellableStatuses, true)) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Order cannot be cancelled at this stage']);
        exit;
    }

    $old_status = $order['status'];

    $conn->prepare("
        UPDATE orders
        SET status = 'cancelled',
            updated_at = CURRENT_TIMESTAMP,
            notes = CONCAT(COALESCE(notes, ''), '\nCancellation Reason: ', ?)
        WHERE id = ?
    ")->execute([$reason, $order_id]);

    try {
        $conn->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes)
            VALUES (?, ?, 'cancelled', ?, ?)
        ")->execute([$order_id, $old_status, $customer_id, $reason]);
    } catch (PDOException $e) {
        error_log('cancel_order.php history skipped: ' . $e->getMessage());
    }

    // Put stock back — only what create_order.php actually took (kg lines).
    $stockRestored = restoreOrderStock($conn, $order_id);

    $conn->commit();

    echo json_encode([
        'status'           => 'success',
        'message'          => 'Order cancelled successfully',
        // COD orders are never paid up-front. The payment_status enum has no
        // 'refunded' value, so paid orders are flagged here for a manual refund.
        'refund_initiated' => $order['payment_status'] === 'paid',
        'stock_restored'   => $stockRestored,
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("cancel_order.php DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'An error occurred while cancelling the order. Please try again.'
    ]);
}

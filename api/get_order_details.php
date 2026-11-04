<?php
/**
 * Get Single Order Details API
 * GET /api/get_order_details.php?order_id=123   (Bearer token required)
 *
 * Returns one order that belongs to the logged-in customer, with its items
 * and status history. Rewritten for PDO (the old version called MySQLi
 * methods on the PDO connection and crashed) and scoped to the JWT owner.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$authUser   = requireAuth();
$customerId = (int) $authUser['user_id'];
$orderId    = (int) ($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    $itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $items = array_map(fn($i) => [
        'id'        => (int) $i['id'],
        'productId' => (int) $i['product_id'],
        'name'      => $i['product_name'],
        'unit'      => $i['unit'] ?? null,
        'price'     => (float) $i['price'],
        'quantity'  => (float) $i['quantity'],
        'subtotal'  => (float) $i['subtotal'],
    ], $itemsStmt->fetchAll(PDO::FETCH_ASSOC));

    $history = [];
    try {
        $hStmt = $conn->prepare("
            SELECT old_status, new_status, notes, created_at
            FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC
        ");
        $hStmt->execute([$orderId]);
        foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $history[] = [
                'oldStatus' => $h['old_status'],
                'newStatus' => $h['new_status'],
                'notes'     => $h['notes'],
                'createdAt' => $h['created_at'],
            ];
        }
    } catch (PDOException $e) {
        error_log('get_order_details.php history skipped: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'id'              => (int) $order['id'],
            'orderNumber'     => $order['order_number'],
            'status'          => $order['status'],
            'paymentMethod'   => $order['payment_method'],
            'paymentStatus'   => $order['payment_status'],
            'customerName'    => $order['customer_name'],
            'customerPhone'   => $order['customer_phone'],
            'deliveryAddress' => $order['customer_address'],
            'notes'           => $order['notes'],
            'subtotal'        => (float) $order['subtotal'],
            'tax'             => (float) $order['tax'],
            'shippingCharge'  => (float) $order['shipping_charge'],
            'totalAmount'     => (float) $order['total_amount'],
            'deliveryDate'    => $order['delivery_date'],
            'items'           => $items,
            'itemCount'       => count($items),
            'statusHistory'   => $history,
            'createdAt'       => $order['created_at'],
            'updatedAt'       => $order['updated_at'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('get_order_details.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load this order. Please try again.']);
}

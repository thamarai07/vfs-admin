<?php
/**
 * Get Customer Orders API
 * REWRITTEN to use PDO exclusively (previous version used MySQLi methods on PDO connection which crashes).
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    if (!isset($_GET['customer_id'])) {
        throw new Exception('Customer ID is required');
    }

    $customerId = (int) $_GET['customer_id'];
    $page       = max((int) ($_GET['page']  ?? 1), 1);
    $limit      = min(max((int) ($_GET['limit'] ?? 10), 1), 100);
    $offset     = ($page - 1) * $limit;
    $status     = $_GET['status'] ?? null;

    $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    // ---- Build WHERE clause ----
    $where  = "WHERE o.customer_id = ?";
    $params = [$customerId];

    if ($status && in_array($status, $validStatuses)) {
        $where   .= " AND o.status = ?";
        $params[] = $status;
    }

    // ---- Total count ----
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where");
    $countStmt->execute($params);
    $totalOrders = (int) $countStmt->fetch()['total'];

    // ---- Fetch orders ----
    $orderParams   = array_merge($params, [$limit, $offset]);
    $stmt          = $conn->prepare("
        SELECT
            o.id, o.order_number,
            o.subtotal, o.tax, o.shipping_charge, o.total_amount,
            o.notes as customer_notes,
            o.payment_method, o.payment_status,
            o.status as order_status,
            o.customer_name, o.customer_phone, o.customer_address,
            o.created_at, o.updated_at
        FROM orders o
        $where
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($orderParams);
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($rawOrders as $order) {
        $orderId = $order['id'];

        // ---- Fetch order items via PDO ----
        $itemsStmt = $conn->prepare("
            SELECT id, product_id, product_name, price, quantity, subtotal
            FROM order_items
            WHERE order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(fn($item) => [
            'id'        => (int)   $item['id'],
            'productId' => (int)   $item['product_id'],
            'name'      => $item['product_name'],
            'price'     => (float) ($item['price'] ?? 0),
            'quantity'  => (float) $item['quantity'],
            'subtotal'  => (float) $item['subtotal']
        ], $rawItems);

        $orders[] = [
            'id'              => (int)   $order['id'],
            'orderNumber'     => $order['order_number'],
            'subtotal'        => (float) $order['subtotal'],
            'tax'             => (float) $order['tax'],
            'shippingCharge'  => (float) $order['shipping_charge'],
            'totalAmount'     => (float) $order['total_amount'],
            'customerNotes'   => $order['customer_notes'],
            'paymentMethod'   => $order['payment_method'],
            'paymentStatus'   => $order['payment_status'],
            'orderStatus'     => $order['order_status'],
            'deliveryAddress' => $order['customer_address'],
            'items'           => $items,
            'itemCount'       => count($items),
            'createdAt'       => $order['created_at'],
            'updatedAt'       => $order['updated_at'],
        ];
    }

    echo json_encode([
        'success'    => true,
        'data'       => $orders,
        'pagination' => [
            'total'      => $totalOrders,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($totalOrders / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log("get_orders.php Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
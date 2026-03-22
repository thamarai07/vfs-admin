<?php
/**
 * Get Single Order Details API
 * File: get_order_details.php
 * 
 * This API retrieves detailed information about a specific order
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once('../config/db.php');


try {
    // Get order ID from query parameter
    if (!isset($_GET['order_id'])) {
        throw new Exception('Order ID is required');
    }
    
    $orderId = intval($_GET['order_id']);
    
    // Get order details
    $query = "
        SELECT 
            o.*,
            c.name as customer_name,
            c.email as customer_email,
            c.phone as customer_phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Order not found');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $itemsQuery = "
        SELECT 
            oi.*,
            p.stock as current_stock
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ";
    
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $orderId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'id' => intval($item['id']),
            'productId' => intval($item['product_id']),
            'name' => $item['product_name'],
            'image' => $item['product_image'],
            'category' => $item['product_category'],
            'slug' => $item['product_slug'],
            'pricePerKg' => floatval($item['price_per_kg']),
            'quantity' => floatval($item['quantity']),
            'subtotal' => floatval($item['subtotal']),
            'currentStock' => $item['current_stock'] ? floatval($item['current_stock']) : null
        ];
    }
    $itemsStmt->close();
    
    // Get order status history
    $historyQuery = "
        SELECT 
            osh.*,
            c.name as changed_by_name
        FROM order_status_history osh
        LEFT JOIN customers c ON osh.changed_by = c.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at ASC
    ";
    
    $historyStmt = $conn->prepare($historyQuery);
    $historyStmt->bind_param("i", $orderId);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    
    $statusHistory = [];
    while ($history = $historyResult->fetch_assoc()) {
        $statusHistory[] = [
            'id' => intval($history['id']),
            'oldStatus' => $history['old_status'],
            'newStatus' => $history['new_status'],
            'changedBy' => $history['changed_by_name'],
            'notes' => $history['notes'],
            'createdAt' => $history['created_at']
        ];
    }
    $historyStmt->close();
    
    // Format response
    $orderData = [
        'id' => intval($order['id']),
        'orderNumber' => $order['order_number'],
        'customer' => [
            'id' => intval($order['customer_id']),
            'name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone']
        ],
        'pricing' => [
            'subtotal' => floatval($order['subtotal']),
            'tax' => floatval($order['tax']),
            'shippingCharge' => floatval($order['shipping_charge']),
            'totalAmount' => floatval($order['total_amount'])
        ],
        'notes' => [
            'customer' => $order['customer_notes'],
            'admin' => $order['admin_notes']
        ],
        'payment' => [
            'method' => $order['payment_method'],
            'status' => $order['payment_status'],
            'paymentId' => $order['payment_id'],
            'gateway' => $order['payment_gateway'],
            'paidAt' => $order['paid_at']
        ],
        'status' => $order['order_status'],
        'deliveryAddress' => [
            'name' => $order['delivery_name'],
            'phone' => $order['delivery_phone'],
            'email' => $order['delivery_email'],
            'flatNo' => $order['delivery_flat_no'],
            'landmark' => $order['delivery_landmark'],
            'fullAddress' => $order['delivery_full_address'],
            'label' => $order['delivery_label'],
            'coordinates' => $order['delivery_coordinates'] ? json_decode($order['delivery_coordinates']) : null
        ],
        'items' => $items,
        'itemCount' => count($items),
        'statusHistory' => $statusHistory,
        'timestamps' => [
            'createdAt' => $order['created_at'],
            'updatedAt' => $order['updated_at'],
            'confirmedAt' => $order['confirmed_at'],
            'shippedAt' => $order['shipped_at'],
            'deliveredAt' => $order['delivered_at'],
            'cancelledAt' => $order['cancelled_at']
        ]
    ];
    
    // Send response
    echo json_encode([
        'success' => true,
        'data' => $orderData
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
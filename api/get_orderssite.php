<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once('../config/db.php');

// Get customer_id from query params
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = ($page - 1) * $limit;

if (!$customer_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Customer ID is required'
    ]);
    exit;
}

try {
    // Build query with filters
    $whereConditions = ["o.customer_id = :customer_id"];
    $params = [':customer_id' => $customer_id];

    if ($status !== 'all') {
        $whereConditions[] = "o.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $whereConditions[] = "(o.order_number LIKE :search OR o.customer_name LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM orders o WHERE $whereClause";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch orders with customer info
    $query = "
        SELECT 
            o.*,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE $whereClause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fetch order items - FIXED: Changed 'images' to 'image'
        $itemsQuery = "
            SELECT 
                oi.*,
                p.name as product_name,
                p.image as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bindParam(':order_id', $row['id']);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Process items to get image URL
        foreach ($items as &$item) {
            if (!empty($item['product_image'])) {
                // If image is stored as URL, use it directly
                $item['product_image'] = $item['product_image'];
            } else {
                $item['product_image'] = 'https://placehold.co/100x100/e5e7eb/6b7280?text=No+Image';
            }
        }

        // Parse address if stored as JSON or text
        $addressData = $row['customer_address'];
        
        // Try to decode as JSON first
        $address = json_decode($addressData, true);
        
        if (!$address || !is_array($address)) {
            // If not JSON, create object from fields
            $address = [
                'name' => $row['customer_name'] ?? '',
                'phone' => $row['customer_phone'] ?? '',
                'email' => '',
                'fullAddress' => $addressData ?? '',
                'landmark' => '',
                'label' => 'Home'
            ];
        }

        $orders[] = [
            'id' => (int)$row['id'],
            'order_number' => $row['order_number'],
            'customer_id' => (int)$row['customer_id'],
            'items' => $items,
            'address' => $address,
            'subtotal' => (float)$row['subtotal'],
            'tax' => (float)$row['tax'],
            'shipping_charge' => (float)$row['shipping_charge'],
            'total' => (float)$row['total'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'payment_method' => $row['payment_method'] ?? 'cash',
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'delivery_date' => $row['delivery_date'],
            'estimated_delivery' => $row['delivery_date']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'orders' => $orders,
        'pagination' => [
            'total' => (int)$totalOrders,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($totalOrders / $limit)
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
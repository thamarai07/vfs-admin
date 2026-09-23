<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once('../config/db.php');

// Get customer_id from query params
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';   // YYYY-MM-DD
$dateTo   = isset($_GET['to'])   ? trim($_GET['to'])   : '';   // YYYY-MM-DD
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = ($page - 1) * $limit;
$datesOnly = isset($_GET['dates']) && $_GET['dates'] == '1'; // calendar: list order dates

if (!$customer_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Customer ID is required'
    ]);
    exit;
}

$baseUrl = rtrim(env('IMAGE_BASE_URL', ''), '/') . '/';
$fixImg = function ($img) use ($baseUrl) {
    $img = trim((string) $img);
    if ($img === '') return 'https://placehold.co/100x100/e5e7eb/6b7280?text=No+Image';
    if (preg_match('#^https?://#i', $img)) return $img;
    // stored as "a.jpg,b.jpg" — take the first
    $img = trim(explode(',', $img)[0]);
    return $baseUrl . ltrim($img, '/');
};

try {
    // ── Calendar mode: just the distinct dates this customer placed orders on ──
    if ($datesOnly) {
        $dStmt = $conn->prepare("
            SELECT DATE(created_at) AS d, COUNT(*) AS c
            FROM orders
            WHERE customer_id = :cid
            GROUP BY DATE(created_at)
            ORDER BY d DESC
        ");
        $dStmt->execute([':cid' => $customer_id]);
        echo json_encode([
            'status' => 'success',
            'dates'  => $dStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    // ── Build WHERE (distinct param names — MySQL native prepares can't reuse one) ──
    $whereConditions = ["o.customer_id = :customer_id"];
    $params = [':customer_id' => $customer_id];

    if ($status !== 'all') {
        $whereConditions[] = "o.status = :status";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $whereConditions[] = "(o.order_number LIKE :s1
            OR o.customer_name LIKE :s2
            OR EXISTS (SELECT 1 FROM order_items oi2
                       WHERE oi2.order_id = o.id AND oi2.product_name LIKE :s3))";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
        $params[':s3'] = "%$search%";
    }

    if ($dateFrom !== '') {
        $whereConditions[] = "DATE(o.created_at) >= :dfrom";
        $params[':dfrom'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $whereConditions[] = "DATE(o.created_at) <= :dto";
        $params[':dto'] = $dateTo;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Total count for pagination
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders o WHERE $whereClause");
    $countStmt->execute($params);
    $totalOrders = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch orders
    $query = "
        SELECT o.*, COUNT(oi.id) AS item_count
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
        $itemsStmt = $conn->prepare("
            SELECT oi.*, p.name AS product_name, p.image AS product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $itemsStmt->bindValue(':order_id', $row['id'], PDO::PARAM_INT);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            // Prefer the product's current image; fall back to whatever the line stored.
            $item['product_image'] = $fixImg($item['product_image'] ?? ($item['image'] ?? ''));
        }
        unset($item);

        // Parse address if stored as JSON or text
        $address = json_decode($row['customer_address'], true);
        if (!$address || !is_array($address)) {
            $address = [
                'name' => $row['customer_name'] ?? '',
                'phone' => $row['customer_phone'] ?? '',
                'email' => '',
                'fullAddress' => $row['customer_address'] ?? '',
                'landmark' => '',
                'label' => 'Home'
            ];
        }

        $orders[] = [
            'id' => (int) $row['id'],
            'order_number' => $row['order_number'],
            'customer_id' => (int) $row['customer_id'],
            'items' => $items,
            'address' => $address,
            'subtotal' => (float) $row['subtotal'],
            'tax' => (float) $row['tax'],
            'shipping_charge' => (float) $row['shipping_charge'],
            // Some rows fill `total`, newer ones (create_order.php) fill `total_amount`.
            'total' => ((float) ($row['total'] ?? 0)) > 0
                       ? (float) $row['total']
                       : (float) ($row['total_amount'] ?? 0),
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
            'total' => $totalOrders,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($totalOrders / max(1, $limit))
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('../config/db.php');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are accepted.'
    ]);
    exit();
}

try {
    // Get customer ID from query parameter
    $customer_id = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

    if ($customer_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid customer ID is required'
        ]);
        exit();
    }

    // Verify customer exists
    $check_customer = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $check_customer->execute([$customer_id]);

    if ($check_customer->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
        exit();
    }

    // Fetch all addresses for the customer
    $query = $conn->prepare("SELECT * FROM customer_addresses 
              WHERE customer_id = ? 
              ORDER BY is_default DESC, created_at DESC");
    $query->execute([$customer_id]);

    $addresses = [];
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $addresses[] = [
            'id' => intval($row['id']),
            'customer_id' => intval($row['customer_id']),
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'flat_no' => $row['flat_no'],
            'landmark' => $row['landmark'],
            'full_address' => $row['full_address'],
            'label' => $row['label'],
            'latitude' => floatval($row['latitude']),
            'longitude' => floatval($row['longitude']),
            'is_default' => intval($row['is_default']),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($addresses),
        'data' => $addresses
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
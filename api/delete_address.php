<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $address_id = isset($data['addressId']) ? intval($data['addressId']) : 0;
    $customer_id = isset($data['customerId']) ? intval($data['customerId']) : 0;

    if ($address_id <= 0 || $customer_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid address ID and customer ID required'
        ]);
        exit();
    }

    // Verify address belongs to customer
    $verify = $conn->prepare("SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $verify->execute([$address_id, $customer_id]);

    if ($verify->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Address not found or does not belong to customer'
        ]);
        exit();
    }

    // Delete the address
    $delete_query = $conn->prepare("DELETE FROM customer_addresses WHERE id = ?");
    
    if ($delete_query->execute([$address_id])) {
        echo json_encode([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete address'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

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

mysqli_begin_transaction($conn);

try {
    // Verify address belongs to customer
    $verify = "SELECT id FROM customer_addresses WHERE id = $address_id AND customer_id = $customer_id";
    $verify_result = mysqli_query($conn, $verify);
    
    if (mysqli_num_rows($verify_result) === 0) {
        throw new Exception('Address not found or does not belong to customer');
    }

    // Unset all defaults for this customer
    $unset_query = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = $customer_id";
    if (!mysqli_query($conn, $unset_query)) {
        throw new Exception('Failed to unset defaults');
    }

    // Set new default
    $set_query = "UPDATE customer_addresses SET is_default = 1, updated_at = NOW() WHERE id = $address_id";
    if (!mysqli_query($conn, $set_query)) {
        throw new Exception('Failed to set default address');
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Default address updated successfully'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
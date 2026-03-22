<?php
require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        throw new Exception('No input data received');
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate required fields
    $required_fields = ['customerId', 'name', 'phoneNumber', 'email', 'flatNo', 'fullAddress', 'label', 'coordinates'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }

    // Validate coordinates
    if (!isset($data['coordinates']['lat']) || !isset($data['coordinates']['lng'])) {
        throw new Exception('Invalid coordinates format');
    }

    // Extract and sanitize data
    $customer_id = (int)$data['customerId'];
    $name = trim($data['name']);
    $phone = trim($data['phoneNumber']);
    $email = trim($data['email']);
    $flat_no = trim($data['flatNo']);
    $landmark = isset($data['landmark']) && !empty(trim($data['landmark'])) ? trim($data['landmark']) : null;
    $full_address = trim($data['fullAddress']);
    $label = trim($data['label']);
    $latitude = (float)$data['coordinates']['lat'];
    $longitude = (float)$data['coordinates']['lng'];
    $is_default = isset($data['isDefault']) && $data['isDefault'] ? 1 : 0;

    // Validate label
    if (!in_array($label, ['Home', 'Work', 'Other'])) {
        throw new Exception('Invalid label. Must be Home, Work, or Other.');
    }

    // Verify customer exists
    $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Customer not found');
    }

    // Start transaction
    $conn->beginTransaction();

    // If this address is set as default, unset others
    if ($is_default) {
        $stmt = $conn->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
    }

    // Insert new address
    $sql = "INSERT INTO customer_addresses 
            (customer_id, name, phone, email, flat_no, landmark, full_address, label, latitude, longitude, is_default, created_at, updated_at) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $customer_id,
        $name,
        $phone,
        $email,
        $flat_no,
        $landmark,
        $full_address,
        $label,
        $latitude,
        $longitude,
        $is_default
    ]);

    $address_id = $conn->lastInsertId();

    // Fetch the inserted address
    $stmt = $conn->prepare("SELECT * FROM customer_addresses WHERE id = ?");
    $stmt->execute([$address_id]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        throw new Exception('Failed to retrieve saved address');
    }

    // Commit transaction
    $conn->commit();

    // Format response
    $response = [
        'success' => true,
        'message' => 'Address saved successfully',
        'data' => [
            'id' => (int)$address['id'],
            'customer_id' => (int)$address['customer_id'],
            'name' => $address['name'],
            'phone' => $address['phone'],
            'email' => $address['email'],
            'flat_no' => $address['flat_no'],
            'landmark' => $address['landmark'],
            'full_address' => $address['full_address'],
            'label' => $address['label'],
            'latitude' => (float)$address['latitude'],
            'longitude' => (float)$address['longitude'],
            'is_default' => (int)$address['is_default'],
            'created_at' => $address['created_at'],
            'updated_at' => $address['updated_at']
        ]
    ];

    http_response_code(201);
    echo json_encode($response);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("save_address.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving the address. Please try again.'
    ]);
}
?>
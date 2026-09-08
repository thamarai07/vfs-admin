<?php
/**
 * Update Address
 * POST | PUT  /api/update_address.php   (Bearer token required)
 * Body must include `id` (or `addressId`) of an address owned by the customer.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/address_helpers.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST or PUT.']);
    exit();
}

try {
    $data        = readJsonBody();
    $userData    = requireAuth();
    $customer_id = (int) $userData['user_id'];

    $address_id = (int) ($data['id'] ?? $data['addressId'] ?? 0);
    if ($address_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid address ID required']);
        exit();
    }

    // Ownership check
    $verify = $conn->prepare("SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $verify->execute([$address_id, $customer_id]);
    if ($verify->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Address not found or does not belong to customer']);
        exit();
    }

    // ---- Validation -----------------------------------------------------
    $name  = trim($data['name'] ?? '');
    $phone = preg_replace('/\D/', '', (string) ($data['phoneNumber'] ?? $data['phone'] ?? ''));
    $email = trim($data['email'] ?? '');

    $flat_no        = trim($data['flatNo'] ?? $data['flat_no'] ?? '');
    $street_address = trim($data['streetAddress'] ?? $data['street_address'] ?? '');
    $area           = trim($data['area'] ?? '');
    $landmark       = trim($data['landmark'] ?? '');
    $city           = trim($data['city'] ?? '');
    $state          = trim($data['state'] ?? '');
    $pincode        = trim($data['pincode'] ?? '');
    $country        = trim($data['country'] ?? '') ?: 'India';
    $legacyFull     = trim($data['fullAddress'] ?? $data['full_address'] ?? '');
    $label          = normalizeLabel($data['label'] ?? 'Home');
    $is_default     = !empty($data['isDefault']) || !empty($data['is_default']) ? 1 : 0;
    $phone_verified = !empty($data['phoneVerified']) || !empty($data['phone_verified']) ? 1 : 0;

    $errors = [];
    if ($name === '')                        $errors[] = 'Full name is required';
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'A valid 10-digit mobile number is required';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    if ($pincode !== '' && !preg_match('/^[1-9][0-9]{5}$/', $pincode)) $errors[] = 'Invalid 6-digit pincode';
    if ($street_address === '' && $legacyFull === '' && $flat_no === '') $errors[] = 'Address details are required';

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => implode('. ', $errors), 'errors' => $errors]);
        exit();
    }

    $latitude  = isset($data['coordinates']['lat']) ? (float) $data['coordinates']['lat'] : null;
    $longitude = isset($data['coordinates']['lng']) ? (float) $data['coordinates']['lng'] : null;

    $full_address = $legacyFull !== '' ? $legacyFull : composeFullAddress([
        'flat_no'        => $flat_no,
        'street_address' => $street_address,
        'area'           => $area,
        'landmark'       => $landmark,
        'city'           => $city,
        'state'          => $state,
        'pincode'        => $pincode,
        'country'        => $country,
    ]);

    // ---- Persist --------------------------------------------------------
    $conn->beginTransaction();

    if ($is_default) {
        $conn->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")
             ->execute([$customer_id]);
    }

    $sql = "UPDATE customer_addresses SET
                name = ?, phone = ?, email = ?, flat_no = ?, street_address = ?, area = ?,
                landmark = ?, city = ?, state = ?, pincode = ?, country = ?, full_address = ?,
                label = ?, latitude = ?, longitude = ?, is_default = ?, phone_verified = ?,
                updated_at = NOW()
            WHERE id = ? AND customer_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $name, $phone, ($email ?: null), $flat_no, $street_address, $area,
        $landmark, $city, $state, $pincode, $country, $full_address,
        $label, $latitude, $longitude, $is_default, $phone_verified,
        $address_id, $customer_id,
    ]);

    $stmt = $conn->prepare("SELECT * FROM customer_addresses WHERE id = ?");
    $stmt->execute([$address_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Address updated successfully',
        'data'    => formatAddressRow($row),
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('update_address.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
}

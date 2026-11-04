<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$token = trim($data['remember_me_token'] ?? '');

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'No token provided']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT rmt.customer_id, c.id, c.name, c.email, c.phone
        FROM remember_me_tokens rmt
        JOIN customers c ON c.id = rmt.customer_id
        WHERE rmt.token = :token
          AND rmt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired remember me token']);
        exit;
    }

    // Rotate the remember_me token on each use (security best practice)
    $newToken  = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    $upd = $conn->prepare("
        UPDATE remember_me_tokens 
        SET token = :new_token, expires_at = :expires_at 
        WHERE customer_id = :id
    ");
    $upd->execute([
        ':new_token'  => $newToken,
        ':expires_at' => $expiresAt,
        ':id'         => $row['customer_id'],
    ]);

    // Issue a fresh JWT
    $jwt       = jwtSign([
        'user_id' => $row['id'],
        'email'   => $row['email'],
        'name'    => $row['name'],
        'iat'     => time(),
        'exp'     => time() + (7 * 24 * 60 * 60),
    ]);

    echo json_encode([
        'status'             => 'success',
        'token'              => $jwt,
        'remember_me_token'  => $newToken, // rotated — frontend must update stored value
        'user'               => [
            'id'    => (int)$row['id'],
            'name'  => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
        ],
    ]);

} catch (Throwable $e) {
    error_log("AutoLogin DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
}

$conn = null;
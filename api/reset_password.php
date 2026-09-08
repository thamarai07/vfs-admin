<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['token']) || empty($data['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Token and password are required']);
    exit;
}

$token    = trim($data['token']);
$password = $data['password'];

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
    exit;
}

try {
    // Validate token (not used, not expired)
    $stmt = $conn->prepare("
        SELECT pr.id, pr.customer_id 
        FROM password_resets pr
        WHERE pr.token = :token
          AND pr.used = 0
          AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired reset link']);
        exit;
    }

    // Update password
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $upd  = $conn->prepare("UPDATE customers SET password_hash = :hash WHERE id = :id");
    $upd->execute([':hash' => $hash, ':id' => $reset['customer_id']]);

    // Mark token as used (soft-delete so it can't be reused)
    $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
    $mark->execute([':id' => $reset['id']]);

    echo json_encode(['status' => 'success', 'message' => 'Password reset successfully']);

} catch (PDOException $e) {
    error_log("ResetPassword DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again.']);
}

$conn = null;
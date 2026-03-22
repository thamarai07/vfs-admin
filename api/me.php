<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$authUser = requireAuth();

$stmt = $conn->prepare("SELECT id, name, email, phone FROM customers WHERE id = :id");
$stmt->execute([':id' => $authUser['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

echo json_encode(['status' => 'success', 'user' => $user]);

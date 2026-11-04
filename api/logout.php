<?php
/**
 * Logout. The JWT is stateless, so the client simply forgets it.
 * If the client sends its remember-me token, that token is revoked so it
 * can't log the device back in. (clearAuthCookie() was never defined, which
 * made this endpoint crash with a 500.)
 */
require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true) ?: [];
$token = trim((string) ($data['remember_me_token'] ?? ''));

if ($token !== '') {
    try {
        require_once __DIR__ . '/../config/db.php';
        $conn->prepare("DELETE FROM remember_me_tokens WHERE token = ?")->execute([$token]);
    } catch (Throwable $e) {
        error_log('logout.php: ' . $e->getMessage());
    }
}

echo json_encode(['status' => 'success', 'message' => 'Logged out']);

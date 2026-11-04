<?php
/**
 * Verify a login token.
 * GET|POST /api/verify_token.php   (Authorization: Bearer <jwt>)
 *
 * Uses the shared verifier in config/jwt.php, which reads JWT_SECRET from .env.
 * (This file used to carry its own hard-coded secret, which is in git history —
 * it no longer matches the live secret and is not used anywhere now.)
 */
require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/jwt.php';

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No token provided']);
    exit();
}

$payload = verifyJWT($token);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
    exit();
}

echo json_encode([
    'status' => 'success',
    'user'   => [
        'user_id' => $payload['user_id'] ?? null,
        'email'   => $payload['email'] ?? null,
        'name'    => $payload['name'] ?? null,
        'exp'     => $payload['exp'] ?? null,
    ],
]);

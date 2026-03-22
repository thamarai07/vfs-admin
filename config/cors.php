<?php
/**
 * CORS & Security Headers
 * Include this at the top of every API file instead of repeating headers inline.
 * Reads ALLOWED_ORIGIN from .env / environment.
 */

require_once __DIR__ . '/env.php';

$allowedOrigin = env('ALLOWED_ORIGIN', '*');

// ---- CORS ----
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// ---- Security Headers ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Handle OPTIONS preflight — return immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

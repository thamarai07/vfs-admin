<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/jwt.php';
header('Content-Type: application/json');

clearAuthCookie();
echo json_encode(['status' => 'success', 'message' => 'Logged out']);
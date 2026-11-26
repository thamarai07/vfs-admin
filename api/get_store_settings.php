<?php
/**
 * Public store settings for the website and the app.
 * GET /api/get_store_settings.php
 *
 * { "status": "success", "data": { "tax_rate_percent": 8 } }
 *
 * The clients use this to show the same tax create_order.php will charge.
 * Not cached, so an admin change shows up on the next load.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/store_settings.php';

echo json_encode([
    'status' => 'success',
    'data'   => [
        'tax_rate_percent' => getTaxRatePercent($conn),
    ],
]);

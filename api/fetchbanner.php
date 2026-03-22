<?php
// api/fetchbanner.php

require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/bannerconfig.php';
require_once __DIR__ . '/../APIController/BannerAPIController.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Standardized logging: removed hardcoded ini_set('error_log')

$isDevMode = env('APP_ENV', 'production') !== 'production';

try {
    $controller = new BannerAPIController();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Debug mode – only available in development environment
        if ($isDevMode && isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo json_encode([
                'debug_mode' => true,
                'php_version' => PHP_VERSION,
                'timestamp'   => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // Clear cache endpoint
        if (isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
            $result = $controller->clearCache();
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;
        }

        // Build filters
        $filters = [
            'device'           => $_GET['device'] ?? 'desktop',
            'city'             => $_GET['city']    ?? null,
            'pincode'          => $_GET['pincode'] ?? null,
            'user_segment'     => $_GET['user_segment'] ?? null,
            'include_inactive' => isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1'
        ];

        if (empty($filters['device'])) {
            http_response_code(400);
            echo json_encode([
                'success'        => false,
                'message'        => 'Device parameter is required',
                'allowed_values' => ['desktop', 'tablet', 'mobile']
            ], JSON_PRETTY_PRINT);
            exit;
        }

        $result = $controller->getDynamicBanners($filters);
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'track_impression':
                    $result = ['success' => true, 'message' => 'Impression tracked'];
                    break;
                case 'track_click':
                    $result = ['success' => true, 'message' => 'Click tracked'];
                    break;
                default:
                    http_response_code(400);
                    $result = ['success' => false, 'message' => 'Invalid action'];
            }
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;
        }
    }

    http_response_code(405);
    echo json_encode([
        'success'         => false,
        'message'         => 'Method not allowed',
        'allowed_methods' => ['GET', 'POST', 'OPTIONS']
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Banner API Error: ' . $e->getMessage());

    http_response_code(500);
    $errorResponse = [
        'success'   => false,
        'message'   => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Detailed error only in development
    if ($isDevMode) {
        $errorResponse['error_details'] = [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
    }

    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}
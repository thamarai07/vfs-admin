<?php
/**
 * Admin-panel guard.
 * Include at the very top of every admin page or admin-only endpoint.
 *
 *   require_once __DIR__ . '/config/admin_auth.php';
 *   requireAdmin();          // HTML pages: redirects to login.php
 *   requireAdmin(true);      // JSON endpoints: 401 JSON instead of a redirect
 *
 * login.php sets $_SESSION['admin_id'] on a successful admin login.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function requireAdmin(bool $json = false): void {
    if (!empty($_SESSION['admin_id'])) {
        return;
    }
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        // Admin pages live in the backend root; endpoints under /api go one level up.
        $prefix = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'api' ? '../' : '';
        header('Location: ' . $prefix . 'login.php');
    }
    exit;
}
